<?php

namespace App\Services\Search;

use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * On-site product search — one place for the query, the cache, and the
 * analytics logging shared by the live AJAX dropdown and the full results
 * page.
 *
 * Query: a portable relevance-ranked LIKE search (name > sku > brand >
 * category > short description) that works on MySQL and SQLite alike —
 * exact and prefix matches float to the top. The catalog is small enough
 * that this beats booting Scout's collection engine per keystroke; when
 * the catalog outgrows it, swap the body of results() for Scout/Meilisearch
 * and everything else here still holds.
 */
class ProductSearch
{
    public static function enabled(): bool
    {
        return (bool) setting('search.ajax_enabled', true);
    }

    public static function minChars(): int
    {
        return max(1, (int) setting('search.min_chars', 2));
    }

    public static function maxResults(): int
    {
        return max(1, min(20, (int) setting('search.max_results', 8)));
    }

    /** Normalize a raw query for matching, caching and logging. */
    public static function normalize(string $term): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $term)));
    }

    /**
     * Ranked product results for a term, cached per normalized term +
     * catalog version (bumps on any product save — same signal the page
     * cache rides), so repeated searches never re-hit the database.
     *
     * @return array{term:string, total:int, results:array<int,array<string,mixed>>}
     */
    public function suggest(string $term, ?int $limit = null): array
    {
        $normalized = self::normalize($term);
        $limit ??= self::maxResults();

        if (mb_strlen($normalized) < self::minChars()) {
            return ['term' => $term, 'total' => 0, 'results' => []];
        }

        $version = (int) Cache::get('pagecache.version', 1);
        $key = "search.v{$version}.".md5($normalized."|{$limit}");

        return Cache::remember($key, now()->addMinutes(10), function () use ($normalized, $term, $limit) {
            $products = $this->query($normalized)->limit($limit)->get();

            return [
                'term' => $term,
                'total' => $products->count(),
                'results' => $products->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'url' => $p->url(),
                    'image' => $p->featuredImageUrl(),
                    'price' => price_format($p->currentPrice()),
                    'on_sale' => $p->isOnSale(),
                    'old_price' => $p->isOnSale() ? price_format((float) $p->price) : null,
                ])->all(),
            ];
        });
    }

    /**
     * Relevance-ranked query builder. Escaped LIKE (portable via
     * ESCAPE '!'), weighted so exact-name and prefix matches win over
     * scattered mid-word hits.
     */
    public function query(string $normalized): \Illuminate\Database\Eloquent\Builder
    {
        $needle = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalized);
        $like = "%{$needle}%";
        $prefix = "{$needle}%";

        return Product::query()
            ->where('status', 'published')
            ->whereIn('visibility', ['visible', 'search'])
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, '!'])
                    ->orWhereRaw('LOWER(sku) LIKE ? ESCAPE ?', [$like, '!'])
                    ->orWhereRaw('LOWER(short_description) LIKE ? ESCAPE ?', [$like, '!'])
                    ->orWhereHas('brand', fn ($b) => $b->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, '!']))
                    ->orWhereHas('categories', fn ($c) => $c->whereRaw('LOWER(categories.name) LIKE ? ESCAPE ?', [$like, '!']));
            })
            ->with(['brand'])
            ->orderByRaw('CASE
                WHEN LOWER(name) = ? THEN 0
                WHEN LOWER(name) LIKE ? ESCAPE ? THEN 1
                WHEN LOWER(name) LIKE ? ESCAPE ? THEN 2
                ELSE 3 END', [$normalized, $prefix, '!', $like, '!'])
            ->orderByDesc('sales_count')
            ->orderBy('name');
    }

    /**
     * Log a real search for analytics — deduped per session + term within
     * a short window so live typing ("i" → "ip" → "iqos") records one row
     * for the settled term, not one per keystroke. Call this only when the
     * frontend signals the query has settled.
     */
    public function log(string $term, int $resultsCount): void
    {
        $normalized = self::normalize($term);

        if (mb_strlen($normalized) < self::minChars()) {
            return;
        }

        // Dedup by visitor (IP + optional user) + term — stable regardless
        // of session-cookie handling, so a settled query logs once per window.
        $guard = 'searchlog:'.md5(request()->ip().'|'.(auth()->id() ?? '').'|'.$normalized);

        if (Cache::has($guard)) {
            return;
        }
        Cache::put($guard, true, now()->addSeconds(60));

        SearchLog::create([
            'query' => mb_substr($normalized, 0, 250),
            'results_count' => $resultsCount,
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Analytics aggregates for the dashboard — one grouped query per metric,
     * cached 5 minutes so the page is cheap to open repeatedly.
     *
     * @return array<string,mixed>
     */
    public static function analytics(int $days = 30): array
    {
        return Cache::remember("search.analytics.{$days}", now()->addMinutes(5), function () use ($days) {
            $since = now()->subDays($days);
            // DB query builder (not Eloquent): grouped rows come back as
            // plain stdClass, which serialize into the cache cleanly —
            // caching Eloquent Collections breaks with "incomplete object".
            $base = fn () => DB::table('search_logs')->where('created_at', '>=', $since);

            return [
                'total' => $base()->count(),
                'unique_terms' => $base()->distinct('query')->count('query'),
                'no_results' => $base()->where('results_count', 0)->count(),
                'top' => $base()
                    ->select('query', DB::raw('COUNT(*) as hits'), DB::raw('MAX(results_count) as results'))
                    ->groupBy('query')->orderByDesc('hits')->limit(25)->get(),
                'zero' => $base()->where('results_count', 0)
                    ->select('query', DB::raw('COUNT(*) as hits'))
                    ->groupBy('query')->orderByDesc('hits')->limit(25)->get(),
                'daily' => $base()
                    ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as hits'))
                    ->groupBy('day')->orderBy('day')->get(),
            ];
        });
    }
}
