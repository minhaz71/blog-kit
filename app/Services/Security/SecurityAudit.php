<?php

namespace App\Services\Security;

use App\Models\FileHash;
use App\Models\FileScanResult;
use App\Models\ThreatIntelIp;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Site security posture audit — the "security audit & recommendations" a
 * Wordfence analyst would run, automated. Produces a 0-100 hardening score
 * plus pass/fail checks with fix guidance. Read-only; safe to run anytime.
 */
class SecurityAudit
{
    /**
     * @return array{score:int, grade:string, passed:int, total:int, checks:array<int,array>}
     */
    public function run(): array
    {
        $checks = [
            $this->check('https', 'HTTPS enforced', app()->environment('production')
                ? str_starts_with((string) config('app.url'), 'https://')
                : true, 'critical', 'Set APP_URL to https:// and force TLS at the server/load balancer.'),

            $this->check('debug', 'Debug mode off in production', ! (app()->environment('production') && config('app.debug')),
                'critical', 'Set APP_DEBUG=false in .env on production — debug pages leak secrets and stack traces.'),

            $this->check('app_key', 'Application key set', filled(config('app.key')),
                'critical', 'Run php artisan key:generate — without it sessions and encryption are insecure.'),

            $this->check('firewall', 'Application firewall enabled', (bool) setting('security.firewall_enabled', true),
                'high', 'Enable the firewall in Security settings.'),

            // Customer passwords are intentionally simple (min 6, owner rule);
            // staff passwords are enforced at 10+ in StaffUserResource — a
            // code-level guarantee, so there is nothing to audit here anymore.
            $this->check('login_throttle', 'Login lockout configured', (int) setting('security.max_login_attempts', 5) > 0,
                'high', 'Set a max login-attempt threshold in Security settings.'),

            $this->check('two_factor_available', 'Two-factor authentication enabled', (bool) setting('security.two_factor_enabled', false),
                'high', 'Turn on two-factor authentication for staff accounts.'),

            $this->check('admin_2fa', 'All Super Admins use 2FA', $this->allAdminsHave2fa(),
                'high', 'Have every Super Admin set up an authenticator app in their profile.'),

            $this->check('default_admin', 'No default admin@example.com account', ! User::where('email', 'admin@example.com')->exists(),
                'high', 'Rename/replace the seeded admin@example.com account with a real address.'),

            $this->check('recaptcha', 'reCAPTCHA on public forms', (bool) setting('security.recaptcha_enabled', false),
                'medium', 'Enable reCAPTCHA to stop automated login/registration abuse.'),

            $this->check('threat_feed', 'Threat-intel blocklist fresh (<7 days)', $this->threatFeedFresh(),
                'medium', 'Run security:update-blocklist (scheduled daily) so the IP blocklist stays current.'),

            $this->check('malware_scan', 'Malware scan run recently (<7 days)', $this->malwareScanFresh(),
                'medium', 'Run a malware scan from the Security Center (scheduled daily).'),

            $this->check('integrity_baseline', 'File-integrity baseline exists', FileHash::exists(),
                'medium', 'Build the file-integrity baseline so tampering is detected.'),

            $this->check('unresolved_issues', 'No unresolved malware findings', ! FileScanResult::where('is_resolved', false)->whereIn('severity', ['high', 'critical'])->exists(),
                'critical', 'Resolve the high/critical findings in the file scanner.'),

            $this->check('alerts', 'Intrusion alert emails enabled', (bool) setting('security.alerts_enabled', true),
                'medium', 'Enable intrusion-alert emails so you hear about attacks in real time.'),

            $this->check('backups', 'A recent backup exists (<7 days)', $this->recentBackup(),
                'high', 'Take (and schedule) backups — see the Backups page.'),
        ];

        $weights = ['critical' => 4, 'high' => 3, 'medium' => 2, 'info' => 1];
        $max = 0;
        $earned = 0;

        foreach ($checks as $c) {
            $w = $weights[$c['severity']] ?? 1;
            $max += $w;
            if ($c['passed']) {
                $earned += $w;
            }
        }

        $score = $max > 0 ? (int) round($earned / $max * 100) : 100;

        return [
            'score' => $score,
            'grade' => $this->grade($score),
            'passed' => count(array_filter($checks, fn ($c) => $c['passed'])),
            'total' => count($checks),
            'checks' => $checks,
        ];
    }

    protected function check(string $key, string $label, bool $passed, string $severity, string $fix): array
    {
        return compact('key', 'label', 'passed', 'severity', 'fix');
    }

    protected function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 65 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };
    }

    protected function allAdminsHave2fa(): bool
    {
        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->get();

        return $admins->isNotEmpty() && $admins->every(fn ($u) => filled($u->two_factor_confirmed_at));
    }

    protected function threatFeedFresh(): bool
    {
        return $this->isFresh(ThreatIntelIp::max('last_seen_at'));
    }

    protected function malwareScanFresh(): bool
    {
        $latest = FileScanResult::max('scanned_at');
        // A scan with zero findings leaves no rows; the scan command stamps
        // the last-run time in cache as a fallback.
        $cached = Cache::get('security.last_malware_scan');

        $ts = $latest ?: $cached;

        return $this->isFresh($ts);
    }

    /** Safe freshness test: only parses real date strings/timestamps. */
    protected function isFresh(mixed $value, int $days = 7): bool
    {
        if (! is_string($value) && ! is_int($value) && ! $value instanceof \DateTimeInterface) {
            return false; // null, or a cache value that rehydrated oddly
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->gt(now()->subDays($days));
        } catch (\Throwable) {
            return false;
        }
    }

    protected function recentBackup(): bool
    {
        try {
            return $this->isFresh(\App\Models\Backup::where('status', 'completed')->max('created_at'));
        } catch (\Throwable) {
            return false;
        }
    }
}
