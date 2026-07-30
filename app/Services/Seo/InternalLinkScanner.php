<?php

namespace App\Services\Seo;

use App\Models\BrokenLink;
use App\Models\Category;
use App\Models\InternalLink;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SlugHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * RankMath-style internal link index. Scans the CONTENT of PUBLISHED
 * products (description + short description) and posts (content) for links
 * to other products/posts and stores each one — so the admin can see how
 * many editorial internal links every product actually receives, and which
 * ones are orphans. Menu/page links are intentionally NOT counted.
 *
 * Correctness rules:
 *  - Only live (published, non-trashed) sources count — a draft's links
 *    don't exist for Google, so they must not inflate inbound counts.
 *  - Old slugs still resolve: a link written before a slug change 301s to
 *    the product, so it is still a real link (via slug_histories).
 *  - Deleting/unpublishing a source removes its rows; deleting a target
 *    removes rows pointing at it (observers + a sweep in every full scan).
 *
 * Performance: two pluck() queries + one slug-history query build the
 * lookup maps up front; sources are scanned in chunks with one delete +
 * one bulk insert per chunk. A few thousand products scan in seconds.
 */
class InternalLinkScanner
{
    protected const LINK_PATTERN = '~<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)</a>~is';

    /** @return array{sources: int, links: int, seconds: float} */
    public function scanAll(): array
    {
        $startedAt = microtime(true);
        $maps = $this->slugMaps();
        $sources = 0;
        $links = 0;

        Product::query()
            ->where('status', 'published')
            ->select(['id', 'description', 'short_description'])
            ->chunkById(200, function ($products) use ($maps, &$sources, &$links) {
                $rows = [];

                foreach ($products as $product) {
                    $sources++;
                    array_push($rows, ...$this->extract(
                        Product::class, $product->id,
                        (string) $product->description."\n".(string) $product->short_description,
                        $maps,
                    ));
                }

                $this->replaceChunk(Product::class, $products->pluck('id')->all(), $rows);
                $links += count($rows);
            });

        Post::query()
            ->published()
            ->select(['id', 'content'])
            ->chunkById(200, function ($posts) use ($maps, &$sources, &$links) {
                $rows = [];

                foreach ($posts as $post) {
                    $sources++;
                    array_push($rows, ...$this->extract(Post::class, $post->id, (string) $post->content, $maps));
                }

                $this->replaceChunk(Post::class, $posts->pluck('id')->all(), $rows);
                $links += count($rows);
            });

        Category::query()
            ->where('is_active', true)
            ->select(['id', 'description', 'content_block', 'custom_html'])
            ->chunkById(200, function ($categories) use ($maps, &$sources, &$links) {
                $rows = [];

                foreach ($categories as $category) {
                    $sources++;
                    array_push($rows, ...$this->extract(Category::class, $category->id, $this->categoryHtml($category), $maps));
                }

                $this->replaceChunk(Category::class, $categories->pluck('id')->all(), $rows);
                $links += count($rows);
            });

        $this->sweepStaleRows();

        Setting::set('seo.links_scanned_at', now()->toDateTimeString());

        return ['sources' => $sources, 'links' => $links, 'seconds' => round(microtime(true) - $startedAt, 1)];
    }

    /**
     * Incremental: re-index ONE product/post after its content changed.
     * A source that is no longer live simply has its rows cleared.
     */
    public function scanSource(Model $source): int
    {
        $html = match (true) {
            $source instanceof Product => (string) $source->description."\n".(string) $source->short_description,
            $source instanceof Category => $this->categoryHtml($source),
            default => (string) ($source->content ?? ''),
        };

        // If the admin fixed/removed a link we had flagged as broken, clear
        // that report on the next save (runs for live and non-live sources).
        $this->reconcileBrokenLinksForSource($source, $html);

        if (! $this->isLiveSource($source)) {
            $this->replaceChunk($source::class, [$source->getKey()], []);

            return 0;
        }

        $rows = $this->extract($source::class, $source->getKey(), $html, $this->slugMaps());

        $this->replaceChunk($source::class, [$source->getKey()], $rows);

        return count($rows);
    }

    /**
     * Record a broken-link report for every live page that still links to a
     * product/post being deleted. Call this BEFORE forget() (which drops the
     * index rows this reads). Restoring the target later re-resolves them.
     */
    public function reportBrokenInbound(Model $deleted, string $reason = 'deleted'): void
    {
        if (! method_exists($deleted, 'url')) {
            return;
        }

        $deadUrl = $deleted->url();

        $inbound = InternalLink::query()
            ->where('target_type', $deleted::class)
            ->where('target_id', $deleted->getKey())
            ->get();

        foreach ($inbound as $link) {
            // The source page must still exist to be worth fixing.
            if (! $link->source) {
                continue;
            }

            BrokenLink::updateOrCreate(
                ['source_type' => $link->source_type, 'source_id' => $link->source_id, 'url' => $deadUrl],
                [
                    'target_type' => $deleted::class,
                    'target_id' => $deleted->getKey(),
                    'anchor' => $link->anchor,
                    'reason' => $reason,
                    'resolved_at' => null,
                ],
            );
        }
    }

    /** Restoring a target makes its inbound links valid again — resolve them. */
    public function resolveBrokenTargeting(Model $restored): void
    {
        BrokenLink::query()
            ->open()
            ->where('target_type', $restored::class)
            ->where('target_id', $restored->getKey())
            ->update(['resolved_at' => now()]);
    }

    /** Resolve open reports for a source whose content no longer contains the dead URL. */
    protected function reconcileBrokenLinksForSource(Model $source, string $html): void
    {
        $open = BrokenLink::query()->open()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->get();

        foreach ($open as $report) {
            $path = (string) parse_url((string) $report->url, PHP_URL_PATH);

            if ($path !== '' && ! str_contains($html, $path)) {
                $report->update(['resolved_at' => now()]);
            }
        }
    }

    /** Drop every row a deleted product/post participates in (either side). */
    public function forget(Model $model): void
    {
        InternalLink::query()
            ->where(fn ($q) => $q->where('source_type', $model::class)->where('source_id', $model->getKey()))
            ->orWhere(fn ($q) => $q->where('target_type', $model::class)->where('target_id', $model->getKey()))
            ->delete();
    }

    protected function isLiveSource(Model $source): bool
    {
        if (method_exists($source, 'trashed') && $source->trashed()) {
            return false;
        }

        return match (true) {
            $source instanceof Product => $source->status === 'published',
            $source instanceof Category => (bool) $source->is_active,
            default => $source->status === 'published' && $source->published_at !== null && $source->published_at->lte(now()),
        };
    }

    /** The editorial HTML a category renders — the fields that can carry links. */
    protected function categoryHtml(Model $category): string
    {
        return (string) $category->description."\n".(string) $category->content_block."\n".(string) $category->custom_html;
    }

    /**
     * slug → id maps, INCLUDING historical slugs: a link written before a
     * slug change 301-redirects to the product, so it still counts. Current
     * slugs win on collision (merge order puts them last).
     *
     * @return array{product: array<string,int>, post: array<string,int>}
     */
    protected function slugMaps(): array
    {
        $history = SlugHistory::query()
            ->whereIn('sluggable_type', [Product::class, Post::class, Category::class])
            ->get(['sluggable_type', 'sluggable_id', 'old_slug'])
            ->groupBy('sluggable_type');

        $oldProductSlugs = ($history[Product::class] ?? collect())->pluck('sluggable_id', 'old_slug')->all();
        $oldPostSlugs = ($history[Post::class] ?? collect())->pluck('sluggable_id', 'old_slug')->all();
        $oldCategorySlugs = ($history[Category::class] ?? collect())->pluck('sluggable_id', 'old_slug')->all();

        // Custom targets indexed by their normalized (trailing-slash-trimmed) path.
        $custom = \App\Models\CustomLinkTarget::query()->get(['id', 'url'])
            ->mapWithKeys(fn ($t) => [rtrim((string) parse_url($t->url, PHP_URL_PATH), '/') ?: '/' => $t->id])
            ->all();

        return [
            'product' => $oldProductSlugs + Product::query()->pluck('id', 'slug')->all(),
            'post' => $oldPostSlugs + Post::query()->pluck('id', 'slug')->all(),
            'category' => $oldCategorySlugs + Category::query()->pluck('id', 'slug')->all(),
            'category_base' => \App\Support\Permalinks::base('category'),
            'custom' => $custom,
        ];
    }

    /** Parse one source's HTML into internal_links rows. */
    protected function extract(string $sourceType, int $sourceId, string $html, array $maps): array
    {
        if (trim($html) === '' || ! preg_match_all(self::LINK_PATTERN, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $ownHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Category URLs use the (configurable) category base — e.g.
        // "/category/{slug}", or "/{slug}" when the base was removed.
        $catBase = $maps['category_base'] ?? 'category';
        $catPattern = $catBase === ''
            ? '~^/([^/]+)/?$~'
            : '~^/'.preg_quote($catBase, '~').'/([^/]+)/?$~';

        $rows = [];

        foreach ($matches as $match) {
            $href = html_entity_decode($match[2]);
            $host = parse_url($href, PHP_URL_HOST);

            // Own-site links only: relative URLs, or absolute on our host.
            if ($host !== null && $host !== $ownHost) {
                continue;
            }

            $path = (string) parse_url($href, PHP_URL_PATH);

            $normalizedPath = rtrim($path, '/') ?: '/';

            // Real entities (product/post/category) ALWAYS win. Custom
            // targets are a fallback for URLs that are NOT a product/post/
            // category (homepage, landing pages) — checked last so a custom
            // target that happens to share a category's URL can never steal
            // that category's inbound links (which zeroed the count).
            [$targetType, $targetId] = match (true) {
                (bool) preg_match('~^/product/([^/]+)/?$~', $path, $m) => [Product::class, $maps['product'][urldecode($m[1])] ?? null],
                (bool) preg_match('~^/blog/([^/]+)/?$~', $path, $m) => [Post::class, $maps['post'][urldecode($m[1])] ?? null],
                (bool) preg_match($catPattern, $path, $m) && isset($maps['category'][urldecode($m[1])])
                    => [Category::class, $maps['category'][urldecode($m[1])]],
                isset($maps['custom'][$normalizedPath]) => [\App\Models\CustomLinkTarget::class, $maps['custom'][$normalizedPath]],
                default => [null, null],
            };

            if ($targetId === null || ($targetType === $sourceType && (int) $targetId === $sourceId)) {
                continue; // external path, unknown slug, or self-link
            }

            $rows[] = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'target_type' => $targetType,
                'target_id' => (int) $targetId,
                'anchor' => mb_substr(trim(strip_tags($match[3])), 0, 255) ?: null,
            ];
        }

        return $rows;
    }

    /** Atomically swap the index rows for a set of sources. */
    protected function replaceChunk(string $sourceType, array $sourceIds, array $rows): void
    {
        DB::transaction(function () use ($sourceType, $sourceIds, $rows) {
            InternalLink::where('source_type', $sourceType)->whereIn('source_id', $sourceIds)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                InternalLink::insert($chunk);
            }
        });
    }

    /**
     * Remove rows whose source or target no longer exists or is no longer
     * live (deleted mid-week, unpublished, force-deleted without events).
     * Four indexed subquery deletes — cheap, runs once per full scan.
     */
    protected function sweepStaleRows(): void
    {
        $liveProducts = Product::query()->where('status', 'published')->select('id');

        InternalLink::where('source_type', Product::class)->whereNotIn('source_id', $liveProducts)->delete();

        $livePosts = Post::query()->published()->select('id');

        InternalLink::where('source_type', Post::class)->whereNotIn('source_id', $livePosts)->delete();

        $liveCategories = Category::query()->where('is_active', true)->select('id');

        InternalLink::where('source_type', Category::class)->whereNotIn('source_id', $liveCategories)->delete();

        // Targets only need to EXIST (a live page linking to a draft is
        // still a real outbound link on the live page — but a deleted
        // target is gone for good).
        InternalLink::where('target_type', Product::class)
            ->whereNotIn('target_id', Product::query()->select('id'))
            ->delete();

        InternalLink::where('target_type', Post::class)
            ->whereNotIn('target_id', Post::query()->select('id'))
            ->delete();

        InternalLink::where('target_type', Category::class)
            ->whereNotIn('target_id', Category::query()->select('id'))
            ->delete();
    }
}
