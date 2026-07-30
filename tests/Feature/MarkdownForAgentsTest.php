<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkdownForAgentsTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        return Product::create([
            'name' => 'IQOS TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 130, 'sale_price' => 120, 'status' => 'published',
            'short_description' => '<p>Rich tobacco.</p>', 'description' => '<h2>About</h2><p>A <strong>bold</strong> stick.</p>',
        ]);
    }

    public function test_md_url_returns_clean_markdown_with_headers(): void
    {
        $this->product();

        $res = $this->get('/product/terea-amber.md');

        $res->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('Vary', 'Accept')
            ->assertSee('# IQOS TEREA Amber', false)
            ->assertSee('Availability: In stock', false)
            ->assertSee('on sale from', false);

        $this->assertNotEmpty($res->headers->get('X-Markdown-Tokens'));
    }

    public function test_accept_header_negotiates_markdown(): void
    {
        $this->product();

        $this->get('/product/terea-amber', ['Accept' => 'text/markdown'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# IQOS TEREA Amber', false);
    }

    public function test_markdown_variants_are_noindex_but_html_is_indexable(): void
    {
        $this->product();

        // Both markdown delivery paths must carry noindex so Google/Bing never
        // index the .md twin as duplicate content.
        $this->get('/product/terea-amber.md')
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex');

        $this->get('/product/terea-amber', ['Accept' => 'text/markdown'])
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex');

        // The real HTML page stays fully indexable — no noindex leaks onto it.
        $html = $this->get('/product/terea-amber');
        $html->assertOk();
        $this->assertNotSame('noindex', $html->headers->get('X-Robots-Tag'));
    }

    public function test_plain_get_still_returns_html(): void
    {
        $this->product();

        $res = $this->get('/product/terea-amber');

        $res->assertOk();
        $this->assertStringContainsString('text/html', (string) $res->headers->get('Content-Type'));
        // Discovery link points AI crawlers at the markdown variant.
        $res->assertSee('rel="alternate" type="text/markdown"', false)
            ->assertSee('/product/terea-amber.md', false);
    }

    public function test_category_post_and_page_md_variants(): void
    {
        Category::create(['name' => 'Devices', 'slug' => 'devices', 'is_active' => true]);
        Post::create(['title' => 'Guide', 'slug' => 'guide', 'status' => 'published', 'content' => '<p>Hello</p>', 'published_at' => now(), 'author_id' => \App\Models\User::factory()->create()->id]);
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => '<p>Us</p>']);

        $this->get('/category/devices.md')->assertOk()->assertSee('# Devices', false);
        $this->get('/blog/guide.md')->assertOk()->assertSee('# Guide', false);
        $this->get('/about.md')->assertOk()->assertSee('# About', false); // root .md resolver
    }

    public function test_toggle_off_disables_markdown(): void
    {
        $this->product();
        Setting::set('seo.markdown_for_agents', false);

        $this->get('/product/terea-amber.md')->assertNotFound();

        // Content negotiation also falls back to HTML when disabled.
        $res = $this->get('/product/terea-amber', ['Accept' => 'text/markdown']);
        $res->assertOk();
        $this->assertStringContainsString('text/html', (string) $res->headers->get('Content-Type'));
    }

    public function test_unknown_slug_md_is_404(): void
    {
        $this->get('/product/does-not-exist.md')->assertNotFound();
    }
}
