<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_auto_fills_open_graph_from_seo_data(): void
    {
        $product = Product::create([
            'name' => 'TEREA Amber Carton', 'slug' => 'terea-amber-carton', 'type' => 'simple',
            'price' => 220, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
        $product->seoMeta()->updateOrCreate([], [
            'title' => 'Buy TEREA Amber Carton in Dubai | 1-Hour Delivery',
            'description' => 'Genuine TEREA Amber cartons, 200 sticks, delivered in one hour.',
        ]);

        $html = $this->get($product->url())->assertOk()->getContent();

        // og:title = the SEO meta title (content title — no duplicated site name).
        $this->assertStringContainsString('<meta property="og:title" content="Buy TEREA Amber Carton in Dubai | 1-Hour Delivery">', $html);
        // og:description = the meta description.
        $this->assertStringContainsString('<meta property="og:description" content="Genuine TEREA Amber cartons, 200 sticks, delivered in one hour.">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.$product->url().'">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $html);
        // Product enrichment: price + currency + availability.
        $this->assertStringContainsString('<meta property="product:price:amount" content="220.00">', $html);
        $this->assertStringContainsString('<meta property="product:price:currency"', $html);
        $this->assertStringContainsString('<meta property="product:availability" content="in stock">', $html);
        // Twitter mirrors OG.
        $this->assertStringContainsString('<meta name="twitter:title" content="Buy TEREA Amber Carton in Dubai | 1-Hour Delivery">', $html);
    }

    public function test_post_page_emits_article_open_graph_with_timestamps(): void
    {
        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        $post = Post::create([
            'title' => 'How to Store TEREA Cartons', 'slug' => 'store-terea-cartons',
            'content' => '<h2>Storage</h2><p>Keep sealed cartons cool.</p>',
            'status' => 'published', 'published_at' => now()->subDay(),
            'post_category_id' => $category->id,
            'author_id' => User::factory()->create()->id,
            'featured_image_alt' => 'Sealed TEREA carton on a shelf',
        ]);

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="article:published_time" content="'.$post->published_at->toIso8601String().'">', $html);
        $this->assertStringContainsString('<meta property="article:modified_time"', $html);
        $this->assertStringContainsString('<meta property="article:section" content="Guides">', $html);
        // og:title falls back to the post title (content title, no suffix).
        $this->assertStringContainsString('<meta property="og:title" content="How to Store TEREA Cartons">', $html);
    }

    public function test_category_page_fills_open_graph_and_image_alt(): void
    {
        Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true]);

        $html = $this->get('/category/terea-uae')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Terea UAE', $html);
        $this->assertStringContainsString('<meta property="og:site_name"', $html);
    }

    public function test_image_dimension_tags_emit_for_local_images(): void
    {
        // 1200x630 PNG in the public storage disk — the guideline size.
        $image = imagecreatetruecolor(120, 63);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        \Illuminate\Support\Facades\Storage::disk('public')->put('products/og-test.png', $png);

        $product = Product::create([
            'name' => 'TEREA Sienna Carton', 'slug' => 'terea-sienna-carton', 'type' => 'simple',
            'price' => 220, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
        $product->images()->create(['path' => 'products/og-test.png', 'alt' => 'TEREA Sienna carton front view']);
        $product->update(['featured_image' => 'products/og-test.png']);
        $product->refresh();

        $html = $this->get($product->url())->assertOk()->getContent();

        $this->assertStringContainsString('og-test.png', $html);
        $this->assertStringContainsString('<meta property="og:image:width" content="120">', $html);
        $this->assertStringContainsString('<meta property="og:image:height" content="63">', $html);
        $this->assertStringContainsString('<meta property="og:image:alt" content="TEREA Sienna carton front view">', $html);
        $this->assertStringContainsString('<meta name="twitter:image:alt" content="TEREA Sienna carton front view">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
    }
}
