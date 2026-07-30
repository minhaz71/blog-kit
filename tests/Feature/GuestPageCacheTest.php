<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Performance\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestPageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function product(): Product
    {
        return Product::create([
            'name' => 'Terea Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
    }

    public function test_guest_second_request_is_served_from_cache(): void
    {
        $product = $this->product();

        $this->get($product->url())->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $this->get($product->url())->assertOk()->assertHeader('X-Page-Cache', 'HIT')->assertSee('Terea Amber');
    }

    public function test_logged_in_users_are_never_cached_or_served_from_cache(): void
    {
        $staff = $this->staff();
        $product = $this->product();

        // Staff first: response (with admin bar) must NOT be stored.
        $this->actingAs($staff)->get($product->url())
            ->assertOk()->assertHeader('X-Page-Cache', 'SKIP')->assertSee('adminbar');

        // Fresh guest gets a MISS — never the staff HTML.
        $guest = $this->flushSession();
        auth()->logout();
        $this->get($product->url())
            ->assertOk()->assertHeader('X-Page-Cache', 'MISS')->assertDontSee('adminbar');

        // And once the guest page IS cached, staff still bypass it.
        $this->get($product->url())->assertHeader('X-Page-Cache', 'HIT');
        $this->actingAs($staff)->get($product->url())
            ->assertHeader('X-Page-Cache', 'SKIP')->assertSee('adminbar');
    }

    public function test_cached_guest_html_never_contains_the_admin_bar(): void
    {
        $staff = $this->staff();
        $product = $this->product();

        $this->get($product->url())->assertHeader('X-Page-Cache', 'MISS')->assertDontSee('adminbar');
        $hit = $this->actingAs($staff)->get($product->url());

        // Staff on a cached URL: bypass, fresh render with the bar.
        $hit->assertHeader('X-Page-Cache', 'SKIP')->assertSee('adminbar');
    }

    public function test_dynamic_paths_are_never_cached(): void
    {
        $this->get('/cart')->assertOk()->assertHeader('X-Page-Cache', 'SKIP');
        $this->get('/cart')->assertOk()->assertHeader('X-Page-Cache', 'SKIP');
    }

    public function test_content_change_invalidates_cached_pages(): void
    {
        $product = $this->product();

        $this->get($product->url())->assertHeader('X-Page-Cache', 'MISS');
        $this->get($product->url())->assertHeader('X-Page-Cache', 'HIT');

        $product->update(['name' => 'Terea Amber Updated']);

        $this->get($product->url())
            ->assertHeader('X-Page-Cache', 'MISS')
            ->assertSee('Terea Amber Updated');
    }

    public function test_purge_all_flush_invalidates_cached_pages(): void
    {
        $product = $this->product();

        $this->get($product->url())->assertHeader('X-Page-Cache', 'MISS');
        $this->get($product->url())->assertHeader('X-Page-Cache', 'HIT');

        PageCache::flush();

        $this->get($product->url())->assertHeader('X-Page-Cache', 'MISS');
    }

    public function test_page_cache_can_be_forced_off(): void
    {
        Setting::set('performance.page_cache_enabled', 'off');
        $product = $this->product();

        $this->get($product->url())->assertHeader('X-Page-Cache', 'SKIP');
        $this->get($product->url())->assertHeader('X-Page-Cache', 'SKIP');
    }

    public function test_tracking_params_share_one_cache_entry(): void
    {
        $product = $this->product();

        $this->get($product->url())->assertHeader('X-Page-Cache', 'MISS');
        $this->get($product->url().'?utm_source=google&gclid=abc123')->assertHeader('X-Page-Cache', 'HIT');
    }

    public function test_cart_count_endpoint_returns_count_and_csrf_token_uncached(): void
    {
        $response = $this->get('/cart/count');

        $response->assertOk()
            ->assertHeader('X-Page-Cache', 'SKIP')
            ->assertJsonStructure(['count', 'token'])
            ->assertJson(['count' => 0]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_cache_signature_comment_tells_cached_from_fresh(): void
    {
        $product = $this->product();

        // Fresh render: "rendered … and cached" signature with app version.
        $this->get($product->url())
            ->assertSee('Page rendered in', false)
            ->assertSee('and cached for the next visitors', false)
            ->assertSee('Hemdox Ecommerce CRM v'.config('app.version'), false);

        // Cache hit: serve time + original render time + cached-at stamp.
        $this->get($product->url())
            ->assertHeader('X-Page-Cache', 'HIT')
            ->assertSee('Page served from cache in', false)
            ->assertSee('originally rendered in', false)
            ->assertSee('site optimized with Hemdox Ecommerce CRM', false);
    }

    public function test_header_ships_no_server_rendered_cart_count(): void
    {
        $product = $this->product();

        // The cached HTML must always start the badge at 0 (JS hydrates it).
        $this->get($product->url())->assertOk()->assertSee('cartCount: 0', false);
    }
}
