<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SeoMeta;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_sitemap_includes_visible_products(): void
    {
        Product::create([
            'name' => 'Alpha', 'slug' => 'alpha', 'sku' => 'A1', 'type' => 'simple',
            'price' => 5, 'stock_status' => 'in_stock', 'stock_qty' => 1,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);

        $xml = app(SitemapGenerator::class)->section('products');
        $this->assertNotNull($xml);
        $this->assertStringContainsString('/product/alpha', $xml);
    }

    public function test_products_sitemap_excludes_noindex_products(): void
    {
        $p = Product::create([
            'name' => 'Hidden', 'slug' => 'hidden', 'sku' => 'H1', 'type' => 'simple',
            'price' => 5, 'stock_status' => 'in_stock', 'stock_qty' => 1,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
        SeoMeta::updateOrCreate(
            ['metable_type' => Product::class, 'metable_id' => $p->id],
            ['noindex' => true],
        );

        $xml = app(SitemapGenerator::class)->section('products');
        $this->assertNotNull($xml);
        $this->assertStringNotContainsString('/product/hidden', $xml);
    }

    protected function product(string $slug): Product
    {
        return Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'type' => 'simple',
            'price' => 5, 'stock_status' => 'in_stock', 'stock_qty' => 1,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
    }

    public function test_sitemap_splits_into_numbered_files_by_links_per_page(): void
    {
        \App\Models\Setting::set('seo.sitemap_links_per_page', 10);

        foreach (range(1, 12) as $i) {
            $this->product("bulk-{$i}");
        }

        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/sitemap-products.xml', $index);
        $this->assertStringContainsString('/sitemap-products-2.xml', $index);

        // Page 2 serves the overflow via the paged route.
        $page2 = $this->get('/sitemap-products-2.xml')->assertOk()->getContent();
        $this->assertSame(2, substr_count($page2, '<url>'));
    }

    public function test_admin_can_disable_content_types_and_exclude_ids(): void
    {
        $kept = $this->product('kept-product');
        $excluded = $this->product('excluded-product');

        \App\Models\Setting::set('seo.sitemap_exclude_product_ids', (string) $excluded->id);
        \App\Models\Setting::set('seo.sitemap_categories', false);

        $xml = app(SitemapGenerator::class)->section('products');
        $this->assertStringContainsString('/product/kept-product', $xml);
        $this->assertStringNotContainsString('/product/excluded-product', $xml);

        // Disabled type: no section file, absent from the index.
        $this->assertNull(app(SitemapGenerator::class)->section('categories'));
        $this->assertStringNotContainsString('sitemap-categories', $this->get('/sitemap.xml')->getContent());
    }

    public function test_lastmod_is_real_and_images_are_included_per_google_spec(): void
    {
        $product = $this->product('imaged');
        $product->images()->create(['path' => 'products/imaged.jpg', 'alt' => 'x', 'sort_order' => 0]);
        Product::whereKey($product->id)->toBase()->update(['updated_at' => '2026-07-01 10:00:00']);

        SitemapGenerator::flush();
        $xml = app(SitemapGenerator::class)->section('products');

        // Real modification time, image extension present, deprecated tags gone.
        $this->assertStringContainsString('<lastmod>2026-07-01T10:00:00', $xml);
        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $xml);
        $this->assertStringContainsString('<image:loc>', $xml);
        $this->assertStringNotContainsString('<changefreq>', $xml);
        $this->assertStringNotContainsString('<priority>', $xml);

        // Images toggle off → no image entries.
        \App\Models\Setting::set('seo.sitemap_images', false);
        SitemapGenerator::flush();
        $this->assertStringNotContainsString('<image:loc>', app(SitemapGenerator::class)->section('products'));
    }

    public function test_categories_sitemap_includes_feature_image_and_lastmod(): void
    {
        $cat = \App\Models\Category::create(['name' => 'Devices', 'slug' => 'devices', 'is_active' => true, 'image' => 'categories/devices.jpg']);
        \App\Models\Category::whereKey($cat->id)->toBase()->update(['updated_at' => '2026-07-02 09:00:00']);
        // A category still on the seeded .svg placeholder — its image is skipped.
        \App\Models\Category::create(['name' => 'Old', 'slug' => 'old', 'is_active' => true, 'image' => 'categories/old.svg']);

        SitemapGenerator::flush();
        $xml = app(SitemapGenerator::class)->section('categories');

        $this->assertStringContainsString('/category/devices', $xml);
        $this->assertStringContainsString('<lastmod>2026-07-02T09:00:00', $xml);
        $this->assertStringContainsString('<image:image>', $xml);
        $this->assertStringContainsString('categories/devices.jpg', $xml);
        $this->assertStringNotContainsString('categories/old.svg', $xml); // .svg placeholder excluded

        // Images toggle off → no image entries in the categories sitemap either.
        \App\Models\Setting::set('seo.sitemap_images', false);
        SitemapGenerator::flush();
        $this->assertStringNotContainsString('<image:loc>', app(SitemapGenerator::class)->section('categories'));
    }

    public function test_blog_categories_section_lists_only_non_empty_categories(): void
    {
        $author = \App\Models\User::factory()->create();
        $withPosts = \App\Models\PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        \App\Models\PostCategory::create(['name' => 'Empty', 'slug' => 'empty']);

        \App\Models\Post::create([
            'title' => 'Guide 1', 'slug' => 'guide-1', 'status' => 'published',
            'author_id' => $author->id, 'published_at' => now(),
            'post_category_id' => $withPosts->id, 'content' => 'x',
        ]);

        $xml = app(SitemapGenerator::class)->section('post-categories');

        $this->assertStringContainsString('/blog/category/guides', $xml);
        $this->assertStringNotContainsString('/blog/category/empty', $xml);
    }

    public function test_sitemap_auto_updates_when_content_changes(): void
    {
        $this->product('first');
        $this->assertStringNotContainsString('/product/fresh-arrival', app(SitemapGenerator::class)->section('products'));

        // Creating a product bumps the cache version — no manual flush.
        $this->product('fresh-arrival');

        $this->assertStringContainsString('/product/fresh-arrival', app(SitemapGenerator::class)->section('products'));
    }
}
