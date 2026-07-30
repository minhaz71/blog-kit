<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves an IP address to an ISO country code, cached for 30 days per IP.
 * Uses the free ip-api.com endpoint (no key, 45 req/min) and degrades
 * gracefully — a lookup failure returns null and never blocks a request.
 *
 * Private/reserved/loopback addresses short-circuit to null so local and
 * internal traffic is never geo-filtered.
 */
class GeoIp
{
    public function country(string $ip): ?string
    {
        if ($ip === '' || $this->isPrivate($ip)) {
            return null;
        }

        return Cache::remember("geoip.{$ip}", now()->addDays(30), function () use ($ip): ?string {
            try {
                $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,countryCode',
                ]);

                if ($response->ok() && $response->json('status') === 'success') {
                    return strtoupper((string) $response->json('countryCode')) ?: null;
                }
            } catch (\Throwable) {
                // Network/quota failure — never let geo lookup break a request.
            }

            return null;
        });
    }

    public function isPrivate(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
