<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;

class BackupPruneCommand extends Command
{
    protected $signature = 'backup:prune {--keep-days=30 : Delete local backups older than this many days}';

    protected $description = 'Delete local backup archives older than the retention window (keeps disk usage bounded).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('keep-days'));
        $cutoff = now()->subDays($days);
        $deleted = 0;

        foreach (Backup::where('created_at', '<', $cutoff)->get() as $backup) {
            $full = storage_path('app/'.$backup->path);
            if (is_file($full)) {
                @unlink($full);
            }
            $backup->delete();
            $deleted++;
        }

        // Also sweep orphaned files with no DB row (e.g. from failed runs).
        foreach (glob(storage_path('app/backups/backup-*.zip')) ?: [] as $file) {
            if (filemtime($file) < $cutoff->timestamp) {
                @unlink($file);
                $deleted++;
            }
        }

        $this->info("Pruned {$deleted} backup(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
