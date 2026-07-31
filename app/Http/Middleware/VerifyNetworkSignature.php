<?php

namespace App\Http\Middleware;

use App\Services\Network\NetworkIdentity;
use App\Services\Network\NetworkSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound multisite-network API calls (this install acting as a
 * spoke). Verifies the HMAC signature against THIS site's own shared secret,
 * enforces a timestamp window, and rejects replayed nonces. No session, no
 * cookies — pure signed request, like the payment webhooks.
 */
class VerifyNetworkSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // The network module must be on for any install to accept network calls.
        abort_unless(network_enabled(), 404);

        $key = (string) $request->header(NetworkSignature::HEADER_KEY, '');
        $timestamp = (string) $request->header(NetworkSignature::HEADER_TIMESTAMP, '');
        $nonce = (string) $request->header(NetworkSignature::HEADER_NONCE, '');
        $signature = (string) $request->header(NetworkSignature::HEADER_SIGNATURE, '');

        $ourKey = NetworkIdentity::key();
        $ourSecret = NetworkIdentity::secret();

        if ($key === '' || $timestamp === '' || $nonce === '' || $signature === '' || ! $ourKey || ! $ourSecret) {
            return $this->deny('Missing or unconfigured network credentials.');
        }

        // The caller must address THIS spoke by its own key.
        if (! hash_equals($ourKey, $key)) {
            return $this->deny('Unknown network key.');
        }

        // Timestamp window (anti-replay part 1).
        $tolerance = (int) config('blogkit.network.timestamp_tolerance', 300);
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $tolerance) {
            return $this->deny('Stale or invalid timestamp.');
        }

        // Signature.
        $body = $request->getContent();
        if (! NetworkSignature::verify($ourSecret, $request->method(), $request->path(), $timestamp, $nonce, $body, $signature)) {
            return $this->deny('Bad signature.');
        }

        // Nonce replay guard (anti-replay part 2): each nonce is single-use
        // within the TTL. add() is atomic, so a replay loses the race.
        $ttl = (int) config('blogkit.network.nonce_ttl', 600);
        if (! Cache::add('netnonce:'.$key.':'.$nonce, 1, $ttl)) {
            return $this->deny('Replayed nonce.');
        }

        return $next($request);
    }

    protected function deny(string $reason): Response
    {
        return response()->json(['ok' => false, 'error' => $reason], 401);
    }
}
