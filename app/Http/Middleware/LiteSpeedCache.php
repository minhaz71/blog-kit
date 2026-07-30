<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits LiteSpeed Cache headers so an LSWS/OpenLiteSpeed server (or QUIC.cloud)
 * can full-page cache guest traffic while keeping cart/checkout/account private.
 */
class LiteSpeedCache
{
    /** One exclusion list shared with the app-level page cache. */
    protected array $noCache = \App\Services\Performance\PageCache::NO_CACHE_PATHS;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Carry any queued cache purge out on THIS response's header —
        // LiteSpeed reads X-LiteSpeed-Purge off the PHP response, so a save
        // or delete purges via its own admin response with no extra outbound
        // HTTP call (any method, any route). This is what keeps deletes fast.
        if ($purge = app(\App\Services\Performance\LiteSpeedPurger::class)->pendingPurge()) {
            $response->headers->set('X-LiteSpeed-Purge', $purge);
        }

        if (! setting('performance.litespeed_enabled', true) || $request->method() !== 'GET') {
            return $response->headers->has('X-LiteSpeed-Cache-Control')
                ? $response
                : tap($response, fn ($r) => $r->headers->set('X-LiteSpeed-Cache-Control', 'no-cache'));
        }

        foreach ($this->noCache as $pattern) {
            if ($request->is($pattern)) {
                $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');

                return $response;
            }
        }

        if ($request->user()) {
            // Logged-in users get private cache (per-session).
            $ttl = (int) setting('performance.litespeed_private_ttl', 600);
            $response->headers->set('X-LiteSpeed-Cache-Control', "private, max-age={$ttl}");
        } else {
            $ttl = (int) setting('performance.litespeed_public_ttl', 3600);
            $response->headers->set('X-LiteSpeed-Cache-Control', "public, max-age={$ttl}");
            $response->headers->set('X-LiteSpeed-Tag', $this->tagsFor($request));
        }

        return $response;
    }

    /** Cache tags allow targeted purges (product update → purge product pages). */
    protected function tagsFor(Request $request): string
    {
        $tags = ['shopkit'];

        if ($request->route()) {
            $name = (string) $request->route()->getName();

            $tags[] = match (true) {
                str_starts_with($name, 'product.') => 'products',
                str_starts_with($name, 'category.') => 'categories',
                str_starts_with($name, 'blog.') => 'blog',
                str_starts_with($name, 'page.') => 'pages',
                $name === 'home' => 'home',
                default => 'misc',
            };

            foreach (['slug' => true] as $param => $_) {
                if ($value = $request->route()->parameter($param)) {
                    $slug = is_object($value) ? ($value->slug ?? null) : $value;

                    if (is_string($slug)) {
                        $tags[] = end($tags).'.'.$slug;
                    }
                }
            }
        }

        return implode(',', $tags);
    }
}
