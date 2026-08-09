<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Scans installed Composer packages for known security advisories — the
 * BlogKit analog of Wordfence's plugin/theme vulnerability monitoring: it tells
 * you when a dependency you rely on has a published CVE.
 *
 * Two tiers, so it works on ANY server (owners never touch the VPS):
 *   1. `composer audit --format=json` when a composer binary is reachable —
 *      the most accurate (composer does its own version matching).
 *   2. Fallback: query the Packagist security-advisories API directly with the
 *      package list from composer.lock and match versions here. Pure PHP +
 *      HTTPS, so it runs even when composer is not installed or not on the
 *      web process's PATH (the usual CyberPanel / OpenLiteSpeed lsphp case).
 *
 * Results are cached so the dashboard reads them without shelling out.
 */
class DependencyAudit
{
    public const CACHE_KEY = 'security.dependency_audit';

    /**
     * @return array{ran:bool, advisories:int, abandoned:int, packages:array, checked_at:string, error:?string, source:string}
     */
    public function run(): array
    {
        // Tier 1 — real composer audit (most accurate) when we can run it.
        $composer = $this->locateComposer();
        if ($composer !== null) {
            $viaComposer = $this->runComposer($composer);
            if ($viaComposer !== null) {
                return $this->cache($viaComposer);
            }
            // composer was found but produced no usable JSON — fall through to
            // the API tier rather than failing outright.
        }

        // Tier 2 — Packagist advisories API from composer.lock (no composer
        // binary needed).
        return $this->cache($this->runViaPackagist($composer === null));
    }

    /** Last cached result without re-running (for the dashboard). */
    public function latest(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }

    // ---- Tier 1: composer binary -----------------------------------------

    /**
     * Find a runnable composer command, or null. Tries, in order: a project
     * composer.phar, common absolute install paths (lsphp often has a minimal
     * PATH that omits them), then `composer` on PATH.
     */
    protected function locateComposer(): ?string
    {
        $phar = base_path('composer.phar');
        if (is_file($phar)) {
            return escapeshellarg(PHP_BINARY).' '.escapeshellarg($phar);
        }

        $candidates = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/bin/composer',
            '/opt/cpanel/composer/bin/composer',
            '/usr/local/composer/composer',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return escapeshellarg($path);
            }
        }

        // Last resort: rely on PATH. `command -v` tells us if it's actually
        // there so we don't shell out to a command that doesn't exist. It exits
        // non-zero (no throw) when composer is absent.
        try {
            $probe = new Process(['sh', '-c', 'command -v composer']);
            $probe->run();
            $which = trim((string) $probe->getOutput());
        } catch (\Throwable) {
            $which = '';
        }

        return $which !== '' ? escapeshellarg($which) : null;
    }

    /**
     * Run `composer audit --format=json`. Returns a normalized result, or null
     * if composer didn't yield parseable JSON (so the caller can fall back).
     */
    protected function runComposer(string $composer): ?array
    {
        // composer NEEDS a writable HOME/COMPOSER_HOME or it errors out (and
        // may print non-JSON). lsphp often runs with an unset/unwritable HOME,
        // so point it at a dir we know we can write.
        $home = storage_path('app/composer-home');
        if (! is_dir($home)) {
            @mkdir($home, 0775, true);
        }

        $process = Process::fromShellCommandline(
            $composer.' audit --format=json --no-interaction --no-plugins',
            base_path(),
            [
                'COMPOSER_HOME' => $home,
                'HOME' => $home,
                'COMPOSER_NO_INTERACTION' => '1',
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            ],
        );
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        // composer may prepend warnings before the JSON body — parse from the
        // first '{'. It also exits non-zero when advisories exist (expected).
        $json = $this->extractJson($process->getOutput());
        if (! is_array($json)) {
            return null;
        }

        $packages = [];
        foreach (($json['advisories'] ?? []) as $package => $items) {
            foreach ((array) $items as $item) {
                $packages[] = $this->normalizeAdvisory($package, $item);
            }
        }

        $abandoned = $json['abandoned'] ?? [];

        return [
            'ran' => true,
            'advisories' => count($packages),
            'abandoned' => is_array($abandoned) ? count($abandoned) : 0,
            'packages' => $packages,
            'checked_at' => now()->toIso8601String(),
            'error' => null,
            'source' => 'composer',
        ];
    }

    // ---- Tier 2: Packagist advisories API --------------------------------

    /**
     * Audit using only composer.lock + the public Packagist advisories API.
     * Works with no composer binary. Matches each advisory's affectedVersions
     * constraint against the installed version so we don't false-positive on
     * already-patched packages.
     */
    protected function runViaPackagist(bool $composerMissing): array
    {
        $base = [
            'ran' => false,
            'advisories' => 0,
            'abandoned' => 0,
            'packages' => [],
            'checked_at' => now()->toIso8601String(),
            'error' => null,
            'source' => 'packagist',
        ];

        $lockPath = base_path('composer.lock');
        if (! is_file($lockPath)) {
            $base['error'] = 'No composer.lock found — cannot determine installed packages.';

            return $base;
        }

        $lock = json_decode((string) file_get_contents($lockPath), true);
        if (! is_array($lock)) {
            $base['error'] = 'composer.lock is unreadable.';

            return $base;
        }

        // vendor/name => installed version.
        $installed = [];
        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $pkg) {
            if (isset($pkg['name'], $pkg['version'])) {
                $installed[$pkg['name']] = $this->normalizeVersion((string) $pkg['version']);
            }
        }

        if ($installed === []) {
            $base['error'] = 'composer.lock lists no packages.';

            return $base;
        }

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->asForm()
                ->post('https://packagist.org/api/security-advisories/', [
                    'packages' => array_keys($installed),
                ]);
        } catch (\Throwable $e) {
            $base['error'] = 'Could not reach the Packagist advisories API ('.class_basename($e).'). '
                .($composerMissing ? 'Composer is also not installed on this server. ' : '')
                .'The server may block outbound HTTPS.';

            return $base;
        }

        if (! $response->successful()) {
            $base['error'] = 'Packagist advisories API returned HTTP '.$response->status().'.';

            return $base;
        }

        $advisories = (array) ($response->json('advisories') ?? []);

        $packages = [];
        foreach ($advisories as $package => $items) {
            $version = $installed[$package] ?? null;
            if ($version === null) {
                continue;
            }
            foreach ((array) $items as $item) {
                $affected = (string) ($item['affectedVersions'] ?? '');
                // Only report if the INSTALLED version is actually affected.
                if ($affected !== '' && ! $this->versionAffected($version, $affected)) {
                    continue;
                }
                $packages[] = $this->normalizeAdvisory($package, $item);
            }
        }

        return [
            'ran' => true,
            'advisories' => count($packages),
            'abandoned' => 0, // the API doesn't report abandonment
            'packages' => $packages,
            'checked_at' => now()->toIso8601String(),
            'error' => null,
            'source' => 'packagist',
        ];
    }

    // ---- Shared helpers ---------------------------------------------------

    /** Shape one advisory item into the row the dashboard/command expects. */
    protected function normalizeAdvisory(string $package, array $item): array
    {
        return [
            'package' => $package,
            'title' => $item['title'] ?? 'Advisory',
            'cve' => $item['cve'] ?? null,
            'severity' => strtolower((string) ($item['severity'] ?? 'unknown')),
            'link' => $item['link'] ?? null,
            'affected' => $item['affectedVersions'] ?? null,
        ];
    }

    protected function cache(array $result): array
    {
        Cache::put(self::CACHE_KEY, $result, now()->addDay());

        return $result;
    }

    /** Pull the first JSON object out of noisy output (warnings before it). */
    protected function extractJson(string $output): mixed
    {
        $direct = json_decode($output, true);
        if (is_array($direct)) {
            return $direct;
        }

        $start = strpos($output, '{');
        $end = strrpos($output, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($output, $start, $end - $start + 1), true);
    }

    /** Strip a leading "v" and drop build metadata for version_compare. */
    protected function normalizeVersion(string $version): string
    {
        $version = ltrim(trim($version), 'vV');

        // Drop build metadata (+...) and a "dev-" prefix noise.
        $version = preg_replace('/\+.*$/', '', $version) ?? $version;

        return $version;
    }

    /**
     * Does $version fall within a Composer-style constraint string? Supports the
     * forms advisories actually use: OR groups separated by "||"/"|", AND terms
     * separated by ",", and the operators < <= > >= = == !=. Unknown/exotic
     * constraints conservatively return true (report rather than hide a CVE).
     */
    protected function versionAffected(string $version, string $constraint): bool
    {
        $version = $this->normalizeVersion($version);

        foreach (preg_split('/\s*\|\|\s*|\s*\|\s*/', trim($constraint)) as $orGroup) {
            $orGroup = trim($orGroup);
            if ($orGroup === '') {
                continue;
            }

            if ($this->matchesAndGroup($version, $orGroup)) {
                return true;
            }
        }

        return false;
    }

    /** Every comma-separated term in one OR group must hold (logical AND). */
    protected function matchesAndGroup(string $version, string $group): bool
    {
        foreach (preg_split('/\s*,\s*/', $group) as $term) {
            $term = trim($term);
            if ($term === '' || $term === '*') {
                continue;
            }

            if (! $this->matchesTerm($version, $term)) {
                return false;
            }
        }

        return true;
    }

    /** Evaluate one "<3.1.6"-style term against the installed version. */
    protected function matchesTerm(string $version, string $term): bool
    {
        if (! preg_match('/^(<=|>=|<>|!=|==|=|<|>)?\s*v?(.+)$/', $term, $m)) {
            return true; // can't parse → don't hide a potential CVE
        }

        $op = $m[1] ?: '=';
        $target = $this->normalizeVersion($m[2]);

        return match ($op) {
            '<' => version_compare($version, $target, '<'),
            '<=' => version_compare($version, $target, '<='),
            '>' => version_compare($version, $target, '>'),
            '>=' => version_compare($version, $target, '>='),
            '!=', '<>' => version_compare($version, $target, '!='),
            default => version_compare($version, $target, '=='),
        };
    }
}
