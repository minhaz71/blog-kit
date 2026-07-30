<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Scans installed Composer packages for known security advisories —
 * `composer audit` against the Packagist security database. This is the
 * ShopKit analog of Wordfence's plugin/theme vulnerability monitoring:
 * it tells you when a dependency you rely on has a published CVE.
 *
 * Results are cached so the dashboard reads them without shelling out.
 */
class DependencyAudit
{
    public const CACHE_KEY = 'security.dependency_audit';

    /**
     * @return array{ran:bool, advisories:int, abandoned:int, packages:array, checked_at:string, error:?string}
     */
    public function run(): array
    {
        $result = [
            'ran' => false,
            'advisories' => 0,
            'abandoned' => 0,
            'packages' => [],
            'checked_at' => now()->toIso8601String(),
            'error' => null,
        ];

        $composer = base_path('composer.phar');
        $binary = is_file($composer) ? 'php '.escapeshellarg($composer) : 'composer';

        $process = Process::fromShellCommandline(
            $binary.' audit --format=json --no-interaction',
            base_path(),
        );
        $process->setTimeout(120);
        $process->run();

        $json = json_decode($process->getOutput(), true);

        if (! is_array($json)) {
            $result['error'] = 'composer audit did not return JSON — is Composer available on this server?';
            Cache::put(self::CACHE_KEY, $result, now()->addDay());

            return $result;
        }

        // composer audit exits non-zero when advisories exist; that's expected.
        $advisories = $json['advisories'] ?? [];
        $abandoned = $json['abandoned'] ?? [];

        $packages = [];
        foreach ($advisories as $package => $items) {
            foreach ((array) $items as $item) {
                $packages[] = [
                    'package' => $package,
                    'title' => $item['title'] ?? 'Advisory',
                    'cve' => $item['cve'] ?? null,
                    'severity' => strtolower((string) ($item['severity'] ?? 'unknown')),
                    'link' => $item['link'] ?? null,
                    'affected' => $item['affectedVersions'] ?? null,
                ];
            }
        }

        $result = [
            'ran' => true,
            'advisories' => count($packages),
            'abandoned' => is_array($abandoned) ? count($abandoned) : 0,
            'packages' => $packages,
            'checked_at' => now()->toIso8601String(),
            'error' => null,
        ];

        Cache::put(self::CACHE_KEY, $result, now()->addDay());

        return $result;
    }

    /** Last cached result without re-running (for the dashboard). */
    public function latest(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }
}
