<?php

namespace App\Http\Middleware;

use App\Services\Performance\CriticalCss;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inlines per-page critical CSS and makes the full stylesheet load async
 * for guest HTML pages. Runs INSIDE GuestPageCache (so the transformed
 * HTML is what gets cached) and outside MinifyHtml (so it sees the final
 * minified markup) — and on LiteSpeed servers, where the app cache is
 * off, the LS edge cache stores the transformed HTML too.
 */
class InlineCriticalCss
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $response instanceof \Illuminate\Http\Response
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')
        ) {
            try {
                $response->setContent(
                    app(CriticalCss::class)->transform((string) $response->getContent(), $request)
                );
            } catch (\Throwable) {
                // Optimization only — never break the page.
            }
        }

        return $response;
    }
}
