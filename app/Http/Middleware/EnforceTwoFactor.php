<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If 2FA is enabled globally and the user has confirmed it, require the
 * per-session two_factor_verified flag before letting them past.
 */
class EnforceTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Skip on the challenge / setup / verify / disable / logout routes themselves.
        if ($request->routeIs('two-factor.*') || $request->routeIs('logout') || $request->is('livewire/*')) {
            return $next($request);
        }

        if ((bool) $user->two_factor_confirmed_at && ! $request->session()->get('two_factor_verified')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
