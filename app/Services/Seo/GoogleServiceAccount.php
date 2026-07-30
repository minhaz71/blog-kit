<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Google service-account auth: builds a self-signed RS256 JWT from
 * the service account JSON (pasted in SEO settings) and exchanges it for a
 * short-lived access token — no SDK dependency. The same account powers
 * Search Console AND GA4 (add it as a user on both properties).
 */
class GoogleServiceAccount
{
    public static function configured(): bool
    {
        $creds = self::credentials();

        return isset($creds['client_email'], $creds['private_key']);
    }

    /** @return array<string, mixed> */
    public static function credentials(): array
    {
        return (array) json_decode((string) setting('seo.google_service_account_json', ''), true);
    }

    /** Access token for the given scopes, cached until near expiry. */
    public function accessToken(array $scopes): string
    {
        $creds = self::credentials();

        if (! isset($creds['client_email'], $creds['private_key'])) {
            throw new RuntimeException('No Google service account configured — paste its JSON in SEO settings → Integrations.');
        }

        $cacheKey = 'google-sa-token:'.md5($creds['client_email'].implode(' ', $scopes));

        return Cache::remember($cacheKey, 3000, function () use ($creds, $scopes): string {
            $now = time();

            $jwt = $this->encode(['alg' => 'RS256', 'typ' => 'JWT'])
                .'.'.$this->encode([
                    'iss' => $creds['client_email'],
                    'scope' => implode(' ', $scopes),
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]);

            if (! openssl_sign($jwt, $signature, $creds['private_key'], 'sha256WithRSAEncryption')) {
                throw new RuntimeException('Could not sign the Google JWT — the service account private key looks invalid.');
            }

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '='),
            ])->throw()->json();

            return (string) $response['access_token'];
        });
    }

    protected function encode(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }
}
