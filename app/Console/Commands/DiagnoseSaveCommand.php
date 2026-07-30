<?php

namespace App\Console\Commands;

use App\Models\ErrorLog;
use App\Models\FirewallLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The "why won't my product save?" gate. Runs every check that has ever
 * broken an admin save on this stack (cache/session file permissions after a
 * root-owned deploy, disk full, firewall blocks on Livewire endpoints,
 * maintenance mode, upload limits) and prints PASS/FAIL with the fix.
 * Read-only apart from harmless probe files it deletes again.
 */
class DiagnoseSaveCommand extends Command
{
    protected $signature = 'blogkit:diagnose-save';

    protected $description = 'Diagnose why admin saves fail or hang: storage permissions, cache/session/DB writes, firewall blocks, maintenance mode, upload limits, recent errors.';

    private int $failures = 0;

    public function handle(): int
    {
        $this->line(sprintf(
            'Running as <info>%s</info> · PHP %s · APP_ENV=%s · cache=%s · session=%s',
            function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : get_current_user(),
            PHP_VERSION,
            config('app.env'),
            config('cache.default'),
            config('session.driver'),
        ));
        $this->newLine();

        // ── 1. Disk space (classic silent killer) ────────────────────
        $free = @disk_free_space(storage_path());
        $this->check(
            'Disk space',
            $free !== false && $free > 100 * 1024 * 1024,
            $free === false ? 'could not read' : $this->bytes($free) . ' free',
            'Free up disk space — writes fail silently when the disk is full.'
        );

        // ── 2. Writable-directory probes (REAL writes, not perms flags) ──
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            storage_path('app/public'),
        ] as $dir) {
            $label = 'Write ' . str_replace(base_path() . '/', '', $dir);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
                $this->check($label, false, 'directory missing and cannot be created', $this->permFix($dir));
                continue;
            }
            // Same shape as Laravel's file cache: a nested subdir + file.
            $probeDir = $dir . '/_probe_' . substr(md5((string) mt_rand()), 0, 6);
            $ok = @mkdir($probeDir, 0775, true) && @file_put_contents($probeDir . '/probe.tmp', 'ok') !== false;
            @unlink($probeDir . '/probe.tmp');
            @rmdir($probeDir);
            $this->check($label, $ok, $ok ? 'writable (probe file created)' : 'CANNOT create subdir/file', $this->permFix($dir));
        }

        // ── 3. Cache store round-trip (what PageCache::flush does on save) ──
        try {
            Cache::put('diagnose.probe', 'ok', 30);
            $ok = Cache::get('diagnose.probe') === 'ok';
            Cache::forget('diagnose.probe');
            $this->check('Cache store round-trip', $ok, $ok ? 'put/get/forget fine' : 'value did not persist', 'Check CACHE_STORE and storage/framework/cache permissions.');
        } catch (\Throwable $e) {
            $this->check('Cache store round-trip', false, $e->getMessage(), 'This exact failure 500s every product save on old builds — fix permissions AND deploy the PageCache hardening commit.');
        }

        // ── 4. Database write round-trip ─────────────────────────────
        try {
            DB::statement('CREATE TABLE IF NOT EXISTS _diagnose_probe (id INT)');
            DB::statement('DROP TABLE _diagnose_probe');
            $this->check('Database write', true, 'create/drop table fine');
        } catch (\Throwable $e) {
            $this->check('Database write', false, $e->getMessage(), 'DB user lacks write/DDL rights or the DB is down/full.');
        }

        // ── 5. Maintenance mode + discourage indexing ────────────────
        $maintenance = (bool) setting('general.maintenance_mode', false);
        $this->check('Maintenance mode', ! $maintenance, $maintenance ? 'ON — front blocked (admin should still work)' : 'off', 'Turn it off: php artisan blogkit:maintenance off');

        // ── 6. Firewall blocks on admin/Livewire paths (last 48h) ────
        try {
            $blocks = FirewallLog::query()
                ->where('created_at', '>=', now()->subHours(48))
                ->where(function ($q) {
                    $q->where('url', 'like', '%livewire%')
                        ->orWhere('url', 'like', '%admin%');
                })
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['ip_address', 'url', 'rule', 'created_at']);

            // Scanner probes for fake admin files (/admin_phpinfo.php,
            // /admin/utils.js) are the firewall WORKING, not your saves being
            // blocked. Only genuine panel endpoints count as a save problem.
            $genuine = $blocks->filter(function ($b) {
                $path = strtolower((string) parse_url((string) $b->url, PHP_URL_PATH));

                return (str_contains($path, 'livewire') || str_starts_with(ltrim($path, '/'), 'admin/') || ltrim($path, '/') === 'admin')
                    && ! preg_match('/\.(php|js|css|map|txt|env|sql|zip|bak)$/', $path)
                    && ! str_contains($path, 'phpinfo');
            });
            $this->check(
                'Firewall vs admin/Livewire',
                $genuine->isEmpty(),
                $genuine->isEmpty()
                    ? ($blocks->isEmpty() ? 'no admin/livewire blocks in 48h' : 'only scanner-bot probes blocked (normal, firewall working)')
                    : $genuine->count() . ' genuine panel request(s) blocked — THIS can be the spinner',
                'Your own save requests are being firewalled. Whitelist your IP / review Security settings.'
            );
            foreach ($genuine->take(5) as $b) {
                $this->line("      ⚠ {$b->created_at} {$b->ip_address} {$b->url} (rule: {$b->rule})");
            }
        } catch (\Throwable $e) {
            $this->check('Firewall vs admin/Livewire', false, 'could not read firewall_logs: ' . $e->getMessage());
        }

        // ── 7. Upload/PHP limits (image edits die on these mid-save) ─
        foreach (['post_max_size', 'upload_max_filesize', 'max_execution_time', 'memory_limit'] as $key) {
            $this->line(sprintf('      %s = %s', $key, ini_get($key)));
        }

        // ── 8. Recent recorded errors (the actual exception, if code-side) ──
        $this->newLine();
        $this->line('<comment>Last 5 recorded application errors (error_logs table):</comment>');
        try {
            foreach (ErrorLog::query()->orderByDesc('last_seen_at')->limit(5)->get() as $err) {
                $this->line(sprintf('  [%s ×%d] %s', $err->last_seen_at, $err->occurrences ?? 1, mb_substr((string) $err->message, 0, 160)));
            }
        } catch (\Throwable) {
            $this->line('  (error_logs table unavailable)');
        }

        $log = storage_path('logs/laravel.log');
        if (is_file($log)) {
            $tail = array_filter(array_slice(file($log) ?: [], -400), fn ($l) => str_contains($l, '.ERROR'));
            $this->line('<comment>Last ERROR lines in laravel.log:</comment>');
            foreach (array_slice($tail, -5) as $line) {
                $this->line('  ' . mb_substr(trim($line), 0, 180));
            }
        }

        $this->newLine();
        if ($this->failures === 0) {
            $this->info('All server-side checks PASSED. If saves still hang, the failure is in the browser request: open DevTools → Network, click Save, and read the status of the /livewire/update call (403 = firewall/session, 419 = expired CSRF/session, 500 = check the errors above, pending forever = a proxy/Cloudflare timeout).');
        } else {
            $this->error($this->failures . ' check(s) FAILED — fix those first; each one above includes the fix.');
        }

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $label, bool $ok, string $detail = '', string $fix = ''): void
    {
        if ($ok) {
            $this->line("  <info>PASS</info>  {$label}" . ($detail !== '' ? " — {$detail}" : ''));
        } else {
            $this->failures++;
            $this->line("  <error>FAIL</error>  {$label}" . ($detail !== '' ? " — {$detail}" : ''));
            if ($fix !== '') {
                $this->line("        FIX: {$fix}");
            }
        }
    }

    private function permFix(string $dir): string
    {
        return "chown -R <site-user>:<site-user> {$dir} && find {$dir} -type d -exec chmod 775 {} \\; (run as root; <site-user> = the PHP user shown at the top)";
    }

    private function bytes(float $bytes): string
    {
        return $bytes > 1073741824 ? round($bytes / 1073741824, 1) . ' GB' : round($bytes / 1048576) . ' MB';
    }
}
