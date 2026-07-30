<?php

namespace App\Services\Ai;

use App\Models\Product;

/**
 * The AI places internal links contextually WHILE writing — the catalog of
 * live URLs travels once per batch inside the cached system prompt, so
 * linking costs no extra tokens and never lands as a dump at the end.
 *
 * This class is the deterministic safety net (zero LLM cost):
 *  - audit()      verifies every link the AI placed right after publishing:
 *                 self-links and URLs not in the catalog are unwrapped back
 *                 to plain text.
 *  - unwrapUrls() runs in the finalize pass and strips links pointing to
 *                 batch products that never went live (failed items or
 *                 slug changes), so no published copy carries a dead link.
 *
 * Both operations cover description AND short_description, and handle
 * single- or double-quoted href attributes.
 */
class InternalLinker
{
    protected const LINK_PATTERN = '~<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)</a>~is';

    /** Columns that can carry AI-written links. */
    protected const HTML_COLUMNS = ['description', 'short_description'];

    /**
     * Guarantee internal links exist. The AI is asked to link siblings while
     * writing, but doesn't always comply — so after publishing we
     * deterministically link the first unlinked mention of each catalog
     * product's name (longest names first, self excluded, capped). Returns
     * how many links were added.
     *
     * @param  array<int,array{name:string,url:string}>  $catalog
     */
    public function ensureLinks(Product $product, array $catalog, int $max = 5): int
    {
        $selfUrl = $product->url();

        // Longest names first so "IQOS TEREA Amber" wins over "TEREA".
        $targets = collect($catalog)
            ->filter(fn ($p) => ! empty($p['name']) && ! empty($p['url']) && $p['url'] !== $selfUrl)
            ->unique('url')
            ->sortByDesc(fn ($p) => mb_strlen($p['name']))
            ->values();

        $added = 0;

        foreach (self::HTML_COLUMNS as $column) {
            if ($added >= $max) {
                break;
            }

            $html = (string) $product->{$column};
            if ($html === '') {
                continue;
            }

            $existingLinks = substr_count(strtolower($html), '<a ');

            foreach ($targets as $target) {
                if ($added >= $max || ($existingLinks + $added) >= $max) {
                    break;
                }

                // Already linked to this URL anywhere (absolute or
                // root-relative form)? Skip.
                $relative = parse_url($target['url'], PHP_URL_PATH) ?: $target['url'];

                if (str_contains($html, 'href="'.$target['url'].'"') || str_contains($html, "href='".$target['url']."'")
                    || str_contains($html, 'href="'.$relative.'"') || str_contains($html, "href='".$relative."'")) {
                    continue;
                }

                $name = preg_quote($target['name'], '~');

                // First occurrence of the exact name that is NOT already
                // inside an <a>…</a> and NOT inside a tag attribute.
                $pattern = '~(?<![\w>])('.$name.')(?![^<]*</a>)(?![^<]*>)~u';

                $replaced = preg_replace_callback($pattern, function ($m) use ($target, &$added) {
                    $added++;

                    return '<a href="'.$target['url'].'">'.$m[1].'</a>';
                }, $html, 1, $count);

                if ($count > 0) {
                    $html = $replaced;
                }
            }

            if ($html !== (string) $product->{$column}) {
                $product->update([$column => $html]);
            }
        }

        return $added;
    }

    /** @return array{kept: int, unwrapped: int} */
    public function audit(Product $product, array $allowedUrls): array
    {
        $selfUrl = $product->url();
        $kept = 0;
        $unwrapped = 0;
        $dirty = [];

        foreach (self::HTML_COLUMNS as $column) {
            $original = (string) $product->{$column};

            if ($original === '') {
                continue;
            }

            $html = preg_replace_callback(
                self::LINK_PATTERN,
                function (array $match) use ($selfUrl, $allowedUrls, &$kept, &$unwrapped): string {
                    $href = self::absolutize(html_entity_decode($match[2]));

                    if ($href !== $selfUrl && in_array($href, $allowedUrls, true)) {
                        $kept++;

                        return $match[0];
                    }

                    $unwrapped++;

                    return $match[3]; // keep the anchor text, drop the link
                },
                $original,
            );

            if ($html !== $original) {
                $dirty[$column] = $html;
            }
        }

        if ($dirty !== []) {
            $product->update($dirty);
        }

        return ['kept' => $kept, 'unwrapped' => $unwrapped];
    }

    /**
     * Rewrite own-domain absolute links to root-relative hrefs
     * ("/product/slug"). The catalog and route() produce APP_URL-absolute
     * URLs, which on a dev store bakes "http://127.0.0.1:8000/…" into
     * published copy — dead the moment the site deploys to a real domain.
     * Root-relative links work on every domain. Returns links rewritten.
     */
    public static function relativize(Product $product): int
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '') {
            return 0;
        }

        $count = 0;
        $dirty = [];
        $pattern = '~(<a\s[^>]*?href=)(["\'])'.preg_quote($appUrl, '~').'(/[^"\']*)?\2~i';

        foreach (self::HTML_COLUMNS as $column) {
            $original = (string) $product->{$column};

            if ($original === '') {
                continue;
            }

            $html = preg_replace_callback($pattern, function (array $m) use (&$count): string {
                $count++;

                return $m[1].$m[2].($m[3] !== '' && isset($m[3]) ? $m[3] : '/').$m[2];
            }, $original);

            if ($html !== $original) {
                $dirty[$column] = $html;
            }
        }

        if ($dirty !== []) {
            $product->update($dirty);
        }

        return $count;
    }

    /**
     * Root-relative hrefs (written by relativize, or by the AI) must compare
     * equal to their absolute catalog form in audits and dead-link sweeps.
     */
    protected static function absolutize(string $href): string
    {
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return rtrim((string) config('app.url'), '/').$href;
        }

        return $href;
    }

    /** Strip links to URLs that turned out dead. Returns how many were removed. */
    public static function unwrapUrls(Product $product, array $deadUrls): int
    {
        if ($deadUrls === []) {
            return 0;
        }

        $removed = 0;
        $dirty = [];

        foreach (self::HTML_COLUMNS as $column) {
            $original = (string) $product->{$column};

            if ($original === '') {
                continue;
            }

            $html = preg_replace_callback(
                self::LINK_PATTERN,
                function (array $match) use ($deadUrls, &$removed): string {
                    if (in_array(self::absolutize(html_entity_decode($match[2])), $deadUrls, true)) {
                        $removed++;

                        return $match[3];
                    }

                    return $match[0];
                },
                $original,
            );

            if ($html !== $original) {
                $dirty[$column] = $html;
            }
        }

        if ($dirty !== []) {
            $product->update($dirty);
        }

        return $removed;
    }
}
