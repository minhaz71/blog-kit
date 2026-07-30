<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Performance\CriticalCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalCssTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (glob(public_path('build/assets/app-*.css')) === []) {
            $this->markTestSkipped('Vite build missing — run npm run build first.');
        }
    }

    protected function product(): Product
    {
        return Product::create([
            'name' => 'Terea Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
    }

    public function test_guest_pages_get_inline_critical_css_and_async_stylesheet(): void
    {
        $product = $this->product();

        $response = $this->get($product->url())->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<style id="critical-css">', $html);
        // The full stylesheet no longer blocks rendering…
        $this->assertMatchesRegularExpression('/<link[^>]*app-[^"]+\.css"[^>]*media="print"/', $html);
        // …but still loads for JS-disabled visitors.
        $this->assertMatchesRegularExpression('/<noscript><link rel="stylesheet"[^>]*app-[^"]+\.css/', $html);
    }

    public function test_critical_css_is_baked_into_the_page_cache(): void
    {
        $product = $this->product();

        $this->get($product->url())->assertHeader('X-Page-Cache', 'MISS');
        $this->get($product->url())
            ->assertHeader('X-Page-Cache', 'HIT')
            ->assertSee('<style id="critical-css">', false);
    }

    public function test_logged_in_users_get_the_untouched_stylesheet(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('Super Admin');
        $product = $this->product();

        $html = $this->actingAs($staff)->get($product->url())->assertOk()->getContent();

        $this->assertStringNotContainsString('critical-css', $html);
    }

    public function test_can_be_disabled_in_settings(): void
    {
        Setting::set('performance.critical_css_enabled', false);
        $product = $this->product();

        $html = $this->get($product->url())->assertOk()->getContent();

        $this->assertStringNotContainsString('critical-css', $html);
    }

    public function test_generator_follows_the_critical_selection_rulebook(): void
    {
        $html = '<body>'
            .'<header class="site-nav">'
            .'<div x-cloak class="mobile-drawer"><span class="drawer-item">menu</span></div>'
            .'</header>'
            .'<section class="hero" id="hero"><p>Above the fold</p></section>'
            .'<!--critical-fold-->'
            .'<section class="related-products">below fold</section>'
            .'<footer class="site-footer">footer</footer>'
            .'</body>';

        $css = '.site-nav{display:flex}'
            .'.hero{padding:2rem;transition:all .3s ease;animation:fade 1s}'
            .'.mobile-drawer{position:fixed}'
            .'.drawer-item{color:red}'
            .'.related-products{margin-top:4rem}'
            .'.site-footer{background:black}'
            .'.hero:hover{opacity:.9}'
            .'.unused-anywhere{color:blue}'
            .'#hero{margin:0}'
            .'#gone{margin:1px}'
            .'p{padding:0}'
            .'[x-cloak]{display:none}'
            .'@keyframes fade{from{opacity:0}}'
            .'@font-face{font-family:X;src:url(x.woff)}'
            .'@media (min-width:640px){.hero{padding:4rem}.site-footer{color:white}}'
            .'.pd-content h2::before{content:"{brace}"}'
            .':where(.site-footer, .site-nav) strong{font-weight:700}';

        $critical = app(CriticalCss::class)->generate($html, $css);

        // INCLUDE: layout/nav, hero, ids, typography, visibility resets.
        $this->assertStringContainsString('.site-nav{display:flex}', $critical);
        $this->assertStringContainsString('.hero{padding:2rem;', $critical);
        $this->assertStringContainsString('#hero{margin:0}', $critical);
        $this->assertStringContainsString('p{padding:0}', $critical);
        $this->assertStringContainsString('[x-cloak]{display:none}', $critical);
        $this->assertStringContainsString('@media (min-width:640px){.hero{padding:4rem}}', $critical);
        $this->assertStringContainsString('@font-face', $critical);
        // :where classes are alternatives, never hard requirements.
        $this->assertStringContainsString('strong{font-weight:700}', $critical);

        // EXCLUDE: hidden-at-load elements (drawer), lower-page (footer,
        // below the fold marker), animations, hover states, unused rules.
        $this->assertStringNotContainsString('.mobile-drawer', $critical);
        $this->assertStringNotContainsString('.drawer-item', $critical);
        $this->assertStringNotContainsString('.related-products', $critical);
        $this->assertStringNotContainsString('.site-footer{', $critical);
        $this->assertStringNotContainsString('.site-footer{color:white}', $critical);
        $this->assertStringNotContainsString(':hover', $critical);
        $this->assertStringNotContainsString('@keyframes', $critical);
        $this->assertStringNotContainsString('transition:', $critical);
        $this->assertStringNotContainsString('animation:', $critical);
        $this->assertStringNotContainsString('.unused-anywhere', $critical);
        $this->assertStringNotContainsString('#gone', $critical);
        // Braces inside quoted content must not break parsing.
        $this->assertStringNotContainsString('.pd-content', $critical);
    }

    public function test_fold_marker_is_stripped_from_the_shipped_html(): void
    {
        $product = $this->product();

        $this->get($product->url())->assertOk()->assertDontSee('critical-fold', false);
    }

    public function test_escaped_tailwind_variant_classes_match(): void
    {
        $html = '<div class="lg:grid-cols-4 w-1/2 p-2.5"></div>';
        $css = '.lg\:grid-cols-4{display:grid}.w-1\/2{width:50%}.p-2\.5{padding:0.625rem}.lg\:hidden{display:none}';

        $critical = app(CriticalCss::class)->generate($html, $css);

        $this->assertStringContainsString('.lg\:grid-cols-4{display:grid}', $critical);
        $this->assertStringContainsString('.w-1\/2{width:50%}', $critical);
        $this->assertStringContainsString('.p-2\.5{padding:0.625rem}', $critical);
        $this->assertStringNotContainsString('.lg\:hidden', $critical);
    }
}
