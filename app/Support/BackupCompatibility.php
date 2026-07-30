<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use ZipArchive;

/**
 * Pre-restore compatibility gate. Given a backup archive, verifies the
 * TARGET machine can safely receive it BEFORE anything is overwritten:
 *
 *  - manifest present + supported format
 *  - archive integrity (SHA-256 of database.sql matches the manifest)
 *  - PHP version (target must not be older than the backup's) + extensions
 *  - Laravel major version
 *  - ShopKit version (never restore a NEWER backup into OLDER code)
 *  - DB driver + server major version
 *  - migration lineage (backup must not contain migrations unknown to this
 *    codebase; extra code migrations are fine — they run after restore)
 *  - APP_KEY fingerprint (warns: encrypted columns won't decrypt if changed)
 *  - mysql client availability (needed to import the dump)
 *
 * errors  → restore is blocked (unless --skip-checks)
 * warnings→ restore allowed, shown prominently
 * notes   → informational (e.g. "3 newer migrations will run after restore")
 */
class BackupCompatibility
{
    /**
     * @return object{ok: bool, legacy: bool, errors: string[], warnings: string[], notes: string[], manifest: ?array}
     */
    public static function check(string $zipPath): object
    {
        $errors = [];
        $warnings = [];
        $notes = [];
        $manifest = null;
        $legacy = false;

        if (! is_file($zipPath)) {
            return self::result(false, false, ["Archive not found: {$zipPath}"], [], [], null);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return self::result(false, false, ['Archive is not a readable zip file.'], [], [], null);
        }

        $raw = $zip->getFromName('manifest.json');

        if ($raw === false) {
            // Pre-manifest archive (or foreign zip). Restorable only with an
            // explicit override — we can't verify anything about it.
            $zip->close();

            return self::result(false, true,
                ['No manifest.json in this archive — it predates the manifest system (or is not a ShopKit backup). Compatibility cannot be verified. Restore with --skip-checks only if you know its origin.'],
                [], [], null);
        }

        $manifest = json_decode($raw, true);

        if (! is_array($manifest)) {
            $zip->close();

            return self::result(false, false, ['manifest.json is corrupt (invalid JSON).'], [], [], null);
        }

        // ── Format ───────────────────────────────────────────────────
        if ((int) ($manifest['format'] ?? 0) > BackupManifest::FORMAT) {
            $errors[] = 'Backup format v'.$manifest['format'].' is newer than this ShopKit understands (v'.BackupManifest::FORMAT.'). Update ShopKit first.';
        }

        // ── Archive integrity ────────────────────────────────────────
        $expectedSha = $manifest['checksums']['database.sql'] ?? null;
        $hasSql = $zip->locateName('database.sql') !== false;

        if (($manifest['includes']['database'] ?? false) && ! $hasSql) {
            $errors[] = 'Manifest says a database dump is included, but database.sql is missing — the archive is incomplete.';
        }

        if ($expectedSha && $hasSql) {
            $stream = $zip->getStream('database.sql');
            $ctx = hash_init('sha256');
            while ($stream && ! feof($stream)) {
                hash_update($ctx, (string) fread($stream, 1024 * 1024));
            }
            if ($stream) {
                fclose($stream);
            }
            $actualSha = hash_final($ctx);

            if (! hash_equals($expectedSha, $actualSha)) {
                $errors[] = 'database.sql checksum mismatch — the archive is corrupted (bad upload/transfer). Do not restore it.';
            }
        }

        // ── PHP ──────────────────────────────────────────────────────
        $backupPhp = (string) ($manifest['php']['version'] ?? '0');
        $currentMm = self::majorMinor(PHP_VERSION);
        $backupMm = self::majorMinor($backupPhp);

        if (version_compare($currentMm, $backupMm, '<')) {
            $errors[] = "PHP too old: this server runs PHP {$currentMm}, the backup was made on PHP {$backupMm}. Upgrade PHP before restoring.";
        } elseif ($currentMm !== $backupMm) {
            $warnings[] = "PHP version differs: backup made on {$backupMm}, this server runs {$currentMm} (newer — usually fine).";
        }

        foreach ((array) ($manifest['php']['extensions'] ?? []) as $ext => $hadIt) {
            if ($hadIt && ! extension_loaded($ext)) {
                $errors[] = "Missing PHP extension \"{$ext}\" — the backup's site depends on it. Install/enable it first.";
            }
        }

        // ── Laravel ──────────────────────────────────────────────────
        $backupLaravel = (int) explode('.', (string) ($manifest['laravel'] ?? '0'))[0];
        $currentLaravel = (int) explode('.', app()->version())[0];

        if ($currentLaravel < $backupLaravel) {
            $errors[] = "Laravel too old: backup from Laravel {$backupLaravel}.x, this codebase is {$currentLaravel}.x.";
        } elseif ($currentLaravel > $backupLaravel) {
            $warnings[] = "Laravel major differs: backup from {$backupLaravel}.x, running {$currentLaravel}.x — verify after restore.";
        }

        // ── Hemdox Blog Kit version ──────────────────────────────────
        $backupKit = (string) ($manifest['app']['blogkit_version'] ?? $manifest['app']['shopkit_version'] ?? '0.0.0');
        $currentKit = (string) config('blogkit.version', '0.0.0');

        if (version_compare($backupKit, $currentKit, '>')) {
            $errors[] = "Backup is from Hemdox Blog Kit {$backupKit} but this code is {$currentKit} — restoring newer data into older code is unsafe. Update the code first.";
        } elseif (version_compare($backupKit, $currentKit, '<')) {
            $notes[] = "Backup from Hemdox Blog Kit {$backupKit} → current {$currentKit}: pending migrations will run automatically after restore.";
        }

        // ── Database ─────────────────────────────────────────────────
        $backupDriver = (string) ($manifest['database']['driver'] ?? '');
        $currentDriver = (string) config('database.default');

        if ($backupDriver !== '' && $backupDriver !== $currentDriver) {
            $errors[] = "Database driver mismatch: backup is {$backupDriver}, this site uses {$currentDriver}. Dumps are not cross-driver portable.";
        }

        $backupDbMajor = (int) explode('.', (string) ($manifest['database']['server_version'] ?? '0'))[0];
        $currentDbMajor = (int) explode('.', self::currentServerVersion())[0];

        if ($backupDbMajor > 0 && $currentDbMajor > 0 && $currentDbMajor < $backupDbMajor) {
            $warnings[] = "Database server older than the backup's (backup: v{$backupDbMajor}.x, here: v{$currentDbMajor}.x) — some dump features may not import.";
        }

        if ($currentDriver === 'mysql' && ! self::mysqlClientAvailable()) {
            $errors[] = 'The "mysql" client binary is not available on this server — required to import the dump. Install mysql-client.';
        }

        // ── Migration lineage ────────────────────────────────────────
        $backupMigrations = (array) ($manifest['database']['migrations'] ?? []);
        $codeMigrations = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn ($f) => basename($f, '.php'))
            ->all();

        $unknown = array_values(array_diff($backupMigrations, $codeMigrations));

        if ($unknown !== []) {
            $errors[] = 'Backup contains '.count($unknown).' migration(s) this codebase does not have (e.g. "'.$unknown[0].'") — it was made on NEWER code. Update the code before restoring.';
        }

        $pending = array_values(array_diff($codeMigrations, $backupMigrations));

        if ($pending !== [] && $backupMigrations !== []) {
            $notes[] = count($pending).' newer migration(s) in this codebase will run automatically after restore (e.g. "'.$pending[0].'").';
        }

        // ── APP_KEY ──────────────────────────────────────────────────
        $backupKeyFp = (string) ($manifest['app_key_fingerprint'] ?? '');
        $currentKeyFp = substr(sha1((string) config('app.key')), 0, 12);

        if ($backupKeyFp !== '' && ! hash_equals($backupKeyFp, $currentKeyFp)) {
            $warnings[] = 'APP_KEY differs from the backup\'s — encrypted values (e.g. 2FA secrets) will NOT decrypt. Copy the original APP_KEY into .env before restoring if you need them.';
        }

        $zip->close();

        return self::result($errors === [], $legacy, $errors, $warnings, $notes, $manifest);
    }

    protected static function majorMinor(string $version): string
    {
        $parts = explode('.', $version);

        return ($parts[0] ?? '0').'.'.($parts[1] ?? '0');
    }

    protected static function currentServerVersion(): string
    {
        try {
            return (string) DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            return '0';
        }
    }

    protected static function mysqlClientAvailable(): bool
    {
        $process = \Symfony\Component\Process\Process::fromShellCommandline('command -v mysql');
        $process->run();

        return $process->isSuccessful();
    }

    protected static function result(bool $ok, bool $legacy, array $errors, array $warnings, array $notes, ?array $manifest): object
    {
        return (object) compact('ok', 'legacy', 'errors', 'warnings', 'notes', 'manifest');
    }
}
