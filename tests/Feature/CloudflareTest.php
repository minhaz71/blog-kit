<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Performance\Cloudflare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareTest extends TestCase
{
    use RefreshDatabase;

    protected function configure(): void
    {
        Setting::set('performance.cloudflare_email', 'owner@example.com');
        Setting::set('performance.cloudflare_api_key', 'test-global-key');
        Setting::set('performance.cloudflare_domain', 'example.com');
    }

    public function test_connect_stores_the_zone_id_and_normalizes_the_domain(): void
    {
        $this->configure();
        Setting::set('performance.cloudflare_domain', 'https://www.example.com/shop');

        Http::fake([
            'api.cloudflare.com/client/v4/zones*' => Http::response([
                'success' => true,
                'result' => [['id' => 'zone-abc-123', 'name' => 'example.com', 'status' => 'active']],
            ]),
        ]);

        $result = app(Cloudflare::class)->connect();

        $this->assertTrue($result['ok']);
        $this->assertSame('zone-abc-123', setting('performance.cloudflare_zone_id'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones?name=example.com'
                && $request->hasHeader('X-Auth-Email', 'owner@example.com')
                && $request->hasHeader('X-Auth-Key', 'test-global-key');
        });
    }

    public function test_failed_connect_clears_the_zone_id(): void
    {
        $this->configure();
        Setting::set('performance.cloudflare_zone_id', 'stale-zone');

        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => false,
                'result' => [],
                'errors' => [['message' => 'Invalid request headers']],
            ], 400),
        ]);

        $result = app(Cloudflare::class)->connect();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Invalid request headers', $result['message']);
        $this->assertNull(setting('performance.cloudflare_zone_id'));
    }

    public function test_connect_requires_configuration(): void
    {
        $result = app(Cloudflare::class)->connect();

        $this->assertFalse($result['ok']);
        Http::fake();
        Http::assertNothingSent();
    }

    public function test_purge_all_hits_the_purge_endpoint(): void
    {
        $this->configure();
        Setting::set('performance.cloudflare_zone_id', 'zone-abc-123');

        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        $result = app(Cloudflare::class)->purgeAll();

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-abc-123/purge_cache'
                && $request['purge_everything'] === true;
        });
    }

    public function test_purge_all_requires_a_connection(): void
    {
        $this->configure(); // no zone id → not connected

        $result = app(Cloudflare::class)->purgeAll();

        $this->assertFalse($result['ok']);
    }

    public function test_optimize_patches_zone_settings_and_reports_per_setting(): void
    {
        $this->configure();
        Setting::set('performance.cloudflare_zone_id', 'zone-abc-123');

        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone-abc-123/settings/early_hints' => Http::response([
                'success' => false, 'errors' => [['message' => 'not available on this plan']],
            ], 400),
            'api.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $result = app(Cloudflare::class)->optimize();

        $this->assertTrue($result['ok']);
        $this->assertSame('set to aggressive', $result['results']['cache_level']);
        $this->assertSame('set to off', $result['results']['rocket_loader']);
        $this->assertStringContainsString('failed', $result['results']['early_hints']);
        $this->assertStringContainsString('1 skipped', $result['message']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/cache_level') && $request['value'] === 'aggressive');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/rocket_loader') && $request['value'] === 'off');
    }
}
