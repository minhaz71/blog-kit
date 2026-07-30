<?php

namespace App\Services\Performance;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare integration for the Performance page: verify the account +
 * domain, purge the CF edge cache together with the local caches, and
 * push cache settings tuned to how this site actually behaves (guest
 * pages cacheable, cart/checkout dynamic, HTML minified at origin).
 *
 * Auth is the classic email + Global API Key pair, per the user-facing
 * "email / API key / domain" setup.
 */
class Cloudflare
{
    protected const API = 'https://api.cloudflare.com/client/v4';

    public function configured(): bool
    {
        return setting('performance.cloudflare_email')
            && setting('performance.cloudflare_api_key')
            && setting('performance.cloudflare_domain');
    }

    public function connected(): bool
    {
        return $this->configured() && setting('performance.cloudflare_zone_id');
    }

    /**
     * Look the domain up in the account's zones. Success stores the zone id
     * (all later calls need it); failure clears it so the UI shows the
     * true state.
     *
     * @return array{ok: bool, message: string}
     */
    public function connect(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Fill in the Cloudflare email, API key and domain first, then save.'];
        }

        $domain = $this->rootDomain();
        $response = $this->client()->get(self::API.'/zones', ['name' => $domain]);
        $zone = $response->json('result.0');

        if (! $response->successful() || ! $zone) {
            Setting::set('performance.cloudflare_zone_id', null);

            $error = $response->json('errors.0.message') ?: 'zone not found for this account';

            return ['ok' => false, 'message' => "Cloudflare connection failed: {$error}."];
        }

        Setting::set('performance.cloudflare_zone_id', $zone['id']);

        return ['ok' => true, 'message' => "Connected to Cloudflare zone “{$zone['name']}” (status: {$zone['status']})."];
    }

    /** @return array{ok: bool, message: string} */
    public function purgeAll(): array
    {
        if (! $this->connected()) {
            return ['ok' => false, 'message' => 'Cloudflare is not connected — check the connection first.'];
        }

        $zone = setting('performance.cloudflare_zone_id');
        $response = $this->client()->post(self::API."/zones/{$zone}/purge_cache", ['purge_everything' => true]);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message') ?: 'unknown error';

            return ['ok' => false, 'message' => "Cloudflare purge failed: {$error}."];
        }

        return ['ok' => true, 'message' => 'Cloudflare edge cache purged.'];
    }

    /**
     * Apply cache settings matched to this site's state. Returns a
     * per-setting result list so the admin sees exactly what changed
     * (some settings are plan-gated and fail individually).
     *
     * @return array{ok: bool, message: string, results: array<string, string>}
     */
    public function optimize(): array
    {
        if (! $this->connected()) {
            return ['ok' => false, 'message' => 'Cloudflare is not connected — check the connection first.', 'results' => []];
        }

        $zone = setting('performance.cloudflare_zone_id');

        $settings = [
            // Static assets cached aggressively; HTML stays origin-controlled
            // so the app/LiteSpeed cache headers keep authority over pages.
            'cache_level' => 'aggressive',
            // Match the origin page-cache TTL so browsers and the edge agree.
            'browser_cache_ttl' => PageCache::ttl() >= 14400 ? PageCache::ttl() : 14400,
            'always_online' => 'on',
            'brotli' => 'on',
            'early_hints' => 'on',
            // The origin already minifies HTML — CF rocket loader tends to
            // break Alpine, keep it off.
            'rocket_loader' => 'off',
        ];

        $results = [];
        $failures = 0;

        foreach ($settings as $name => $value) {
            $response = $this->client()->patch(self::API."/zones/{$zone}/settings/{$name}", ['value' => $value]);

            if ($response->successful()) {
                $results[$name] = is_int($value) ? "set to {$value}" : "set to {$value}";
            } else {
                $failures++;
                $results[$name] = 'failed: '.($response->json('errors.0.message') ?: 'not available on this plan');
            }
        }

        $applied = count($settings) - $failures;

        return [
            'ok' => $failures < count($settings),
            'message' => "Cloudflare optimized: {$applied} setting(s) applied".($failures ? ", {$failures} skipped" : '').'.',
            'results' => $results,
        ];
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-Auth-Email' => (string) setting('performance.cloudflare_email'),
            'X-Auth-Key' => (string) setting('performance.cloudflare_api_key'),
        ])->acceptJson()->timeout(15);
    }

    /** Zones are registered by root domain — strip any pasted www/subdomain noise. */
    protected function rootDomain(): string
    {
        $domain = strtolower(trim((string) setting('performance.cloudflare_domain')));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];

        return preg_replace('/^www\./', '', $domain);
    }
}
