<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Development / "site under construction" mode (Admin → General settings).
 *
 * Rule (in order):
 *   1. Maintenance off              → everyone through.
 *   2. Always-open path (allow-list)→ through, so login/admin/assets/webhooks
 *      stay reachable and staff can ALWAYS sign in and turn it off. This runs
 *      FIRST, before the auth check, so even a logged-OUT owner can reach the
 *      login screen — the anti-lockout guarantee.
 *   3. Signed-in STAFF (owner/team) → through: they preview/work on the real
 *      site while it is closed.
 *   4. Everyone else (guests + logged-in customers) → branded 503 notice.
 *
 * NOTE: the Filament admin panel uses its own middleware stack and never runs
 * this middleware, so /admin is unaffected regardless — the allow-list entry
 * is belt-and-braces. Registered as the FIRST appended web middleware: after
 * session start (so we know who is logged in) and before the guest page cache
 * (so a cached page can never leak while the site is closed).
 */
class MaintenanceMode
{
    /** Paths that must stay reachable even while closed. */
    protected array $allow = [
        'admin', 'admin/*',
        'login', 'logout', 'register',
        'forgot-password', 'reset-password', 'reset-password/*',
        'two-factor', 'two-factor/*', 'verify-email/*',
        'up', 'robots.txt', 'build/*', 'storage/*', 'livewire/*', 'webhooks/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('general.maintenance_mode')) {
            return $next($request);
        }

        // Anti-lockout: login/admin/assets/etc. are always reachable, checked
        // BEFORE auth so a signed-out owner can still reach the sign-in screen.
        if ($request->is(...$this->allow)) {
            return $next($request);
        }

        // Only STAFF preview the real site; customers (logged in or not) and
        // guests see the notice. Guard the call so a customer User without the
        // method can never error here.
        $user = $request->user();
        if ($user && method_exists($user, 'isStaff') && $user->isStaff()) {
            return $next($request);
        }

        // Explicitly uncacheable at EVERY layer: this response short-circuits
        // before the LiteSpeedCache middleware, so without these headers an
        // edge cache (LiteSpeed/Cloudflare) could store the maintenance page
        // and keep serving it — even to signed-in staff — after they sign in.
        return response()
            ->view('errors.maintenance', ['status' => 503], 503)
            ->header('Retry-After', '3600')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('X-LiteSpeed-Cache-Control', 'no-cache');
    }
}
