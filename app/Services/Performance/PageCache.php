<?php

namespace App\Services\Performance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * App-level full-page cache for GUEST traffic — the fallback layer that
 * makes "serve from cache, don't boot the CMS" true on any server.
 *
 * Layering with LiteSpeed: when the site runs on LiteSpeed/OpenLiteSpeed,
 * the SERVER caches guest pages via the X-LiteSpeed-Cache-Control headers
 * (existing middleware) — double-caching in the app would only waste
 * memory, so 'auto' mode disables this layer there. On nginx/apache/dev
 * this layer does the job instead. Admins can force on/off.
 *
 * Safety rules (mirrors the LiteSpeed header rules):
 *  - GET + guest + 200 text/html only — logged-in users (and therefore
 *    the admin bar) are never stored OR served from this cache.
 *  - cart/checkout/account/auth/admin/api/livewire never cached.
 *  - Cached HTML contains no per-user state: the cart badge hydrates via
 *    the no-cache /cart/count endpoint.
 */
class PageCache
{
    /** One exclusion list shared with the LiteSpeed header middleware. */
    public const NO_CACHE_PATHS = [
        'cart*', 'checkout*', 'my-account*', 'login', 'register', 'password*',
        'admin*', 'api/*', 'livewire*', 'payment*', 'webhooks/*', 'wishlist*',
        'hmmail*', 'two-factor*', 'search*',
    ];

    public static function isLiteSpeedServer(): bool
    {
        return str_contains((string) request()->server('SERVER_SOFTWARE', ''), 'LiteSpeed');
    }

    public static function enabled(): bool
    {
        return match ((string) setting('performance.page_cache_enabled', 'auto')) {
            'on' => true,
            'off' => false,
            // auto: the LiteSpeed server already caches guests at the edge.
            default => ! self::isLiteSpeedServer(),
        };
    }

    public static function ttl(): int
    {
        return max(60, (int) setting('performance.page_cache_ttl', 3600));
    }

    /** Any content change → new version → every cached page regenerates. */
    public static function flush(): void
    {
        // Invalidation is an optimization — it must NEVER break the save that
        // triggered it. A broken cache backend (missing/unwritable
        // storage/framework/cache dirs after a permission mix-up) would
        // otherwise 500 every product/post save from the model-saved hook.
        // Worst case of swallowing: a stale guest page for one TTL.
        try {
            Cache::put('pagecache.version', (int) Cache::get('pagecache.version', 1) + 1);
        } catch (\Throwable $e) {
            \App\Models\ErrorLog::record($e);
        }
    }

    public static function cacheable(Request $request): bool
    {
        return self::enabled() && self::eligible($request);
    }

    /**
     * Guest-page eligibility WITHOUT the enabled() switch — shared with
     * optimizations (critical CSS) that apply even when this cache layer
     * is off (e.g. auto mode on a LiteSpeed server).
     */
    public static function eligible(Request $request): bool
    {
        if ($request->method() !== 'GET' || $request->expectsJson()) {
            return false;
        }

        foreach (self::NO_CACHE_PATHS as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return $request->user() === null;
    }

    public static function key(Request $request): string
    {
        // Tracking params must not fragment the cache — ad clicks land on
        // the same cached page as organic visits.
        $query = collect($request->query())
            ->except(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'msclkid'])
            ->sortKeys()
            ->all();

        $version = (int) Cache::get('pagecache.version', 1);

        return 'pagecache.v'.$version.'.'.md5($request->getHost().'|'.$request->path().'|'.http_build_query($query));
    }
}
