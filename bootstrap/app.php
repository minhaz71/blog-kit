<?php

use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\Firewall;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\LiteSpeedCache;
use App\Http\Middleware\VerifyRecaptcha;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Firewall runs first on every request.
        $middleware->prepend(Firewall::class);

        // HandleRedirects must run BEFORE route model binding so it can intercept
        // requests to non-existent slugs (which otherwise throw ModelNotFound before
        // any appended middleware gets to see the response).
        $middleware->web(prepend: [HandleRedirects::class]);
        $middleware->web(append: [
            // Development / "under construction" mode. First appended so it
            // runs after the session starts (we know who's logged in) but
            // before the guest page cache — a cached page must never leak
            // while the site is closed to the public.
            \App\Http\Middleware\MaintenanceMode::class,
            // Markdown-for-agents: on `Accept: text/markdown`, serve the clean
            // markdown representation. Before the page cache so a markdown
            // request is never answered with cached HTML.
            \App\Http\Middleware\NegotiateMarkdown::class,
            // RFC 8288 Link header for agent discovery. Sits OUTSIDE the guest
            // page cache (before it here) so the header is applied on cache
            // HITs too — the cache stores only HTML, not this header.
            \App\Http\Middleware\AgentDiscoveryLink::class,
            // Browser security headers (CSP, HSTS, X-Frame-Options, …). BEFORE
            // the guest page cache so they apply on cache HITs too — the cache
            // stores only HTML, not these headers.
            \App\Http\Middleware\SecurityHeaders::class,
            // Outermost of the three: a page-cache HIT returns stored
            // (already-minified) HTML without re-running Minify/controllers.
            // Auto-disabled on LiteSpeed servers, where the LS headers
            // below make the SERVER cache guest pages at the edge instead.
            \App\Http\Middleware\GuestPageCache::class,
            // Critical CSS inside the page cache (transformed HTML is what
            // gets stored) and outside Minify (sees final markup).
            \App\Http\Middleware\InlineCriticalCss::class,
            // Minify before LiteSpeedCache so LS stores minified HTML.
            \App\Http\Middleware\MinifyHtml::class,
            LiteSpeedCache::class,
            EnforceTwoFactor::class,
        ]);

        // Named middleware aliases.
        $middleware->alias([
            'recaptcha' => VerifyRecaptcha::class,
            '2fa' => EnforceTwoFactor::class,
            // 404s storefront ecommerce routes when the store module is off.
            'ecommerce' => \App\Http\Middleware\EnsureEcommerceEnabled::class,
            // HMAC-verifies inbound multisite-network API calls.
            'network.signed' => \App\Http\Middleware\VerifyNetworkSignature::class,
        ]);

        $middleware->api(prepend: [
            'throttle:api',
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*', // gateway webhooks authenticate via signature verification
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api/* always gets JSON; AJAX endpoints on web routes (checkout
        // shipping-options etc.) must ALSO get JSON errors, not an HTML
        // redirect the frontend JS can't parse.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Persist real application errors to the admin-only error log
        // (deduped, noise-filtered). Never throws.
        $exceptions->report(function (Throwable $e): void {
            \App\Models\ErrorLog::record($e);
        });

        // Never leak internal code (stack traces, file paths, SQL) to the
        // public. Non-staff always get the branded friendly error page, even
        // if APP_DEBUG is on; staff/Super Admin keep Laravel's detailed page
        // so they can debug. The full technical detail lives in the log above.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null; // JSON handling above
            }

            // Only take over genuine unhandled SERVER errors. Everything with
            // its own render behavior — auth redirects, validation redirects,
            // 404s, 4xx — must pass through untouched (same filter the log uses).
            if (! \App\Models\ErrorLog::shouldRecord($e)) {
                return null;
            }

            $user = $request->user();
            if ($user && method_exists($user, 'isStaff') && $user->isStaff()) {
                return null; // staff keep Laravel's detailed page for debugging
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            return response()->view('errors.friendly', ['status' => $status], $status);
        });
    })->create();
