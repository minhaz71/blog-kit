<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wrong URLs must show the branded 404 page (search box + home button) —
 * never a bare server error.
 */
class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_page_shows_branded_404_with_search_and_home(): void
    {
        $response = $this->get('/this/page/does-not-exist');

        $response->assertNotFound();
        $response->assertSee("We can't find that page", escape: false);
        // Product search form wired to the real search route.
        $response->assertSee('action="'.url('/search').'"', escape: false);
        $response->assertSee('Search products');
        // Escape hatches.
        $response->assertSee('Go to homepage');
        $response->assertSee('Browse all products');
    }

    public function test_missing_build_asset_paths_get_the_branded_404_too(): void
    {
        // Missing files under public asset directories must render the
        // branded page (no firewall interference, no bare error) — the
        // .htaccess routes these to Laravel when no real file matches.
        $this->get('/build/assets/does-not-exist.css')
            ->assertNotFound()
            ->assertSee("We can't find that page", escape: false);

        $this->get('/fonts/missing-font.woff2')
            ->assertNotFound()
            ->assertSee('Go to homepage');
    }

    public function test_404_page_is_noindex(): void
    {
        $this->get('/definitely-missing')
            ->assertNotFound()
            ->assertSee('noindex', escape: false);
    }
}
