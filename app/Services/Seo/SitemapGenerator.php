<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamic XML sitemaps per current Google/Bing/Yandex guidance:
 *
 *  - Split by content type (products / product categories / posts / pages /
 *    blog categories / authors), each type chunked into numbered files with
 *    an admin-configurable URL limit (spec max 50,000 per file).
 *  - Accurate <lastmod> from the content's real updated_at — never "now"
 *    (Google ignores lastmod from sites that fake it). changefreq/priority
 *    are omitted: Google and Bing officially ignore both.
 *  - <image:image> entries for product/post images (admin toggle) so
 *    Google Images indexes them.
 *  - Noindexed, unpublished, hidden and admin-excluded content never
 *    appears. Empty blog categories are skipped (no thin archives).
 *  - Auto-updates: served dynamically, cached one hour, and the cache
 *    version is bumped the moment any product/post/category/page changes —
 *    new content appears in the sitemap immediately.
 *
 * Admin controls live in SEO settings → XML sitemap.
 */
class SitemapGenerator
{
    public const CACHE_TTL = 3600;

    /** section key => included by default */
    public const SECTIONS = [
        'products' => true,
        'categories' => true,
        'posts' => true,
        'pages' => true,
        'post-categories' => true,
        'authors' => false,
    ];

    public static function enabled(string $section): bool
    {
        if (! array_key_exists($section, self::SECTIONS)) {
            return false;
        }

        // Product & category URLs only exist with the ecommerce module on.
        if (in_array($section, ['products', 'categories'], true) && ! ecommerce_enabled()) {
            return false;
        }

        return (bool) setting('seo.sitemap_'.str_replace('-', '_', $section), self::SECTIONS[$section]);
    }

    /** Admin-chosen URLs per sitemap file, clamped to the 50k spec limit. */
    public static function perPage(): int
    {
        return max(10, min(49500, (int) setting('seo.sitemap_links_per_page', 1000)));
    }

    public static function imagesEnabled(): bool
    {
        return (bool) setting('seo.sitemap_images', true);
    }

    /** Bump on any content change → every cached sitemap regenerates. */
    public static function flush(): void
    {
        Cache::put('sitemap.version', (int) Cache::get('sitemap.version', 1) + 1);
    }

    protected function cacheKey(string $suffix): string
    {
        return 'sitemap.v'.(int) Cache::get('sitemap.version', 1).'.'.$suffix;
    }

    // ── Index ───────────────────────────────────────────────────────

    public function index(): string
    {
        return Cache::remember($this->cacheKey('index'), self::CACHE_TTL, function () {
            $perPage = self::perPage();
            $entries = [];

            foreach (array_keys(self::SECTIONS) as $section) {
                if (! self::enabled($section)) {
                    continue;
                }

                $total = $this->countFor($section);

                // posts + pages prepend fixed URLs onto page 1, so they always
                // have at least one file even when the DB count is 0.
                $prependsFixedUrls = in_array($section, ['posts', 'pages'], true);

                if ($total === 0 && ! $prependsFixedUrls) {
                    continue;
                }

                $pages = max($prependsFixedUrls ? 1 : 0, (int) ceil($total / $perPage));
                $lastmod = $this->lastmodFor($section);

                for ($page = 1; $page <= $pages; $page++) {
                    $entries[] = [
                        'loc' => url('/sitemap-'.$section.($page > 1 ? '-'.$page : '').'.xml'),
                        'lastmod' => $lastmod,
                    ];
                }
            }

            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach ($entries as $entry) {
                $xml .= '  <sitemap><loc>'.e($entry['loc']).'</loc>'
                    .($entry['lastmod'] ? '<lastmod>'.$entry['lastmod'].'</lastmod>' : '')
                    ."</sitemap>\n";
            }

            return $xml.'</sitemapindex>';
        });
    }

    // ── Sections ────────────────────────────────────────────────────

    public function section(string $name, int $page = 1): ?string
    {
        if (! self::enabled($name) || $page < 1) {
            return null;
        }

        return Cache::remember($this->cacheKey("{$name}.{$page}"), self::CACHE_TTL, function () use ($name, $page) {
            $urls = match ($name) {
                'products' => $this->products($page),
                'categories' => $this->categories($page),
                'posts' => $this->posts($page),
                'pages' => $this->pages($page),
                'post-categories' => $this->postCategories($page),
                'authors' => $this->authors($page),
            };

            // Backstop against any count/filter drift (noindex or custom
            // canonicals emptying a page that the index still lists): an
            // enabled section always returns a valid, 200 urlset — empty if
            // need be — so a crawler never hits a 404 on a listed sitemap URL.
            // (Disabled/unknown sections already returned null above → 404.)
            return $this->urlset($urls);
        });
    }

    protected function products($page)
    {
        return Product::visible()
            ->whereNotIn('id', $this->excludedIds('product'))
            ->with(['seoMeta', 'images'])
            ->orderBy('id')
            ->forPage($page, self::perPage())
            ->get()
            ->reject(fn ($p) => $p->seoMeta?->noindex || $this->canonicalElsewhere($p))
            ->map(fn ($p) => $this->url(
                $p->url(),
                $p->updated_at,
                self::imagesEnabled()
                    ? collect([$p->featuredImageUrl(), ...$p->images->map->url()])->filter()->unique()->values()->all()
                    : [],
            ));
    }

    protected function categories($page)
    {
        return Category::active()
            ->with('seoMeta')
            ->orderBy('id')
            ->forPage($page, self::perPage())
            ->get()
            ->reject(fn ($c) => $c->seoMeta?->noindex || $this->canonicalElsewhere($c))
            ->map(fn ($c) => $this->url(
                $c->url(),
                $c->updated_at,
                self::imagesEnabled() ? $this->categoryImages($c) : [],
            ));
    }

    /**
     * A category's feature image for the image sitemap. Skips the seeded
     * decorative .svg placeholders (only real photos belong in Google Images).
     *
     * @return list<string>
     */
    protected function categoryImages(Category $category): array
    {
        $image = $category->imageUrl();

        if (! $image || str_ends_with(strtolower($image), '.svg')) {
            return [];
        }

        return [$image];
    }

    protected function posts($page)
    {
        $urls = Post::published()
            ->whereNotIn('id', $this->excludedIds('post'))
            ->with('seoMeta')
            ->orderBy('id')
            ->forPage($page, self::perPage())
            ->get()
            ->reject(fn ($p) => $p->seoMeta?->noindex || $this->canonicalElsewhere($p))
            ->map(fn ($p) => $this->url(
                $p->url(),
                $p->updated_at,
                self::imagesEnabled() ? array_filter([$p->featuredImageUrl()]) : [],
            ));

        if ($page === 1) {
            $urls->prepend($this->url(route('blog.index'), Post::published()->max('updated_at')));
        }

        return $urls;
    }

    protected function pages($page)
    {
        $noindexSlugs = ['cart', 'checkout', 'my-account'];

        $urls = Page::published()
            ->with('seoMeta')
            ->orderBy('id')
            ->forPage($page, self::perPage())
            ->get()
            ->reject(fn ($p) => $p->seoMeta?->noindex || in_array($p->slug, $noindexSlugs) || $this->canonicalElsewhere($p))
            ->map(fn ($p) => $this->url($p->url(), $p->updated_at));

        if ($page === 1) {
            $catalogTouched = Product::visible()->max('updated_at');
            $urls->prepend($this->url(route('shop'), $catalogTouched));
            $urls->prepend($this->url(url('/'), $catalogTouched));
        }

        return $urls;
    }

    /** Blog categories — only ones with at least one published post. */
    protected function postCategories($page)
    {
        return PostCategory::query()
            ->whereHas('posts', fn ($q) => $q->published())
            ->withMax(['posts as latest_post_at' => fn ($q) => $q->published()], 'updated_at')
            ->orderBy('id')
            ->forPage($page, self::perPage())
            ->get()
            ->map(fn ($c) => $this->url(
                route('blog.category', $c->slug),
                $c->latest_post_at ? Carbon::parse($c->latest_post_at) : $c->updated_at,
            ));
    }

    protected function authors($page)
    {
        return Post::published()
            ->join('users', 'users.id', '=', 'posts.author_id')
            ->selectRaw('users.public_slug, MAX(posts.updated_at) as latest')
            ->groupBy('users.public_slug')
            ->orderBy('users.public_slug')
            ->forPage($page, self::perPage())
            ->get()
            ->map(fn ($row) => $this->url(route('blog.author', $row->public_slug), Carbon::parse($row->latest)));
    }

    /**
     * A page whose custom canonical points somewhere else must not appear in
     * the sitemap — the sitemap should only ever list canonical URLs.
     */
    protected function canonicalElsewhere($model): bool
    {
        $canonical = $model->seoMeta?->canonical_url;

        return $canonical && rtrim((string) $canonical, '/') !== rtrim($model->url(), '/');
    }

    // ── Counts + freshness for the index ────────────────────────────

    protected function countFor(string $section): int
    {
        return match ($section) {
            'products' => Product::visible()->whereNotIn('id', $this->excludedIds('product'))->count(),
            'categories' => Category::active()->count(),
            // Real row counts only. The blog-index / home + shop URLs that
            // posts()/pages() prepend ride on page 1 — they do NOT add a page,
            // so they must not inflate the count (a +1/+2 here made the index
            // advertise a trailing sitemap-*.xml that 404s whenever the real
            // count is an exact multiple of perPage). The "always at least one
            // file" guarantee for these prepend-bearing sections lives in index().
            'posts' => Post::published()->whereNotIn('id', $this->excludedIds('post'))->count(),
            'pages' => Page::published()->count(),
            'post-categories' => PostCategory::whereHas('posts', fn ($q) => $q->published())->count(),
            'authors' => Post::published()->distinct()->count('author_id'),
        };
    }

    /** Real latest-change timestamp per section — never a faked "now". */
    protected function lastmodFor(string $section): ?string
    {
        $timestamp = match ($section) {
            'products' => Product::visible()->max('updated_at'),
            'categories' => Category::active()->max('updated_at'),
            'posts', 'authors' => Post::published()->max('updated_at'),
            'pages' => collect([Page::published()->max('updated_at'), Product::visible()->max('updated_at')])->filter()->max(),
            'post-categories' => Post::published()->max('updated_at'),
        };

        return $timestamp ? Carbon::parse($timestamp)->toAtomString() : null;
    }

    /** @return array<int, int> */
    protected function excludedIds(string $type): array
    {
        return collect(explode(',', (string) setting("seo.sitemap_exclude_{$type}_ids", '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values()
            ->all();
    }

    // ── XML rendering ───────────────────────────────────────────────

    protected function url(string $loc, $lastmod, array $images = []): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod ? Carbon::parse($lastmod)->toAtomString() : null,
            'images' => $images,
        ];
    }

    protected function urlset($urls): string
    {
        $hasImages = collect($urls)->contains(fn ($u) => ($u['images'] ?? []) !== []);
        $imageNs = $hasImages ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.$imageNs.'>'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($url['loc'])."</loc>\n";

            if ($url['lastmod']) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }

            // Google allows up to 1,000 images per URL; 50 is plenty here.
            foreach (array_slice($url['images'] ?? [], 0, 50) as $image) {
                $xml .= '    <image:image><image:loc>'.e($image)."</image:loc></image:image>\n";
            }

            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>';
    }
}
