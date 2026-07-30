<?php

namespace App\Support;

use App\Models\Backup;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Fail-safe against catastrophic data loss.
 *
 * Any Artisan command that wipes or rewinds the schema — typed by a human, run
 * by CI, or invoked by an AI agent — is intercepted BEFORE it runs. The rules
 * (strongest first):
 *
 *   1. Disposable DB (in-memory SQLite the test suite spins up) → allowed;
 *      there is nothing to protect. This is decided by the ACTUAL connection,
 *      never the "testing" env flag (a cached config can point a "test" run at
 *      real MySQL — which is how local dev data once got wiped).
 *   2. Real database, WITHOUT an explicit opt-in → HARD BLOCKED in EVERY
 *      environment. Data is never wiped by accident, anywhere. Deleting real
 *      records is only ever done deliberately through the app (admin panel),
 *      not by a schema-wiping CLI command.
 *   3. Real database, WITH the explicit opt-in (SHOPKIT_ALLOW_DESTRUCTIVE=1 for
 *      this one run) → still requires a recoverable backup from the last 24h;
 *      if none exists one is taken first, and if that backup cannot be produced
 *      the command is ABORTED. A wipe never proceeds without a fresh snapshot.
 */
class DatabaseSafetyGuard
{
    /** Commands that destroy or rewind data. */
    public const DESTRUCTIVE = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    /** How recent a backup must be before a destructive command may proceed. */
    public const BACKUP_MAX_AGE_HOURS = 24;

    /**
     * Pure decision (testable without the event/test-runner short-circuit):
     * a destructive command is hard-blocked on a real database unless the
     * operator has explicitly opted in for this run. Blocks in EVERY
     * environment — never only production.
     */
    public static function mustHardBlock(string $command, bool $override): bool
    {
        return in_array($command, self::DESTRUCTIVE, true) && ! $override;
    }

    public static function hardBlockMessage(string $command): string
    {
        return "Refused \"{$command}\": it erases or rewinds the database, and this store never "
            .'wipes data automatically. Normal updates only ever run additive `migrate`. Delete '
            .'records deliberately through the admin panel instead. If you truly must run this, set '
            .'SHOPKIT_ALLOW_DESTRUCTIVE=1 for this one command (a <24h backup is still required), '
            .'then unset it immediately.';
    }

    /**
     * A disposable database is the in-memory SQLite the test suite spins up —
     * the only case where a destructive command needs no protection. Anything
     * else (MySQL, or an on-disk SQLite file) is treated as real data.
     */
    public static function isDisposableDatabase(): bool
    {
        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            return false;
        }

        $database = config("database.connections.{$connection}.database");

        return in_array($database, [':memory:', ''], true);
    }

    /** Is there a completed backup (DB row OR archive on disk) within $hours? */
    public static function recentBackupExists(int $hours): bool
    {
        $cutoff = now()->subHours($hours);

        // Backup record in the database.
        try {
            if (Backup::query()->where('status', 'completed')->where('created_at', '>=', $cutoff)->exists()) {
                return true;
            }
        } catch (\Throwable) {
            // No backups table yet / DB unavailable — fall through to disk.
        }

        // Archive on disk (survives even if the DB record is about to be wiped).
        $dir = storage_path('app/backups');
        if (is_dir($dir)) {
            foreach (glob($dir.'/*.zip') ?: [] as $file) {
                if (@filemtime($file) >= $cutoff->getTimestamp()) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function register(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, self::DESTRUCTIVE, true)) {
                return;
            }

            // Rule 1: disposable in-memory test DB — nothing to protect.
            if (self::isDisposableDatabase()) {
                return;
            }

            $out = $event->output;
            $override = (bool) env('BLOGKIT_ALLOW_DESTRUCTIVE', env('SHOPKIT_ALLOW_DESTRUCTIVE'));

            // Rule 2: never wipe a real database by accident — block everywhere
            // unless the operator has deliberately opted in for this run.
            if (self::mustHardBlock($event->command, $override)) {
                throw new RuntimeException(self::hardBlockMessage($event->command));
            }

            // Rule 3: opted in — guarantee a recoverable backup from the last
            // 24h. Take one now if none exists; abort if it can't be produced.
            if (self::recentBackupExists(self::BACKUP_MAX_AGE_HOURS)) {
                $out?->writeln('<info>🛡  Recent backup found (<'.self::BACKUP_MAX_AGE_HOURS.'h) — proceeding with "'.$event->command.'".</info>');

                return;
            }

            $out?->writeln("<info>🛡  Safety guard: no backup in the last ".self::BACKUP_MAX_AGE_HOURS."h — taking one before \"{$event->command}\"…</info>");

            try {
                $exit = Artisan::call('backup:run', ['--type' => 'database'], $out);
            } catch (\Throwable $e) {
                $exit = 1;
                $out?->writeln('<error>'.$e->getMessage().'</error>');
            }

            if ($exit !== 0) {
                throw new RuntimeException(
                    "Aborted \"{$event->command}\": the pre-wipe backup failed, so the database was NOT touched. "
                    .'Fix the backup (check mysqldump + that storage/app/backups is writable), then retry.'
                );
            }

            $out?->writeln('<info>✅ Backup complete — safe to proceed. Restore with: php artisan backup:restore --latest</info>');
        });
    }
}
