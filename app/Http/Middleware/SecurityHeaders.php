<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the standard browser security headers to every web response, so a
 * BlogKit site scores well on securityheaders.com out of the box (the grade the
 * user asked about). Defaults are chosen to HARDEN without breaking the app or
 * the Filament admin:
 *
 *  - X-Content-Type-Options: nosniff
 *  - X-Frame-Options: SAMEORIGIN            (clickjacking; admin still frames itself)
 *  - Referrer-Policy: strict-origin-when-cross-origin
 *  - Permissions-Policy: restrictive feature allow-list
 *  - Content-Security-Policy: upgrade-insecure-requests  (safe, non-breaking —
 *      forces https sub-resources without restricting sources, so inline
 *      scripts/styles and Vite assets keep working; tighten via settings)
 *  - Strict-Transport-Security                (HTTPS only)
 *
 * Everything is settings-overridable and each header is only set when the
 * server/CDN (LiteSpeed, Cloudflare) hasn't already set it — so we never
 * duplicate or fight an existing edge policy.
 */
class SecurityHeaders
{
    /** Restrictive but app-safe feature policy (payment/fullscreen kept for checkout & media). */
    protected const DEFAULT_PERMISSIONS = 'accelerometer=(), autoplay=(), camera=(), display-capture=(self), encrypted-media=(), fullscreen=*, geolocation=(self), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=*, picture-in-picture=(), usb=(), interest-cohort=()';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! (bool) setting('security.headers_enabled', true)) {
            return $response;
        }

        // Only add a header the upstream (server/CDN/plugin) hasn't already set.
        $set = function (string $name, string $value) use ($response) {
            if (trim($value) !== '' && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        };

        $set('X-Content-Type-Options', 'nosniff');
        $set('X-Frame-Options', (string) setting('security.frame_options', 'SAMEORIGIN'));
        $set('Referrer-Policy', (string) setting('security.referrer_policy', 'strict-origin-when-cross-origin'));
        $set('X-Permitted-Cross-Domain-Policies', 'none');
        $set('Content-Security-Policy', (string) setting('security.csp', 'upgrade-insecure-requests;'));
        $set('Permissions-Policy', (string) setting('security.permissions_policy', self::DEFAULT_PERMISSIONS));

        // HSTS only over HTTPS — a browser ignores it on http, and we must not
        // force HTTPS on a site still being set up without a certificate.
        if ($request->isSecure() && (bool) setting('security.hsts_enabled', true)) {
            $maxAge = (int) setting('security.hsts_max_age', 31536000); // 1 year
            $hsts = "max-age={$maxAge}; includeSubDomains";
            if ((bool) setting('security.hsts_preload', false)) {
                $hsts .= '; preload';
            }
            $set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }
}
