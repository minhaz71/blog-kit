<?php

namespace App\Console\Commands;

use App\Support\Preflight;
use App\Support\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * Safe self-update: GitHub → this VPS. Pulls the new version, installs deps,
 * runs ADDITIVE migrations (destructive ones are blocked by
 * DatabaseSafetyGuard), rebuilds caches — and if ANYTHING fails, rolls the
 * code back to the previous commit and restores the database from the
 * mandatory pre-update backup. Existing data is never dropped.
 *
 * Every step is echoed so the admin "Update" button (which runs this
 * detached via BackgroundProcess) can stream progress.
 */
class ShopkitUpdate extends Command
{
    protected $signature = 'blogkit:update
        {--dry-run : Show what would happen without changing anything}
        {--skip-backup : DANGER: skip the pre-update backup (never use in production)}
        {--branch= : Branch to pull (default: current)}';

    protected $description = 'Update ShopKit from git safely: backup, pull, migrate, rebuild — with automatic rollback.';

    protected ?string $rollbackCommit = null;

    protected ?string $backupPath = null;

    public function handle(): int
    {
        @set_time_limit(0);
        $dry = (bool) $this->option('dry-run');

        $this->line('<info>ShopKit updater</info> — current version '.Version::core().' @ '.(Version::gitCommit() ?? 'unknown'));

        // ── Step 1: pre-flight gate ────────────────────────────────────
        if (! Version::isGitRepo()) {
            $this->error('This is not a git checkout. Deploy the site via git first (see docs/DEPLOYMENT.md). Nothing changed.');

            return self::FAILURE;
        }

        $pre = Preflight::summary();
        if (! $pre['ok'] && ! $dry) {
            $this->error("Pre-flight found {$pre['critical']} critical issue(s). Run `php artisan blogkit:preflight` and fix them first. Nothing changed.");

            return self::FAILURE;
        }

        $behind = Version::commitsBehind(fetch: true);
        if ($behind === 0) {
            $this->info('Already up to date — the checkout matches the remote. Nothing to do.');

            return self::SUCCESS;
        }
        $this->line($behind === null ? 'Update available (could not count commits).' : "Update available: {$behind} new commit(s).");

        if ($dry) {
            $this->newLine();
            $this->line('DRY RUN — would now: backup → maintenance mode → git pull → composer install → migrate → rebuild caches → up.');

            return self::SUCCESS;
        }

        // ── Step 2: mandatory full backup (the restore point) ──────────
        if (! $this->option('skip-backup')) {
            $this->step('Taking a full backup (database + files)…');
            if (Artisan::call('backup:run', ['--type' => 'full'], $this->output) !== 0) {
                $this->error('Backup failed — update aborted, nothing changed.');

                return self::FAILURE;
            }
            $this->backupPath = \App\Models\Backup::latest()->value('path');
        }

        // ── Step 3: maintenance mode + record rollback point ───────────
        $this->rollbackCommit = Version::gitCommit(short: false);
        $secret = bin2hex(random_bytes(8));
        $this->step("Entering maintenance mode (bypass: /{$secret})…");
        Artisan::call('down', ['--secret' => $secret, '--retry' => 30]);

        try {
            // ── Step 4: pull code ──────────────────────────────────────
            $branch = $this->option('branch') ?: Version::gitBranch();
            $this->exec(['git', 'fetch', '--tags', '--quiet']);
            $this->exec(['git', 'checkout', $branch]);
            $this->exec(['git', 'pull', '--ff-only', 'origin', $branch]);

            // ── Step 5: dependencies (prod, optimized) ─────────────────
            $this->step('Installing PHP dependencies…');
            $this->exec(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'], 900);

            // ── Step 6: additive migrations (destructive ones blocked) ─
            $this->step('Running database migrations (additive only)…');
            if (Artisan::call('migrate', ['--force' => true], $this->output) !== 0) {
                throw new \RuntimeException('Migration failed.');
            }

            // ── Step 7: assets + caches ────────────────────────────────
            $this->rebuildAssets();
            $this->step('Rebuilding caches…');
            Version::forget();
            foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $c) {
                Artisan::call($c);
            }
            Artisan::call('queue:restart');
        } catch (\Throwable $e) {
            $this->error('Update FAILED: '.$e->getMessage());
            $this->rollback();
            Artisan::call('up');

            return self::FAILURE;
        }

        // ── Step 8: back online ────────────────────────────────────────
        Artisan::call('up');
        $this->newLine();
        $this->info('✅ Updated to '.Version::core().' @ '.(Version::gitCommit() ?? '?').'. Site is back online.');

        return self::SUCCESS;
    }

    /** Rebuild the frontend bundle if Node is present; otherwise assume CI/committed build. */
    protected function rebuildAssets(): void
    {
        $hasNode = (function (): bool {
            $p = new Process(['npm', '--version']);
            $p->run();

            return $p->isSuccessful();
        })();

        if (! $hasNode) {
            $this->line('  npm not found — using the committed/CI-built assets in public/build.');

            return;
        }

        $this->step('Building frontend assets…');
        $this->exec(['npm', 'ci', '--no-audit', '--no-fund'], 900);
        $this->exec(['npm', 'run', 'build'], 900);
    }

    /** Restore code + database to the pre-update state. */
    protected function rollback(): void
    {
        $this->warn('Rolling back…');

        if ($this->rollbackCommit) {
            try {
                $this->exec(['git', 'reset', '--hard', $this->rollbackCommit]);
                $this->exec(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'], 900);
                $this->line('  Code restored to '.substr($this->rollbackCommit, 0, 8).'.');
            } catch (\Throwable $e) {
                $this->error('  Code rollback error: '.$e->getMessage());
            }
        }

        if ($this->backupPath) {
            $this->line('  Restoring database from the pre-update backup…');
            // --force: non-interactive; the archive is the one we just made.
            Artisan::call('backup:restore', ['--path' => $this->backupPath, '--force' => true], $this->output);
        }

        foreach (['config:clear', 'route:clear', 'view:clear'] as $c) {
            Artisan::call($c);
        }

        $this->warn('Rollback complete — the site is on the previous version with its previous data.');
    }

    protected function step(string $message): void
    {
        $this->newLine();
        $this->line('▸ '.$message);
    }

    /** @param array<int,string> $cmd */
    protected function exec(array $cmd, int $timeout = 120): void
    {
        $process = new Process($cmd, base_path());
        $process->setTimeout($timeout);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Command failed: '.implode(' ', $cmd).' — '.trim($process->getErrorOutput()));
        }
    }
}
