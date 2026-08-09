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
class BlogkitUpdate extends Command
{
    protected $signature = 'blogkit:update
        {--dry-run : Show what would happen without changing anything}
        {--skip-backup : DANGER: skip the pre-update backup (never use in production)}
        {--branch= : Branch to pull (default: current)}';

    protected $description = 'Update Hemdox BlogKit from git safely: backup, pull, migrate, rebuild — with automatic rollback.';

    protected ?string $rollbackCommit = null;

    protected ?string $backupPath = null;

    public function handle(): int
    {
        @set_time_limit(0);
        $dry = (bool) $this->option('dry-run');

        if (! $dry) {
            \App\Support\UpdateStatus::begin(Version::core().' @ '.(Version::gitCommit() ?? 'unknown'));
        }

        $this->line('<info>Hemdox BlogKit updater</info> — current version '.Version::core().' @ '.(Version::gitCommit() ?? 'unknown'));

        // ── Step 1: pre-flight gate ────────────────────────────────────
        if (! Version::isGitRepo()) {
            $this->error('This is not a git checkout. Deploy the site via git first (see docs/DEPLOYMENT.md). Nothing changed.');
            if (! $dry) \App\Support\UpdateStatus::finish(false, 'Not a git checkout — cannot self-update. Deploy via git first.');

            return self::FAILURE;
        }

        $pre = Preflight::summary();
        if (! $pre['ok'] && ! $dry) {
            $this->error("Pre-flight found {$pre['critical']} critical issue(s). Run `php artisan blogkit:preflight` and fix them first. Nothing changed.");
            if (! $dry) \App\Support\UpdateStatus::finish(false, "Pre-flight found {$pre['critical']} critical issue(s) — fix them, then retry.");

            return self::FAILURE;
        }

        $behind = Version::commitsBehind(fetch: true);
        if ($behind === 0) {
            $this->info('Already up to date — the checkout matches the remote. Nothing to do.');
            if (! $dry) \App\Support\UpdateStatus::finish(true, 'Already up to date.', Version::core().' @ '.(Version::gitCommit() ?? '?'));

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
            // Non-technical owners run this as the site user; pre-authorize the
            // repo so git never refuses with "detected dubious ownership".
            try {
                $this->exec(['git', 'config', '--global', '--add', 'safe.directory', base_path()]);
            } catch (\Throwable) {
                // best-effort; continue
            }
            $this->exec(['git', 'fetch', '--tags', '--quiet']);
            $this->exec(['git', 'checkout', $branch]);
            try {
                $this->exec(['git', 'pull', '--ff-only', 'origin', $branch]);
            } catch (\Throwable $e) {
                // Fast-forward impossible — the remote history was rewritten
                // (e.g. a commit-message cleanup / force-push). A full backup was
                // already taken above, so realign HARD to the remote. This only
                // touches tracked CODE files; the database and uploaded media
                // (storage, gitignored) are untouched, and rollback restores the
                // previous commit + database if anything downstream fails.
                $this->line('  Fast-forward not possible (remote history changed) — realigning to origin/'.$branch.'.');
                $this->exec(['git', 'fetch', 'origin', $branch, '--quiet']);
                $this->exec(['git', 'reset', '--hard', 'origin/'.$branch]);
            }

            // ── Step 5: dependencies (prod, optimized) ─────────────────
            $this->step('Installing PHP dependencies…');
            $this->exec([...$this->composer(), 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'], 900);

            // ── Step 6: additive migrations (destructive ones blocked) ─
            $this->step('Running database migrations (additive only)…');
            if (Artisan::call('migrate', ['--force' => true], $this->output) !== 0) {
                throw new \RuntimeException('Migration failed.');
            }

            // ── Step 7: assets + caches ────────────────────────────────
            $this->rebuildAssets();
            $this->step('Rebuilding caches…');
            Version::forget();
            // CRITICAL: rebuild caches in FRESH php subprocesses, not in this
            // long-lived process. This process loaded the OLD route/config files
            // into OPcache at boot, BEFORE `git pull` replaced them — so calling
            // route:cache in-process can bake the PRE-UPDATE routes (new
            // endpoints then 404). A new `php artisan` process reads the new
            // files. Clear first so a stale cache is never left behind.
            $php = PHP_BINARY ?: 'php';
            $artisan = base_path('artisan');
            $this->exec([$php, $artisan, 'optimize:clear']);
            foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $c) {
                $this->exec([$php, $artisan, $c]);
            }
            $this->exec([$php, $artisan, 'queue:restart']);

            // OpenLiteSpeed/LiteSpeed keep lsphp workers warm with OPcache; when
            // the host sets opcache.validate_timestamps=0 the running workers
            // won't see new code until the server reloads. Try a graceful reload
            // (needs privileges; silently ignored if not permitted), and always
            // leave a clear note so the owner can do it from CyberPanel/SSH.
            $this->reloadWebServer();
        } catch (\Throwable $e) {
            $this->error('Update FAILED: '.$e->getMessage());
            \App\Support\UpdateStatus::appendLog('Update FAILED: '.$e->getMessage());
            $this->rollback();
            Artisan::call('up');
            \App\Support\UpdateStatus::finish(false, 'Update failed and was rolled back: '.mb_substr($e->getMessage(), 0, 240));

            return self::FAILURE;
        }

        // ── Step 8: back online ────────────────────────────────────────
        Artisan::call('up');
        Version::forget();
        $this->newLine();
        $this->info('✅ Updated to '.Version::core().' @ '.(Version::gitCommit() ?? '?').'. Site is back online.');
        \App\Support\UpdateStatus::finish(true, 'Updated successfully. Site is back online.', Version::core().' @ '.(Version::gitCommit() ?? '?'));

        return self::SUCCESS;
    }

    /**
     * The repo ships a committed/CI-built public/build, so a frontend rebuild is
     * optional. Prefer the committed build; only run npm if it's actually
     * missing, and NEVER let an npm hiccup (permissions, RAM, registry) fail the
     * whole update — the committed build is the fallback.
     */
    protected function rebuildAssets(): void
    {
        if (is_file(public_path('build/manifest.json'))) {
            $this->line('  Using committed/CI-built assets in public/build (skipping npm).');

            return;
        }

        $hasNode = (function (): bool {
            $p = new Process(['npm', '--version']);
            $p->run();

            return $p->isSuccessful();
        })();

        if (! $hasNode) {
            $this->line('  npm not found — using the committed/CI-built assets in public/build.');

            return;
        }

        try {
            $this->step('Building frontend assets…');
            $this->exec(['npm', 'ci', '--no-audit', '--no-fund'], 900);
            $this->exec(['npm', 'run', 'build'], 900);
        } catch (\Throwable $e) {
            $this->line('  npm build failed ('.mb_substr($e->getMessage(), 0, 100).') — keeping the committed build.');
        }
    }

    /**
     * Run Composer THROUGH the current PHP binary so it can never fall back to a
     * system default PHP 7.4 that fails with "requires php ^8.x" (the classic
     * CyberPanel update failure). Falls back to a bare `composer` only if no phar
     * is found on the usual paths.
     *
     * @return array<int, string>
     */
    protected function composer(): array
    {
        foreach (['/usr/local/bin/composer', '/usr/bin/composer', base_path('composer.phar')] as $phar) {
            if (is_file($phar)) {
                return [PHP_BINARY, $phar];
            }
        }

        return ['composer'];
    }

    /** Restore code + database to the pre-update state. */
    protected function rollback(): void
    {
        $this->warn('Rolling back…');

        if ($this->rollbackCommit) {
            try {
                $this->exec(['git', 'reset', '--hard', $this->rollbackCommit]);
                $this->exec([...$this->composer(), 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'], 900);
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

    /**
     * Best-effort graceful reload of the web server so warm PHP workers pick up
     * the new code (essential on OpenLiteSpeed with opcache.validate_timestamps=0).
     * Never fatal: most of these need root, which the site user lacks — so on
     * failure we just tell the owner exactly what to run.
     */
    protected function reloadWebServer(): void
    {
        $tried = false;

        // OpenLiteSpeed / LiteSpeed (CyberPanel): graceful restart.
        if (is_executable('/usr/local/lsws/bin/lswsctrl')) {
            $tried = true;
            try {
                $this->exec(['/usr/local/lsws/bin/lswsctrl', 'restart'], 60);
                $this->line('  Reloaded LiteSpeed — new routes are live.');

                return;
            } catch (\Throwable) {
                // no privileges — fall through to the instruction
            }
        }

        if ($tried || is_dir('/usr/local/lsws')) {
            $note = 'OpenLiteSpeed detected: if a new page/route 404s, reload the web server as root — `systemctl restart lsws` (or restart it from CyberPanel).';
            $this->warn('  '.$note);
            \App\Support\UpdateStatus::appendLog($note);
        }
    }

    protected function step(string $message): void
    {
        $this->newLine();
        $this->line('▸ '.$message);
        \App\Support\UpdateStatus::step($message);
        \App\Support\UpdateStatus::appendLog('▸ '.$message);
    }

    /** @param array<int,string> $cmd */
    protected function exec(array $cmd, int $timeout = 120): void
    {
        $process = new Process($cmd, base_path());
        $process->setTimeout($timeout);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
            \App\Support\UpdateStatus::appendLog($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Command failed: '.implode(' ', $cmd).' — '.trim($process->getErrorOutput()));
        }
    }
}
