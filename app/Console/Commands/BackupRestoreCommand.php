<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Support\BackupCompatibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Full restore with a compatibility gate. Order of operations:
 *
 *   1. COMPATIBILITY CHECK (PHP/Laravel/ShopKit/DB versions, extensions,
 *      migration lineage, archive checksum, APP_KEY) — errors BLOCK the
 *      restore unless --skip-checks is passed.
 *   2. Safety backup of the CURRENT database (so a restore is undoable).
 *   3. Import database.sql — the dump carries the full schema, so NO
 *      migrations are needed to rebuild the site.
 *   4. Restore files (storage-public/**, ai-imports/**) when present.
 *   5. Run any migrations newer than the backup (only when code is ahead).
 *   6. Clear caches + verify restored row counts against the manifest.
 */
class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore
        {--latest : Restore the most recent completed backup}
        {--path= : Restore a specific backup zip (path relative to storage/app, or absolute)}
        {--skip-checks : DANGEROUS — restore even when compatibility checks fail}
        {--no-files : Restore the database only, skip archived files}
        {--no-safety-backup : Skip the automatic pre-restore backup of the current database}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Restore a ShopKit backup archive (database + files) after verifying compatibility.';

    public function handle(): int
    {
        $zipPath = $this->resolveZip();

        if (! $zipPath || ! is_file($zipPath)) {
            $this->error('No backup found to restore. Use --latest or --path=backups/backup-database-XXXX.zip');

            return self::FAILURE;
        }

        $this->line("Archive: <info>{$zipPath}</info>");

        // ── 1. Compatibility gate ────────────────────────────────────
        $check = BackupCompatibility::check($zipPath);
        $this->printReport($check);

        if (! $check->ok && ! $this->option('skip-checks')) {
            $this->error('Restore BLOCKED by compatibility errors above. Fix them, or re-run with --skip-checks if you fully understand the risk.');

            return self::FAILURE;
        }

        if (! $check->ok) {
            $this->warn('⚠  Proceeding despite failed checks (--skip-checks).');
        }

        $this->warn('This will OVERWRITE the current database'.($this->option('no-files') ? '' : ' and archived files').'.');

        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            $this->info('Restore cancelled.');

            return self::SUCCESS;
        }

        // ── 2. Safety backup (make the restore undoable) ─────────────
        if (! $this->option('no-safety-backup')) {
            $this->line('Taking a safety backup of the current database…');

            if (Artisan::call('backup:run', ['--type' => 'database'], $this->output) !== 0) {
                $this->error('Safety backup failed — aborting so nothing is lost. Use --no-safety-backup to override.');

                return self::FAILURE;
            }
        }

        // ── 3+4. Extract and restore ─────────────────────────────────
        $tmpDir = storage_path('app/backups/_restore_'.uniqid());
        mkdir($tmpDir, 0755, true);

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                $this->error('Could not open the backup archive.');

                return self::FAILURE;
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            $sql = $tmpDir.'/database.sql';

            if (is_file($sql)) {
                $this->line('Restoring database (schema + data — no migrations needed)…');

                if (! $this->importSql($sql)) {
                    return self::FAILURE;
                }
            } else {
                $this->warn('Archive holds no database.sql — files-only restore.');
            }

            if (! $this->option('no-files')) {
                $this->restoreFiles($tmpDir.'/storage-public', storage_path('app/public'), 'uploaded files');
                $this->restoreFiles($tmpDir.'/ai-imports', storage_path('app/private/ai-imports'), 'AI import CSVs');
            }
        } finally {
            $this->cleanup($tmpDir);
        }

        // ── 5. Bring schema up when the code is ahead of the backup ──
        $this->line('Applying any migrations newer than the backup…');
        Artisan::call('migrate', ['--force' => true], $this->output);

        // ── 6. Caches + verification ─────────────────────────────────
        Artisan::call('optimize:clear');

        $this->verifyCounts($check->manifest);

        $this->info('✅ Restore complete: '.basename($zipPath));

        return self::SUCCESS;
    }

    protected function importSql(string $sql): bool
    {
        $driver = config('database.default');
        $c = config("database.connections.$driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error("Restore currently supports MySQL/MariaDB only (current: {$driver}).");

            return false;
        }

        $cmd = array_filter([
            'mysql',
            '-h', $c['host'] ?? '127.0.0.1',
            '-P', (string) ($c['port'] ?? 3306),
            '-u', (string) ($c['username'] ?? 'root'),
            ($c['password'] ?? '') === '' ? null : '-p'.$c['password'],
            $c['database'],
        ], fn ($x) => $x !== null);

        $process = Process::fromShellCommandline(
            implode(' ', array_map('escapeshellarg', $cmd)).' < '.escapeshellarg($sql)
        );
        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Database import failed: '.$process->getErrorOutput());

            return false;
        }

        return true;
    }

    protected function restoreFiles(string $from, string $to, string $label): void
    {
        if (! is_dir($from)) {
            return;
        }

        if (! is_dir($to)) {
            mkdir($to, 0755, true);
        }

        $count = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $rel = substr($file->getPathname(), strlen($from) + 1);
            $dest = $to.'/'.$rel;

            if (! is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0755, true);
            }

            copy($file->getPathname(), $dest);
            $count++;
        }

        $this->line("Restored {$count} {$label}.");
    }

    protected function verifyCounts(?array $manifest): void
    {
        $expected = (array) ($manifest['counts'] ?? []);

        if ($expected === []) {
            return;
        }

        $mismatches = [];

        foreach ($expected as $table => $count) {
            if ($count === null) {
                continue;
            }
            try {
                $actual = (int) DB::table($table)->count();
                if ($actual !== (int) $count) {
                    $mismatches[] = "{$table}: expected {$count}, got {$actual}";
                }
            } catch (\Throwable) {
                $mismatches[] = "{$table}: table missing after restore";
            }
        }

        if ($mismatches === []) {
            $this->info('Verified: restored row counts match the manifest ('.implode(', ', array_map(
                fn ($t, $c) => "{$t}={$c}",
                array_keys(array_filter($expected, fn ($c) => $c !== null)),
                array_filter($expected, fn ($c) => $c !== null),
            )).').');
        } else {
            $this->warn('Row-count differences vs manifest (newer migrations can explain small ones): '.implode('; ', $mismatches));
        }
    }

    protected function printReport(object $check): void
    {
        if ($check->manifest) {
            $m = $check->manifest;
            $this->line(sprintf(
                'Backup: Hemdox Blog Kit %s · PHP %s · Laravel %s · %s %s · taken %s',
                $m['app']['blogkit_version'] ?? $m['app']['shopkit_version'] ?? '?',
                $m['php']['version'] ?? '?',
                $m['laravel'] ?? '?',
                $m['database']['driver'] ?? '?',
                $m['database']['server_version'] ?? '',
                $m['created_at'] ?? '?',
            ));
        }

        foreach ($check->errors as $error) {
            $this->error('  ✖ '.$error);
        }
        foreach ($check->warnings as $warning) {
            $this->warn('  ⚠ '.$warning);
        }
        foreach ($check->notes as $note) {
            $this->line('  ℹ '.$note);
        }

        if ($check->ok && $check->errors === [] && $check->warnings === []) {
            $this->info('  ✔ All compatibility checks passed.');
        }
    }

    protected function resolveZip(): ?string
    {
        if ($path = $this->option('path')) {
            return str_starts_with($path, '/') ? $path : storage_path('app/'.ltrim($path, '/'));
        }

        // Default to --latest behaviour: newest completed DB/full backup.
        $backup = Backup::whereIn('type', ['database', 'full'])
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($backup) {
            return storage_path('app/'.$backup->path);
        }

        // Fall back to scanning the folder if the table row is missing.
        $files = glob(storage_path('app/backups/backup-*.zip')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    protected function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($dir);
    }
}
