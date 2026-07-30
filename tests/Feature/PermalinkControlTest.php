<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleRedirects;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Permalinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PermalinkControlTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $name): Product
    {
        return Product::create([
            'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
            'price' => 30, 'status' => 'published',
        ]);
    }

    protected function category(string $name): Category
    {
        return Category::create(['name' => $name, 'slug' => str($name)->slug(), 'is_active' => true]);
    }

    /** Fully reload storefront routes so a changed base takes effect. */
    protected function reloadRoutes(): void
    {
        $this->app['router']->setRoutes(new \Illuminate\Routing\RouteCollection);
        \Illuminate\Support\Facades\Route::middleware('web')->group(base_path('routes/web.php'));
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    /** Drive the 404 → redirect path of the middleware directly. */
    protected function redirectFor(string $path): ?Response
    {
        $mw = app(HandleRedirects::class);
        $response = $mw->handle(Request::create($path, 'GET'), fn () => new Response('not found', 404));

        return $response->isRedirect() ? $response : null;
    }

    // ── URL generation ────────────────────────────────────────────────
    public function test_defaults_preserve_the_original_urls(): void
    {
        $this->assertSame(url('/product/amber'), Permalinks::product('amber'));
        $this->assertSame(url('/category/devices'), Permalinks::category('devices'));
        $this->assertSame('blog', Permalinks::blogBase());
    }

    public function test_renaming_and_clearing_bases_reshapes_urls(): void
    {
        Setting::set('seo.product_base', 'shop');
        Setting::set('seo.category_base', '');   // root-level
        Setting::set('seo.blog_base', 'news');

        $this->assertSame(url('/shop/amber'), Permalinks::product('amber'));
        $this->assertSame(url('/devices'), Permalinks::category('devices'));
        $this->assertSame('news', Permalinks::blogBase());
    }

    // ── Validation ────────────────────────────────────────────────────
    public function test_validation_rules(): void
    {
        $this->assertNull(Permalinks::validate('shop-2'));
        $this->assertNull(Permalinks::validate(''));                       // root-level ok
        $this->assertNotNull(Permalinks::validate('', [], allowEmpty: false)); // blog can't be empty
        $this->assertNotNull(Permalinks::validate('cart'));                // reserved
        $this->assertNotNull(Permalinks::validate('a/b'));                 // not a single segment
        $this->assertNotNull(Permalinks::validate('shop', ['shop']));      // duplicate of another base
    }

    // ── Default routes still serve ────────────────────────────────────
    public function test_default_product_and_category_routes_serve(): void
    {
        $product = $this->product('Amber Kazakhstan');
        $category = $this->category('Devices');

        $this->get('/product/amber-kazakhstan')->assertOk();
        $this->get('/category/devices')->assertOk();
    }

    // ── Old URLs 301 to the new shape (middleware unit) ───────────────
    public function test_old_prefixed_url_redirects_after_rename(): void
    {
        $this->product('Amber');
        Setting::set('seo.product_base', 'shop');

        $redirect = $this->redirectFor('/product/amber');

        $this->assertNotNull($redirect, 'old /product URL should redirect');
        $this->assertSame(301, $redirect->getStatusCode());
        $this->assertStringEndsWith('/shop/amber', $redirect->headers->get('Location'));
    }

    public function test_old_category_url_redirects_to_root_level(): void
    {
        $this->category('Devices');
        Setting::set('seo.category_base', '');

        $redirect = $this->redirectFor('/category/devices');

        $this->assertNotNull($redirect);
        $this->assertStringEndsWith('/devices', $redirect->headers->get('Location'));
    }

    // ── Root-level serving via the unified resolver (routes reloaded) ──
    public function test_root_level_category_serves_and_page_still_resolves(): void
    {
        $category = $this->category('Devices');
        \App\Models\Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => 'Hi']);

        Setting::set('seo.category_base', '');
        $this->reloadRoutes();

        $this->get('/devices')->assertOk();   // category at root
        $this->get('/about')->assertOk();      // CMS page still works
        $this->get('/definitely-nothing-here')->assertNotFound();
    }
}
