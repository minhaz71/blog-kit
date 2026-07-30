<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Single source of truth for what version of Hemdox Blog Kit (and each of its
 * tools) is running, plus the git state used by the updater. Reads
 * version.json at the repo root — a `git pull` that ships a new version.json
 * is what makes the admin reflect the new version.
 */
class Version
{
    /** Core Hemdox Blog Kit version, e.g. "1.0.0". */
    public static function core(): string
    {
        $manifest = self::manifest();

        // 'blogkit' is the current manifest key; 'shopkit' is read as a
        // fallback for archives/manifests produced before the rebrand.
        return (string) ($manifest['blogkit'] ?? $manifest['shopkit'] ?? '0.0.0');
    }

    public static function releasedAt(): ?string
    {
        return self::manifest()['released'] ?? null;
    }

    /** Minimum PHP the current version requires. */
    public static function requiredPhp(): string
    {
        return (string) (self::manifest()['requires']['php'] ?? '8.3.0');
    }

    /** @return array<string, string> tool slug => version */
    public static function components(): array
    {
        return (array) (self::manifest()['components'] ?? []);
    }

    /** Human labels for the component slugs (for the admin table). */
    public const COMPONENT_LABELS = [
        'ai-blog-writer' => 'AI Blog Writer',
        'blog-idea-generator' => 'Blog Idea Generator',
        'ai-product-writer' => 'AI Product Writer',
        'link-agent' => 'Internal Link Agent',
        'backup' => 'Backup & Restore',
        'security' => 'Security Center',
        'seo' => 'SEO Suite',
        'product-templates' => 'Product Template Builder',
        'payments' => 'Payments',
        'storefront' => 'Storefront',
    ];

    /**
     * The version.json manifest (cached 60s so the admin page and updater
     * don't re-read disk repeatedly, but a fresh pull is reflected quickly).
     */
    public static function manifest(): array
    {
        return safe_cache('blogkit.version.manifest', 60, function (): array {
            $path = base_path('version.json');

            if (! is_file($path)) {
                return ['blogkit' => (string) config('blogkit.version', '0.0.0')];
            }

            return json_decode((string) file_get_contents($path), true) ?: [];
        });
    }

    public static function forget(): void
    {
        Cache::forget('blogkit.version.manifest');
    }

    // ── Git state (tolerant when not a repo / git absent) ──────────────

    public static function isGitRepo(): bool
    {
        return is_dir(base_path('.git'));
    }

    public static function gitBranch(): ?string
    {
        return self::git(['rev-parse', '--abbrev-ref', 'HEAD']);
    }

    public static function gitCommit(bool $short = true): ?string
    {
        return self::git(['rev-parse', $short ? '--short' : 'HEAD', 'HEAD']);
    }

    public static function gitCommittedAt(): ?string
    {
        return self::git(['log', '-1', '--format=%cd', '--date=format:%Y-%m-%d %H:%M']);
    }

    /**
     * How many commits behind the remote the local checkout is (update
     * availability). Requires a prior `git fetch`; returns null when unknown
     * (not a repo, no upstream, git missing). $fetch runs a fetch first.
     */
    public static function commitsBehind(bool $fetch = false): ?int
    {
        if (! self::isGitRepo()) {
            return null;
        }

        if ($fetch) {
            self::git(['fetch', '--quiet', '--tags'], 60);
        }

        $branch = self::gitBranch();
        if (! $branch || $branch === 'HEAD') {
            return null;
        }

        $count = self::git(['rev-list', '--count', "HEAD..origin/{$branch}"]);

        return is_numeric($count) ? (int) $count : null;
    }

    /** @param array<int,string> $args */
    protected static function git(array $args, int $timeout = 15): ?string
    {
        if (! self::isGitRepo()) {
            return null;
        }

        try {
            $process = new Process(['git', ...$args], base_path());
            $process->setTimeout($timeout);
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) ?: null : null;
        } catch (\Throwable) {
            return null; // git not installed, or any failure — never throw
        }
    }
}
