<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Seo\SchemaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoFeaturePackTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(array $overrides = []): Product
    {
        static $n = 0;
        $n++;

        $product = Product::create(array_merge([
            'name' => "Feed Widget {$n}", 'slug' => "feed-widget-{$n}", 'type' => 'simple',
            'price' => 100, 'status' => 'published', 'stock_status' => 'in_stock',
            'manage_stock' => false, 'sku' => "FW-{$n}",
        ], $overrides));

        $product->images()->create(['path' => "products/widget-{$n}.jpg", 'sort_order' => 0]);

        return $product;
    }

    // ── B1: Organization sameAs bug fix ───────────────────────────────

    public function test_organization_schema_builds_sameas_from_individual_social_settings(): void
    {
        Setting::set('seo.social_facebook', 'https://facebook.com/tereahub');
        Setting::set('seo.social_instagram', 'https://instagram.com/tereahub');

        $schema = app(SchemaGenerator::class)->organization();

        $this->assertSame(
            ['https://facebook.com/tereahub', 'https://instagram.com/tereahub'],
            $schema['sameAs'],
        );
    }

    // ── A: IndexNow improvements ──────────────────────────────────────

    public function test_publishing_a_page_pings_indexnow(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        Page::create(['title' => 'Dubai delivery', 'slug' => 'terea-delivery-dubai-test', 'content' => '<p>x</p>', 'status' => 'published']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'indexnow')
            && collect($request['urlList'])->contains(fn ($u) => str_contains($u, 'terea-delivery-dubai-test')));
    }

    public function test_bulk_indexnow_submit_sends_every_published_url(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $product = $this->makeProduct();
        Page::create(['title' => 'About', 'slug' => 'about-bulk-test', 'content' => '<p>x</p>', 'status' => 'published']);

        $this->artisan('seo:indexnow-submit')->assertSuccessful();

        Http::assertSent(function ($request) use ($product) {
            $urls = collect($request['urlList'] ?? []);

            return str_contains($request->url(), 'indexnow')
                && $urls->contains(fn ($u) => str_contains($u, $product->slug))
                && $urls->contains(fn ($u) => str_contains($u, 'about-bulk-test'))
                && $urls->contains(url('/'));
        });
    }

    public function test_bulk_submit_dry_run_sends_nothing(): void
    {
        Http::fake();

        // Mute the save-time observer ping so only the command is measured.
        Setting::set('seo.indexnow_enabled', false);
        $this->makeProduct();

        $this->artisan('seo:indexnow-submit', ['--dry-run' => true])->assertSuccessful();

        // Other observers may make unrelated HTTP calls (cache purge); the
        // command itself must not have touched IndexNow.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'indexnow'));
    }

    // ── D: Merchant product feed ──────────────────────────────────────

    public function test_feed_serves_required_google_merchant_fields(): void
    {
        Setting::set('general.currency', 'AED');
        $product = $this->makeProduct(['price' => 210, 'sale_price' => 195, 'gtin' => '01234567890123']);

        $response = $this->get('/feeds/products.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();

        $this->assertStringContainsString('<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">', $xml);
        $this->assertStringContainsString('<g:id>'.$product->sku.'</g:id>', $xml);
        $this->assertStringContainsString('<g:title>'.$product->name.'</g:title>', $xml);
        $this->assertStringContainsString('<g:price>210.00 AED</g:price>', $xml);
        $this->assertStringContainsString('<g:sale_price>195.00 AED</g:sale_price>', $xml);
        $this->assertStringContainsString('<g:availability>in_stock</g:availability>', $xml);
        $this->assertStringContainsString('<g:condition>new</g:condition>', $xml);
        $this->assertStringContainsString('<g:gtin>01234567890123</g:gtin>', $xml);
        $this->assertStringContainsString('<g:country>AE</g:country>', $xml);

        // Valid XML end to end.
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_feed_skips_imageless_products_and_flags_missing_gtin(): void
    {
        $withImage = $this->makeProduct(); // no gtin
        Product::create(['name' => 'No Image', 'slug' => 'no-image', 'type' => 'simple', 'price' => 5, 'status' => 'published', 'stock_status' => 'in_stock']);

        $xml = $this->get('/feeds/products.xml')->getContent();

        $this->assertStringContainsString($withImage->name, $xml);
        $this->assertStringNotContainsString('No Image', $xml);
        $this->assertStringContainsString('<g:identifier_exists>no</g:identifier_exists>', $xml);
    }

    public function test_feed_respects_exclusions_and_disable_toggle(): void
    {
        $kept = $this->makeProduct();
        $excluded = $this->makeProduct();
        Setting::set('seo.feed_exclude_product_ids', (string) $excluded->id);

        $xml = $this->get('/feeds/products.xml')->getContent();
        $this->assertStringContainsString($kept->name, $xml);
        $this->assertStringNotContainsString($excluded->name, $xml);

        Setting::set('seo.feed_enabled', false);
        // Bust the version-keyed cache the same way content changes do.
        \Illuminate\Support\Facades\Cache::put('sitemap.version', 99);
        $this->get('/feeds/products.xml')->assertNotFound();
    }
}
