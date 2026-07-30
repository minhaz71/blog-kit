<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards storefront ecommerce routes (shop, cart, checkout, webhooks,
 * wishlist, product reviews) behind the optional ecommerce module.
 *
 * The routes stay REGISTERED even when the module is off, so route('shop'),
 * route('product.show', …) and friends used throughout Blade, the SEO
 * manager and sitemaps still resolve for URL generation and never throw a
 * RouteNotFoundException. Actually visiting one while the module is off
 * returns 404 — the page simply does not exist for a pure blog site.
 */
class EnsureEcommerceEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ecommerce_enabled(), 404);

        return $next($request);
    }
}
