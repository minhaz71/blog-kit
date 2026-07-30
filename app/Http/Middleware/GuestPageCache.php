<?php

namespace App\Http\Middleware;

use App\Services\Performance\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves guests from the app-level page cache — a HIT returns stored HTML
 * without running controllers, queries, or view rendering. See PageCache
 * for the layering/safety rules. X-Page-Cache: HIT|MISS|SKIP for
 * observability.
 */
class GuestPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PageCache::cacheable($request)) {
            return tap($next($request), fn (Response $r) => $r->headers->set('X-Page-Cache', 'SKIP'));
        }

        $key = PageCache::key($request);

        if (($cached = Cache::get($key)) !== null) {
            return response($cached['html'].$this->signature('hit', $cached), 200)
                ->header('Content-Type', $cached['content_type'])
                ->header('X-Page-Cache', 'HIT');
        }

        $response = $next($request);
        $contentType = (string) $response->headers->get('Content-Type', 'text/html; charset=UTF-8');

        // Store only successful HTML pages — and never one rendered with
        // flash data (e.g. "added to cart" notice after a redirect), which
        // is one-off content that must not be replayed to other visitors.
        $hasFlash = $request->hasSession()
            && count((array) $request->session()->get('_flash.old', [])) > 0;

        if ($response->getStatusCode() === 200 && str_contains($contentType, 'text/html') && ! $hasFlash) {
            $meta = [
                'render_time' => $this->elapsed(),
                'generated_at' => now()->format('Y-m-d H:i:s T'),
            ];

            // The stored copy stays clean — the signature is appended
            // per-serve so its timings are always the truth.
            Cache::put($key, [
                'html' => $response->getContent(),
                'content_type' => $contentType,
            ] + $meta, PageCache::ttl());

            $response->setContent($response->getContent().$this->signature('miss', $meta));
        }

        $response->headers->set('X-Page-Cache', 'MISS');

        return $response;
    }

    /**
     * LiteSpeed-style signature comment at the bottom of the HTML, so
     * "view source" instantly answers: cached or not, and how fast.
     */
    protected function signature(string $type, array $meta): string
    {
        $brand = 'Hemdox Ecommerce CRM v'.config('app.version', '1.0.0');
        $served = number_format($this->elapsed(), 3);

        if ($type === 'hit') {
            $rendered = isset($meta['render_time']) ? number_format((float) $meta['render_time'], 3) : null;

            return "\n<!-- Page served from cache in {$served}s"
                .($rendered ? " (originally rendered in {$rendered}s, cached {$meta['generated_at']})" : '')
                ." — site optimized with {$brand} -->";
        }

        return "\n<!-- Page rendered in {$served}s and cached for the next visitors — site optimized with {$brand} -->";
    }

    /** Seconds since the request hit PHP. */
    protected function elapsed(): float
    {
        return defined('LARAVEL_START')
            ? microtime(true) - LARAVEL_START
            : (microtime(true) - (float) request()->server('REQUEST_TIME_FLOAT', microtime(true)));
    }
}
