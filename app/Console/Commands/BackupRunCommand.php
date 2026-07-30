<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Support\BackupManifest;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Creates a self-describing, portable backup archive:
 *
 *   manifest.json      environment fingerprint + checksums (always included)
 *   database.sql       full schema + data (mysqldump — restores WITHOUT migrations)
 *   storage-public/**  uploaded files (product images, media)     [files/full]
 *   ai-imports/**      AI Product Publisher source CSVs           [files/full]
 *
 * The manifest is what lets backup:restore verify PHP/Laravel/ShopKit/DB
 * compatibility on the target machine BEFORE anything is overwritten.
 */
class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--type=full : one of database, files, full}';

    protected $description = 'Create a portable backup archive (database + files + manifest) in storage/app/backups.';

    public function handle(): int
    {
        $type = $this->option('type');
        if (! in_array($type, ['database', 'files', 'full'], true)) {
            $this->error('Unknown --type. Use one of: database, files, full.');

            return self::FAILURE;
        }

        $stamp = date('Y_m_d_His');
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $backup = Backup::create([
            'type' => $type,
            'path' => "backups/backup-{$type}-{$stamp}.zip",
            'disk' => 'local',
            'status' => 'running',
        ]);

        $sqlPath = null;

        try {
            $zipPath = storage_path('app/'.$backup->path);
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot open zip archive for writing.');
            }

            $withFiles = in_array($type, ['files', 'full'], true);
            $publicDir = storage_path('app/public');
            $aiImportsDir = storage_path('app/private/ai-imports');

            if (in_array($type, ['database', 'full'], true)) {
                $sqlPath = $this->dumpDatabase($stamp);

                if ($sqlPath && is_file($sqlPath)) {
                    $zip->addFile($sqlPath, 'database.sql');
                }
            }

            if ($withFiles) {
                $this->addDirectoryToZip($zip, $publicDir, 'storage-public');
                $this->addDirectoryToZip($zip, $aiImportsDir, 'ai-imports');
            }

            // Manifest goes last so its checksums cover the final dump.
            $manifest = BackupManifest::generate($type, [
                'database' => $sqlPath,
                'storage_public' => $withFiles && is_dir($publicDir),
                'ai_imports' => $withFiles && is_dir($aiImportsDir),
            ]);
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip->close();

            $backup->update([
                'status' => 'completed',
                'size' => file_exists($zipPath) ? filesize($zipPath) : 0,
            ]);
            $this->info("Backup created: {$backup->path} (".number_format($backup->size / 1024, 1).' KB, manifest v'.BackupManifest::FORMAT.')');
        } catch (Throwable $e) {
            $backup->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            // The zip holds its own copy after close(); remove the tmp dump.
            if ($sqlPath && is_file($sqlPath)) {
                @unlink($sqlPath);
            }
        }

        return self::SUCCESS;
    }

    protected function dumpDatabase(string $stamp): ?string
    {
        $driver = config('database.default');
        $connection = config("database.connections.$driver");
        $tmp = storage_path("app/backups/dump-{$stamp}.sql");

        if ($driver === 'sqlite') {
            $src = $connection['database'];
            if (! is_file($src)) {
                return null;
            }
            copy($src, $tmp);

            return $tmp;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // MariaDB's mysqldump rejects the MySQL-only --set-gtid-purged
            // flag ("unknown variable"). Detect the real server so the dump
            // works on both engines (MySQL locally, MariaDB on many hosts).
            $serverVersion = '';
            try {
                $serverVersion = (string) \Illuminate\Support\Facades\DB::connection($driver)
                    ->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } catch (\Throwable) {
                // Fall back to MySQL-compatible flags if the version is unknown.
            }
            $isMariaDb = stripos($serverVersion, 'mariadb') !== false;

            $cmd = array_filter([
                'mysqldump',
                '-h', $connection['host'] ?? '127.0.0.1',
                '-P', (string) ($connection['port'] ?? 3306),
                '-u', (string) ($connection['username'] ?? 'root'),
                ($connection['password'] ?? '') === '' ? '' : '-p'.$connection['password'],
                // Consistent snapshot without locking; portable + restorable on
                // the same server (no GTID clash, no tablespace privilege need).
                '--single-transaction',
                // MySQL-only: MariaDB has no GTID-purged variable and errors on it.
                $isMariaDb ? '' : '--set-gtid-purged=OFF',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                // Schema travels WITH the data: restore never needs migrations.
                '--routines',
                '--triggers',
                '--add-drop-table',
                $connection['database'],
            ], fn ($x) => $x !== '');
            $process = new Process($cmd);
            $process->setTimeout(600);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('mysqldump failed: '.$process->getErrorOutput());
            }
            file_put_contents($tmp, $process->getOutput());

            return $tmp;
        }

        // Unsupported driver — skip DB dump.
        return null;
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($files as $file) {
            if ($file->isFile()) {
                $rel = substr($file->getPathname(), strlen($dir) + 1);
                $zip->addFile($file->getPathname(), "{$prefix}/{$rel}");
            }
        }
    }
}
