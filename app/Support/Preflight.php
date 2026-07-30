<?php

namespace App\Support;

/**
 * Production-readiness checks, surfaced both by `blogkit:preflight` and the
 * admin Updates page, and run as gate step 1 of `blogkit:update`. Each check
 * is {key, label, ok, severity, fix}. "critical" failures should block an
 * update / going live; "warning" ones are advisories.
 */
class Preflight
{
    /** @return array<int, array{key:string,label:string,ok:bool,severity:string,detail:string}> */
    public static function checks(): array
    {
        $prod = app()->environment('production');
        $checks = [];

        $add = function (string $key, string $label, bool $ok, string $severity, string $detail) use (&$checks): void {
            $checks[] = compact('key', 'label', 'ok', 'severity', 'detail');
        };

        // ── Environment ────────────────────────────────────────────────
        $add('app_env', 'APP_ENV is production', $prod, 'critical',
            $prod ? 'Running in production mode.' : 'Set APP_ENV=production on the live server so prod hardening applies.');

        $add('app_debug', 'Debug mode off', ! config('app.debug'), 'critical',
            config('app.debug') ? 'APP_DEBUG=true leaks stack traces, env values and SQL. Set APP_DEBUG=false.' : 'Debug is off.');

        $add('https', 'HTTPS URL configured', str_starts_with((string) config('app.url'), 'https://'), $prod ? 'critical' : 'warning',
            'APP_URL should be https:// in production so links, cookies and canonicals are secure.');

        $add('log_level', 'Log level not debug', config('logging.channels.single.level', 'debug') !== 'debug' || ! $prod, 'warning',
            'LOG_LEVEL=debug can capture sensitive data in production logs; use error or warning.');

        // ── Database safety ────────────────────────────────────────────
        $dbUser = (string) config('database.connections.'.config('database.default').'.username');
        $add('db_user', 'Database user is not root', $dbUser !== 'root', $prod ? 'critical' : 'warning',
            $dbUser === 'root' ? 'Use a dedicated least-privilege DB user, not root.' : 'Dedicated DB user in use.');

        $unsafeWipe = env('BLOGKIT_ALLOW_UNSAFE_WIPE', env('SHOPKIT_ALLOW_UNSAFE_WIPE'));
        $allowDestructive = env('BLOGKIT_ALLOW_DESTRUCTIVE', env('SHOPKIT_ALLOW_DESTRUCTIVE'));
        $add('unsafe_wipe', 'Unsafe-wipe override is off', ! $unsafeWipe && ! $allowDestructive, 'critical',
            'BLOGKIT_ALLOW_UNSAFE_WIPE / BLOGKIT_ALLOW_DESTRUCTIVE bypass data-loss protection. Unset them in production.');

        // ── Backups (data safety before any update) ────────────────────
        $backupsWritable = is_writable(storage_path('app')) || is_dir(storage_path('app/backups')) && is_writable(storage_path('app/backups'));
        $add('backups_writable', 'Backup directory writable', $backupsWritable, 'critical',
            'storage/app/backups must be writable — the updater takes a mandatory backup first.');

        $add('cloud_backup', 'Off-machine backup configured', (bool) config('blogkit.backup.remote'), 'warning',
            'Set BACKUP_CLOUD_REMOTE so daily backups also go to the cloud (see docs/BACKUPS.md).');

        // ── App integrity ──────────────────────────────────────────────
        $add('app_key', 'Application key set', (string) config('app.key') !== '', 'critical',
            'APP_KEY must be set (php artisan key:generate) — it encrypts sessions and cookies.');

        // ── Update toolchain ───────────────────────────────────────────
        $add('git_repo', 'Git repository present', Version::isGitRepo(), 'warning',
            Version::isGitRepo() ? 'On '.(Version::gitBranch() ?? '?').' @ '.(Version::gitCommit() ?? '?') : 'Not a git checkout — the one-click updater needs the site deployed via git.');

        return $checks;
    }

    /** @return array{ok:bool, critical:int, warnings:int, checks:array} */
    public static function summary(): array
    {
        $checks = self::checks();
        $criticalFailures = array_filter($checks, fn ($c) => ! $c['ok'] && $c['severity'] === 'critical');
        $warnings = array_filter($checks, fn ($c) => ! $c['ok'] && $c['severity'] === 'warning');

        return [
            'ok' => $criticalFailures === [],
            'critical' => count($criticalFailures),
            'warnings' => count($warnings),
            'checks' => $checks,
        ];
    }
}
