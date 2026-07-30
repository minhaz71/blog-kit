<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Seo\SearchConsoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchConsoleSyncTest extends TestCase
{
    use RefreshDatabase;

    /** Configure a REAL (freshly generated) service-account key pair. */
    protected function configureServiceAccount(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        Setting::set('seo.google_service_account_json', json_encode([
            'client_email' => 'seo-bot@test-project.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]));
        Setting::set('seo.gsc_property', 'sc-domain:tereahub.ae');
        Setting::set('seo.ga4_property_id', '123456789');
    }

    protected function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 3600]),
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response(['rows' => [
                ['keys' => ['https://tereahub.ae/product/terea-amber'], 'clicks' => 120, 'impressions' => 3400, 'ctr' => 0.0353, 'position' => 4.2],
                ['keys' => ['https://tereahub.ae/'], 'clicks' => 80, 'impressions' => 2100, 'ctr' => 0.0381, 'position' => 2.1],
            ]]),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => [
                ['dimensionValues' => [['value' => '/product/terea-amber']], 'metricValues' => [['value' => '95']]],
            ]]),
            'searchconsole.googleapis.com/v1/urlInspection/*' => Http::response(['inspectionResult' => [
                'indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed', 'lastCrawlTime' => '2026-07-08T04:00:00Z'],
            ]]),
        ]);
    }

    public function test_sync_skips_gracefully_without_credentials(): void
    {
        $this->artisan('seo:gsc-sync')
            ->expectsOutputToContain('Skipped')
            ->assertExitCode(0);

        $this->assertFalse(SearchConsoleService::configured());
    }

    public function test_sync_imports_performance_sessions_and_index_status(): void
    {
        $this->configureServiceAccount();
        $this->fakeGoogle();

        $this->artisan('seo:gsc-sync', ['--inspect' => 2])->assertExitCode(0);

        // Search analytics rows stored with GA4 sessions joined by path.
        $amber = DB::table('gsc_page_stats')->where('url', 'like', '%terea-amber%')->first();
        $this->assertSame(120, $amber->clicks);
        $this->assertSame(3400, $amber->impressions);
        $this->assertSame(3.53, (float) $amber->ctr);      // ratio → percent
        $this->assertSame(4.2, (float) $amber->position);
        $this->assertSame(95, $amber->organic_sessions);   // GA4 joined

        // Index status recorded for inspected URLs.
        $status = DB::table('index_statuses')->where('url', 'like', '%terea-amber%')->first();
        $this->assertSame('PASS', $status->verdict);
        $this->assertSame('Submitted and indexed', $status->coverage);

        // The JWT grant hit Google's token endpoint (real signing worked).
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token'));
    }
}
