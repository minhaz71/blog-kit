<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify Google reCAPTCHA v2/v3 response on gated form POSTs. Only enforced
 * when reCAPTCHA is enabled AND a secret key is configured in security settings.
 */
class VerifyRecaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! setting('security.recaptcha_enabled')) {
            return $next($request);
        }

        $secret = setting('security.recaptcha_secret_key');
        if (! $secret) {
            return $next($request);  // Toggle is on but no secret configured — fail open, admin bug.
        }

        $token = (string) $request->input('g-recaptcha-response', $request->header('X-Recaptcha-Token', ''));
        if (! $token) {
            throw ValidationException::withMessages(['captcha' => 'Please complete the CAPTCHA challenge.']);
        }

        try {
            $resp = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (\Throwable) {
            // Fail closed if Google is unreachable — a well-run site shouldn't accept forms in that state.
            throw ValidationException::withMessages(['captcha' => 'CAPTCHA verification service unavailable. Please try again.']);
        }

        $body = $resp->json();
        $success = (bool) ($body['success'] ?? false);
        $score = $body['score'] ?? null;  // v3 only

        if (! $success || ($score !== null && (float) $score < 0.3)) {
            throw ValidationException::withMessages(['captcha' => 'CAPTCHA failed. Please try again.']);
        }

        return $next($request);
    }
}
