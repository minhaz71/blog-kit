<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Models\FirewallLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wordfence-style application firewall: blocks known-bad request patterns,
 * blocked IPs/countries/user agents, scanner paths, and repeat offenders.
 */
class Firewall
{
    /** Request paths attackers probe for. Instant block + log. */
    protected array $scannerPaths = [
        'wp-login.php', 'wp-admin', 'wp-config.php', 'xmlrpc.php', 'wp-content',
        '.env', '.env.bak', '.env.local', '.env.production', '.git', '.gitignore',
        '.htpasswd', '.DS_Store', 'phpinfo.php', 'phpmyadmin', 'pma', 'adminer.php',
        'config.php', 'configuration.php', 'shell.php', 'cmd.php', 'eval-stdin.php',
        'vendor/phpunit', 'telescope', '.aws', 'id_rsa', 'backup.sql', 'dump.sql',
    ];

    protected array $sqliPatterns = [
        '/\bunion\b.{0,40}\bselect\b/i',
        '/\bselect\b.{0,40}\bfrom\b.{0,40}\b(information_schema|mysql\.)/i',
        '/\b(sleep|benchmark)\s*\(\s*\d/i',
        '/[\'"]\s*(or|and)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d/i',
        '/;\s*(drop|truncate|alter)\s+(table|database)/i',
        '/\bload_file\s*\(|\binto\s+(out|dump)file\b/i',
    ];

    protected array $xssPatterns = [
        '/<script[\s>]/i',
        '/javascript\s*:/i',
        '/on(error|load|click|mouseover|focus)\s*=/i',
        '/<iframe[\s>]/i',
        '/document\.(cookie|write)/i',
        '/eval\s*\(|atob\s*\(/i',
    ];

    protected array $traversalPatterns = [
        '/\.\.[\/\\\\]/',
        '/%2e%2e[\/\\\\%]/i',
        '/\/(etc\/passwd|proc\/self|windows\/win\.ini)/i',
        '/php:\/\/(input|filter)/i',
    ];

    protected array $badBots = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'dirbuster', 'gobuster', 'wpscan',
        'acunetix', 'nessus', 'openvas', 'zgrab', 'python-requests/1', 'go-http-client/0',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('security.firewall_enabled', true)) {
            return $next($request);
        }

        $ip = (string) $request->ip();

        // 1. Blocked IP list (manual + automatic bans)
        if (BlockedIp::isBlocked($ip)) {
            $this->log($request, 'blocked_ip');
            abort(403, 'Access denied.');
        }

        // 1b. Real-time threat-intelligence blocklist (known threat actors).
        if (setting('security.threat_intel_enabled', true) && \App\Models\ThreatIntelIp::contains($ip)) {
            $this->log($request, 'threat_intel_ip');
            $this->alert('threat_ip', 'Blocked a known threat-actor IP', 'warning',
                "Request from {$ip} matched the real-time threat blocklist.", $ip);
            abort(403, 'Access denied.');
        }

        // 1c. Country blocking (allow-list wins over deny-list when both set).
        if ($blockedCountry = $this->countryBlocked($ip)) {
            $this->log($request, 'country_block', $blockedCountry);
            abort(403, 'Access from your region is not permitted.');
        }

        // 2. User agent checks
        $userAgent = (string) $request->userAgent();

        if ($userAgent === '' && setting('security.block_empty_user_agent', true) && ! $request->is('api/*')) {
            $this->deny($request, 'empty_user_agent');
        }

        foreach ($this->badBots as $bot) {
            if ($userAgent !== '' && stripos($userAgent, $bot) !== false) {
                $this->deny($request, 'bad_bot', $bot);
            }
        }

        foreach ((array) setting('security.blocked_user_agents', []) as $blocked) {
            if ($blocked !== '' && stripos($userAgent, $blocked) !== false) {
                $this->deny($request, 'blocked_user_agent', $blocked);
            }
        }

        // 3. Fake Googlebot detection (claims Googlebot, fails reverse DNS)
        if (setting('security.verify_googlebot', false) && stripos($userAgent, 'googlebot') !== false) {
            if (! $this->isRealGooglebot($ip)) {
                $this->deny($request, 'fake_googlebot');
            }
        }

        // 4. Scanner paths (wp-*, .env, .git, phpmyadmin...)
        $path = strtolower($request->path());

        foreach ($this->scannerPaths as $scannerPath) {
            if ($path === $scannerPath || str_starts_with($path, $scannerPath.'/') || str_ends_with($path, '/'.$scannerPath)) {
                $this->deny($request, 'scanner_path', $scannerPath, autoBan: true);
            }
        }

        // 5. Payload inspection: query string + input values
        //
        // SKIPPED for the admin panel + Livewire endpoints: those are gated by
        // Filament's own auth, and their payloads LEGITIMATELY contain HTML
        // and <script> (rich-editor bodies, the Custom code fields, AI-written
        // pages). Pattern-matching them blocks real product/page saves and,
        // worse, AUTO-BANS the store owner's own IP after 3 strikes — which
        // then 403s the entire site with nothing in the error log (firewall
        // blocks log to Firewall logs only). This middleware runs before the
        // session starts, so it cannot exempt "logged-in staff" — exempting
        // the auth-gated paths is the reliable equivalent. Public routes keep
        // full payload inspection.
        if (! $request->is('admin', 'admin/*', 'livewire/*', 'livewire-*/*')) {
            $payload = $request->getQueryString().' '.implode(' ', $this->flattenInput($request->except(['password', 'password_confirmation', '_token'])));

            foreach (['sqli' => $this->sqliPatterns, 'xss' => $this->xssPatterns, 'traversal' => $this->traversalPatterns] as $rule => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $payload, $match)) {
                        $this->deny($request, $rule, $match[0] ?? null, autoBan: true);
                    }
                }
            }
        }

        // 6. Repeated 404 scanning → temporary ban
        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            $key = "fw.404s.{$ip}";
            $count = (int) Cache::increment($key);

            if ($count === 1) {
                Cache::put($key, 1, 600);
            }

            $threshold = (int) setting('security.max_404_per_10min', 25);

            if ($count >= $threshold) {
                BlockedIp::block($ip, 'Repeated 404 scanning', now()->addHours(2));
                $this->log($request, 'repeated_404');
            }
        }

        return $response;
    }

    protected function deny(Request $request, string $rule, ?string $matched = null, bool $autoBan = false): never
    {
        $this->log($request, $rule, $matched);

        if ($autoBan) {
            $ip = (string) $request->ip();
            $key = "fw.strikes.{$ip}";
            $strikes = (int) Cache::increment($key);

            if ($strikes === 1) {
                Cache::put($key, 1, 3600);
            }

            if ($strikes >= (int) setting('security.strikes_before_ban', 3)) {
                BlockedIp::block($ip, "Firewall: repeated {$rule}", now()->addHours(6));
                $this->alert('auto_ban', "Auto-banned {$ip} for repeated attacks", 'high',
                    "The firewall banned {$ip} after {$strikes} \"{$rule}\" violations.", $ip, ['rule' => $rule, 'strikes' => $strikes]);
            }
        }

        abort(403, 'Request blocked by firewall.');
    }

    /**
     * Returns the offending country code when the request should be blocked
     * by the geo policy, or null when allowed. Allow-list is authoritative:
     * if set, ONLY those countries pass. Otherwise the deny-list blocks.
     */
    protected function countryBlocked(string $ip): ?string
    {
        $allow = array_filter(array_map('strtoupper', (array) setting('security.allowed_countries', [])));
        $deny = array_filter(array_map('strtoupper', (array) setting('security.blocked_countries', [])));

        if ($allow === [] && $deny === []) {
            return null;
        }

        $country = app(\App\Services\Security\GeoIp::class)->country($ip);

        if ($country === null) {
            return null; // couldn't resolve (private IP / lookup failed) → don't block
        }

        if ($allow !== [] && ! in_array($country, $allow, true)) {
            return $country;
        }

        if ($deny !== [] && in_array($country, $deny, true)) {
            return $country;
        }

        return null;
    }

    protected function alert(string $type, string $title, string $severity, string $description, ?string $ip = null, array $meta = []): void
    {
        try {
            app(\App\Services\Security\SecurityAlertService::class)
                ->record($type, $title, $severity, $description, $ip, meta: $meta);
        } catch (\Throwable) {
            // Alerting must never break request handling.
        }
    }

    protected function log(Request $request, string $rule, ?string $matched = null): void
    {
        try {
            FirewallLog::create([
                'ip_address' => $request->ip(),
                'url' => str($request->getRequestUri())->limit(995, '')->toString(),
                'method' => $request->method(),
                'user_agent' => str((string) $request->userAgent())->limit(495, '')->toString(),
                'rule' => $rule,
                'matched_payload' => $matched ? str($matched)->limit(500, '')->toString() : null,
            ]);
        } catch (\Throwable) {
            // Never let firewall logging take the site down.
        }
    }

    protected function isRealGooglebot(string $ip): bool
    {
        return Cache::remember("fw.googlebot.{$ip}", 86400, function () use ($ip) {
            $host = gethostbyaddr($ip);

            if (! $host || ! preg_match('/\.(googlebot|google)\.com$/', $host)) {
                return false;
            }

            return gethostbyname($host) === $ip;
        });
    }

    protected function flattenInput(array $input): array
    {
        $flat = [];

        array_walk_recursive($input, function ($value) use (&$flat) {
            if (is_string($value)) {
                $flat[] = $value;
            }
        });

        return $flat;
    }
}
