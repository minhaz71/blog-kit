<?php

namespace Tests\Feature;

use App\Models\PageSpeedSnapshot;
use App\Services\Seo\PageSpeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageSpeedTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_parses_psi_response_into_core_metrics(): void
    {
        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => [
                    'categories' => ['performance' => ['score' => 0.87]],
                    'audits' => [
                        'largest-contentful-paint' => ['numericValue' => 2340.5],
                        'cumulative-layout-shift' => ['numericValue' => 0.041],
                    ],
                ],
                'loadingExperience' => [
                    'metrics' => ['INTERACTION_TO_NEXT_PAINT' => ['percentile' => 180]],
                ],
            ]),
        ]);

        $snapshot = app(PageSpeedService::class)->snapshot(url('/'), 'mobile');

        $this->assertSame(87, $snapshot->performance);
        $this->assertSame(2.34, (float) $snapshot->lcp);
        $this->assertSame(0.041, (float) $snapshot->cls);
        $this->assertSame(180, $snapshot->inp);
        $this->assertSame(1, PageSpeedSnapshot::count());
    }

    public function test_failed_psi_call_records_nothing(): void
    {
        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response(['error' => 'quota'], 429)]);

        $this->assertNull(app(PageSpeedService::class)->snapshot(url('/'), 'mobile'));
        $this->assertSame(0, PageSpeedSnapshot::count());
    }

    public function test_key_urls_start_with_home_and_include_categories(): void
    {
        \App\Models\Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true]);

        $urls = app(PageSpeedService::class)->keyUrls();

        $this->assertSame(url('/'), $urls[0]);
        $this->assertContains(url('/category/terea-uae'), $urls);
    }
}
