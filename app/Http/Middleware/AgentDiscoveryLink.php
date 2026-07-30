<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds an RFC 8288 `Link` response header advertising the store's read-only
 * agent-discovery surfaces (API catalogue + sitemap). Invisible to users and
 * inert for SEO — purely a machine-discovery hint.
 *
 * Registered OUTSIDE the guest page cache (earlier in the appended stack) so
 * the header is applied on cache HITs too, not just fresh renders. Skips the
 * admin panel and non-HTML responses (the .md/JSON surfaces carry their own).
 */
class AgentDiscoveryLink
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET')
            && ! $request->is('admin', 'admin/*', 'api/*', 'livewire/*')
            && ! $response->headers->has('Link')
            && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            $links = implode(', ', [
                '<'.url('/.well-known/api-catalog').'>; rel="api-catalog"; type="application/linkset+json"',
                '<'.route('sitemap.index').'>; rel="sitemap"; type="application/xml"',
            ]);
            $response->headers->set('Link', $links);
        }

        return $response;
    }
}
