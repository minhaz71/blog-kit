<?php

namespace Tests\Feature;

use App\Models\NotFoundLog;
use App\Models\Product;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedirectMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('redirects.active');
    }

    public function test_active_301_redirect_sends_permanent_redirect(): void
    {
        Redirect::create([
            'source' => '/old-page',
            'target' => '/',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/old-page')
            ->assertStatus(301)
            ->assertRedirect('/');
    }

    public function test_changing_a_product_permalink_auto_301s_the_old_url(): void
    {
        $product = Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published',
        ]);

        // Rename the permalink.
        $product->update(['slug' => 'terea-amber-gold']);

        // The old URL 301-redirects to the new permalink, no manual redirect needed.
        $this->get('/product/terea-amber')
            ->assertStatus(301)
            ->assertRedirect($product->fresh()->url());
    }

    public function test_410_redirect_returns_gone(): void
    {
        Redirect::create([
            'source' => '/removed',
            'target' => '/',
            'status_code' => 410,
            'is_active' => true,
        ]);

        $this->get('/removed')->assertStatus(410);
    }

    public function test_inactive_redirect_does_not_fire(): void
    {
        Redirect::create([
            'source' => '/off',
            'target' => '/',
            'status_code' => 301,
            'is_active' => false,
        ]);

        // No redirect — request falls through and 404s normally.
        $response = $this->get('/off');
        $this->assertNotSame(301, $response->status());
    }

    public function test_404_is_logged_and_hit_count_increments(): void
    {
        $this->get('/definitely-not-a-page');
        $this->get('/definitely-not-a-page');

        $log = NotFoundLog::query()->where('url', 'like', '%/definitely-not-a-page%')->first();
        $this->assertNotNull($log, '404 log should be recorded.');
        $this->assertGreaterThanOrEqual(2, $log->hits);
    }
}
