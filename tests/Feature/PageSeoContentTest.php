<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSeoContentTest extends TestCase
{
    use RefreshDatabase;

    protected function seedPages(): void
    {
        // The refresh command updates existing pages by slug — create the
        // shells it expects, plus a category for the catalogue.
        foreach ([
            'terea-delivery-dubai' => 'TEREA Delivery in Dubai',
            'about-us' => 'About Terea Hub',
            'privacy-policy' => 'Privacy Policy',
            'terms-and-conditions' => 'Terms and Conditions',
            'refund-policy' => 'Return and Refund Policy',
            'shipping-policy' => 'Shipping Policy',
            'contact-us' => 'Contact Us',
            'faq' => 'FAQ',
        ] as $slug => $title) {
            Page::create(['title' => $title, 'slug' => $slug, 'content' => 'old', 'status' => 'published']);
        }

        Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 220, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ])->categories()->attach(Category::first());
    }

    public function test_refresh_command_rewrites_content_and_faqs(): void
    {
        $this->seedPages();

        $this->artisan('pages:seo-refresh')->assertExitCode(0);

        $dubai = Page::where('slug', 'terea-delivery-dubai')->first();
        $this->assertStringContainsString('Genuine IQOS TEREA delivered in Dubai', $dubai->content);
        $this->assertSame(5, $dubai->allFaqs()->count());

        $faq = Page::where('slug', 'faq')->first();
        $this->assertSame(8, $faq->allFaqs()->count());
    }

    public function test_no_em_dashes_or_duplicate_internal_links_on_any_page(): void
    {
        $this->seedPages();
        $this->artisan('pages:seo-refresh');

        foreach (Page::all() as $page) {
            $html = (string) $page->content;

            // House rule: no em/en dashes anywhere.
            $this->assertSame(0, preg_match_all('/[\x{2014}\x{2013}]/u', $html),
                "Dash found in {$page->slug}");

            // No internal href repeats on a page (anchor dilution).
            preg_match_all('/href="([^"]+)"/i', $html, $m);
            $internal = array_filter($m[1], fn ($h) => str_starts_with($h, '/'));
            $this->assertSame(count($internal), count(array_unique($internal)),
                "Duplicate internal link in {$page->slug}");
        }
    }

    public function test_city_pages_show_the_category_catalogue(): void
    {
        $this->seedPages();
        $this->artisan('pages:seo-refresh');

        // City pages include the catalogue at the top with its own heading
        // hidden (hideHead) — the page's own H1/lead lead instead — so we
        // assert the catalogue cards and a category name, not the partial's
        // internal title.
        $this->get('/terea-delivery-dubai')
            ->assertOk()
            ->assertSee('catalogue-card', false)
            ->assertSee('catalogue--flush', false)
            ->assertSee('Terea UAE');
    }

    public function test_non_city_pages_do_not_show_the_catalogue(): void
    {
        $this->seedPages();
        $this->artisan('pages:seo-refresh');

        $this->get('/privacy-policy')->assertOk()->assertDontSee('Shop TEREA by Category');
        $this->get('/about-us')->assertOk()->assertDontSee('Shop TEREA by Category');
    }

    public function test_legal_pages_are_comprehensive_and_well_structured(): void
    {
        $this->seedPages();
        $this->artisan('pages:seo-refresh');

        foreach (['privacy-policy', 'terms-and-conditions', 'refund-policy', 'shipping-policy'] as $slug) {
            $html = (string) Page::where('slug', $slug)->first()->content;
            // A real policy: multiple sections and meaningful length.
            $this->assertGreaterThanOrEqual(7, preg_match_all('/<h2/i', $html), "{$slug} needs sections");
            $this->assertGreaterThanOrEqual(300, str_word_count(strip_tags($html)), "{$slug} too thin");
        }
    }

    public function test_command_is_idempotent(): void
    {
        $this->seedPages();
        $this->artisan('pages:seo-refresh');
        $first = Page::where('slug', 'about-us')->first()->content;

        $this->artisan('pages:seo-refresh');
        $second = Page::where('slug', 'about-us')->first()->content;

        $this->assertSame($first, $second);
    }
}
