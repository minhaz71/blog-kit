<?php

namespace Tests\Feature;

use Database\Seeders\TereaHubSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TereaHubHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_full_terea_hub_layout(): void
    {
        $this->seed(); // demo catalog + TEREA-less baseline
        $this->seed(TereaHubSeeder::class);

        $response = $this->get('/');

        $response->assertOk()
            // Announcement bar
            ->assertSee('1-hour delivery in Dubai, Sharjah &amp; Ajman', false)
            // Hero with art-directed banners + badge + both CTAs
            ->assertSee('Genuine IQOS TEREA, at your door in 1 hour')
            ->assertSee('homepage/hero-desktop.svg')
            ->assertSee('homepage/hero-mobile.svg')
            ->assertSee('Shop TEREA now')
            ->assertSee('Delivery areas')
            // USP strip
            ->assertSee('12-hour UAE-wide')
            ->assertSee('Pay on delivery')
            // Categories, promo banner, FAQ, testimonial, SEO block
            ->assertSee('Shop by edition')
            ->assertSee('homepage/banner-japan.svg')
            ->assertSee('Frequently asked questions')
            ->assertSee('Dubai Marina')
            ->assertSee('Buy IQOS TEREA in the UAE');
    }

    public function test_seeder_builds_editable_catalog_structure(): void
    {
        $this->seed();
        $this->seed(TereaHubSeeder::class);

        $this->assertDatabaseHas('categories', ['slug' => 'terea-uae', 'is_active' => true]);
        $this->assertDatabaseHas('categories', ['slug' => 'terea-japan', 'is_active' => true]);
        $this->assertSame('Terea Hub', setting('general.site_name'));
        $this->assertSame('#0f766e', setting('appearance.primary_color'));

        // Re-running must not duplicate sections or categories.
        $this->seed(TereaHubSeeder::class);
        $this->assertSame(10, \App\Models\HomepageSection::count());
        $this->assertSame(1, \App\Models\Category::where('slug', 'terea-uae')->count());
    }
}
