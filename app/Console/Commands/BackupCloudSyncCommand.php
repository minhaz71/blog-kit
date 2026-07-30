<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Ships local backup archives to an off-machine cloud remote via rclone,
 * then enforces remote-side retention (deletes cloud copies older than the
 * window). This is what makes "daily Google Drive backup + auto-remove old"
 * actually automatic — the daily scheduler calls it after backup:run.
 *
 * No-ops safely when no remote is configured (dev machines) or rclone is
 * not installed, so it never breaks the schedule.
 *
 * Config (config/shopkit.php → backup):
 *   remote        e.g. "gdrive:TereaHub-Backups"  (BACKUP_CLOUD_REMOTE)
 *   retain_days   delete cloud files older than N days (BACKUP_CLOUD_RETAIN_DAYS, default 30)
 */
class BackupCloudSyncCommand extends Command
{
    protected $signature = 'backup:cloud-sync
        {--remote= : rclone remote:path (overrides config)}
        {--retain-days= : delete cloud files older than N days (overrides config)}
        {--mirror : use rclone sync (mirror) instead of copy — also removes cloud files deleted locally}';

    protected $description = 'Upload local backups to a cloud remote (rclone) and prune old cloud copies.';

    public function handle(): int
    {
        $remote = (string) ($this->option('remote') ?: config('blogkit.backup.remote'));

        if ($remote === '') {
            $this->info('No cloud remote configured (BACKUP_CLOUD_REMOTE). Skipping cloud sync.');

            return self::SUCCESS;
        }

        if (! $this->rcloneAvailable()) {
            $this->warn('rclone is not installed or not on PATH. Skipping cloud sync. See docs/BACKUPS.md.');

            return self::SUCCESS; // never fail the schedule over a missing optional tool
        }

        $localDir = storage_path('app/backups');
        if (! is_dir($localDir)) {
            $this->info('No local backups directory yet — nothing to upload.');

            return self::SUCCESS;
        }

        $verb = $this->option('mirror') ? 'sync' : 'copy';

        // 1. Upload. copy = additive (safe); sync = mirror (removes remote
        //    files not present locally, so local prune propagates up).
        $this->line("Uploading local backups to {$remote} (rclone {$verb})…");
        $upload = $this->rclone([$verb, $localDir, $remote, '--transfers', '4', '--checkers', '8']);

        if (! $upload->isSuccessful()) {
            $this->error('rclone upload failed: '.trim($upload->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info('Upload complete.');

        // 2. Remote-side retention: delete cloud files older than the window.
        //    (With --mirror this is usually redundant, but it also cleans up
        //    remotes that were populated before mirror mode was enabled.)
        $retainDays = (int) ($this->option('retain-days') ?: config('blogkit.backup.retain_days', 30));

        if ($retainDays > 0) {
            $this->line("Pruning cloud backups older than {$retainDays} days…");
            $prune = $this->rclone(['delete', $remote, '--min-age', "{$retainDays}d"]);

            if ($prune->isSuccessful()) {
                $this->info("Cloud retention applied (kept last {$retainDays} days).");
            } else {
                // Upload already succeeded — a prune hiccup is not fatal.
                $this->warn('Cloud prune reported an issue: '.trim($prune->getErrorOutput()));
            }
        }

        return self::SUCCESS;
    }

    protected function rcloneAvailable(): bool
    {
        $probe = new Process(['rclone', 'version']);
        $probe->run();

        return $probe->isSuccessful();
    }

    /** @param array<int, string> $args */
    protected function rclone(array $args): Process
    {
        $process = new Process(array_merge(['rclone'], $args));
        $process->setTimeout(1800); // large archives over a slow uplink

        $process->run(function ($type, $buffer) {
            // Stream rclone progress/errors into the command output/log.
            $this->getOutput()->write($buffer);
        });

        return $process;
    }
}
