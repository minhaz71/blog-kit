<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two admin-controlled site switches:
 *  - Discourage search engines (noindex + robots.txt) — hides from crawlers.
 *  - Maintenance mode (under construction) — hides from human guests.
 */
class SiteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function publishedProduct(): Product
    {
        return Product::create([
            'name' => 'Amber', 'slug' => 'amber', 'type' => 'simple',
            'price' => 30, 'status' => 'published',
        ]);
    }

    // ── Discourage search engines ─────────────────────────────────────
    public function test_indexable_by_default(): void
    {
        $this->publishedProduct();

        $this->get('/product/amber')->assertOk()->assertSee('index, follow', false);
        // The wildcard group must NOT block the whole site (AI training-bot
        // groups legitimately carry "Disallow: /", so check the * group only).
        $this->get('/robots.txt')->assertOk()->assertDontSee("User-agent: *\nDisallow: /\n", false);
    }

    public function test_discourage_indexing_forces_noindex_everywhere(): void
    {
        $this->publishedProduct();
        Setting::set('seo.discourage_indexing', true);

        $this->get('/product/amber')->assertOk()->assertSee('noindex, nofollow', false);
        $this->get('/robots.txt')->assertOk()->assertSee("User-agent: *\nDisallow: /", false);
    }

    // ── Maintenance / under construction ──────────────────────────────
    public function test_site_open_by_default(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_maintenance_mode_shows_construction_page_to_guests(): void
    {
        Setting::set('general.maintenance_mode', true);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Under construction', false);

        // Login stays reachable so staff can get in.
        $this->get('/login')->assertOk();
    }

    public function test_staff_bypass_maintenance_mode_but_customers_do_not(): void
    {
        Setting::set('general.maintenance_mode', true);
        $this->publishedProduct();

        // Staff (any role) preview the real site while it's closed.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('Super Admin');
        $this->actingAs($staff);
        $this->get('/')->assertOk();
        $this->get('/product/amber')->assertOk();

        // A logged-in customer (no role) still sees the construction page.
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertStatus(503);
    }
}
