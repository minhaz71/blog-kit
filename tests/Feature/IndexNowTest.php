<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\Seo\IndexNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_is_auto_generated_and_served_from_key_file(): void
    {
        $key = IndexNow::key();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $key);
        $this->assertSame($key, IndexNow::key(), 'Key must be stable once generated.');

        $this->get('/'.$key.'.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee($key);

        // Wrong key → 404, never leaks the real one.
        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();
    }

    public function test_publishing_a_product_pings_indexnow_with_spec_payload(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $product = Product::create([
            'name' => 'Terea Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published',
        ]);

        Http::assertSent(function ($request) use ($product) {
            return str_contains($request->url(), 'api.indexnow.org')
                && $request['key'] === IndexNow::key()
                && str_contains($request['keyLocation'], IndexNow::key().'.txt')
                && in_array($product->url(), $request['urlList'], true);
        });

        // Same URL within 5 minutes → deduplicated, no second ping.
        $product->update(['price' => 12]);
        $this->assertSame(1, count(Http::recorded(fn ($r) => str_contains($r->url(), 'api.indexnow.org'))));
    }

    public function test_deleting_content_does_not_ping_indexnow(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $product = Product::create([
            'name' => 'Terea Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published',
        ]);

        // Publishing pinged once; deleting must add no further ping —
        // removing content is not an IndexNow-worthy signal.
        $before = count(Http::recorded(fn ($r) => str_contains($r->url(), 'api.indexnow.org')));
        $product->delete();
        $after = count(Http::recorded(fn ($r) => str_contains($r->url(), 'api.indexnow.org')));

        $this->assertSame($before, $after);
    }

    public function test_drafts_and_disabled_toggle_never_ping(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        Product::create([
            'name' => 'Draft', 'slug' => 'draft-p', 'type' => 'simple',
            'price' => 10, 'status' => 'draft',
        ]);

        Setting::set('seo.indexnow_enabled', false);
        Product::create([
            'name' => 'Off', 'slug' => 'off-p', 'type' => 'simple',
            'price' => 10, 'status' => 'published',
        ]);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.indexnow.org'));
    }
}
