<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaFeaturesTest extends TestCase
{
    use RefreshDatabase;

    /** Extract every JSON-LD graph from a page. */
    protected function jsonLd(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        $graphs = [];
        foreach ($m[1] as $blob) {
            $decoded = json_decode($blob, true);
            $this->assertNotNull($decoded, 'JSON-LD must be valid JSON');
            $graphs[] = $decoded;
        }

        return $graphs;
    }

    /** Find a node of the given @type anywhere in the graphs. */
    protected function findNode(array $graphs, string $type): ?array
    {
        foreach ($graphs as $graph) {
            foreach (($graph['@graph'] ?? [$graph]) as $node) {
                if (($node['@type'] ?? null) === $type) {
                    return $node;
                }
            }
        }

        return null;
    }

    public function test_product_schema_carries_full_merchant_listing_features(): void
    {
        Setting::set('seo.return_policy_days', 7);
        Setting::set('seo.return_fees', 'free');
        Setting::set('seo.return_method', 'mail');
        Setting::set('seo.shipping_rate', 0);
        Setting::set('seo.shipping_transit_days', 1);

        $category = Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $product = Product::create([
            'name' => 'TEREA Amber Carton', 'slug' => 'terea-amber-carton', 'type' => 'simple',
            'price' => 220, 'sku' => 'TER-AMB-200', 'gtin' => '07622100859378',
            'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
            'manage_stock' => false,
        ]);
        $product->categories()->attach($category);

        $node = $this->findNode($this->jsonLd($product->url()), 'Product');
        $this->assertNotNull($node, 'Product schema must be emitted');

        // Identifiers + category.
        $this->assertSame('TER-AMB-200', $node['sku']);
        $this->assertSame('07622100859378', $node['gtin']);
        $this->assertSame('Terea UAE', $node['category']);

        $offer = $node['offers'];
        $this->assertSame('220.00', $offer['price']);
        $this->assertSame('https://schema.org/InStock', $offer['availability']);
        $this->assertSame('https://schema.org/NewCondition', $offer['itemCondition']);
        $this->assertArrayHasKey('priceValidUntil', $offer); // GSC warning killer
        $this->assertArrayHasKey('seller', $offer);

        // Merchant return policy — complete per Google's docs.
        $policy = $offer['hasMerchantReturnPolicy'];
        $this->assertSame(7, $policy['merchantReturnDays']);
        $this->assertSame('https://schema.org/FreeReturn', $policy['returnFees']);
        $this->assertSame('https://schema.org/ReturnByMail', $policy['returnMethod']);
        $this->assertNotEmpty($policy['applicableCountry']);

        // Shipping details — cost, destination, speed.
        $shipping = $offer['shippingDetails'];
        $this->assertSame('0.00', $shipping['shippingRate']['value']);
        $this->assertNotEmpty($shipping['shippingDestination']['addressCountry']);
        $this->assertSame(1, $shipping['deliveryTime']['transitTime']['maxValue']);
    }

    public function test_article_schema_is_enriched(): void
    {
        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        $post = Post::create([
            'title' => 'TEREA Carton Storage Guide', 'slug' => 'carton-storage-guide',
            'content' => '<h2>Storage</h2><p>'.str_repeat('Keep cartons sealed and cool. ', 30).'</p>',
            'excerpt' => 'How to store TEREA cartons properly.',
            'status' => 'published', 'published_at' => now()->subDay(),
            'post_category_id' => $category->id,
            'author_id' => User::factory()->create()->id,
        ]);
        $post->tags()->create(['name' => 'storage', 'slug' => 'storage']);

        $node = $this->findNode($this->jsonLd(route('blog.show', $post->slug)), 'BlogPosting');
        $this->assertNotNull($node);

        $this->assertSame('Guides', $node['articleSection']);
        $this->assertSame('storage', $node['keywords']);
        $this->assertGreaterThan(100, $node['wordCount']);
        $this->assertSame('en', $node['inLanguage']);
        $this->assertNotEmpty($node['datePublished']);
        $this->assertNotEmpty($node['dateModified']);
        $this->assertSame('Person', $node['author']['@type']);
        $this->assertArrayHasKey('publisher', $node);
    }

    public function test_breadcrumb_schema_is_properly_structured(): void
    {
        $parent = Category::create(['name' => 'Heated Tobacco', 'slug' => 'heated-tobacco', 'is_active' => true]);
        $child = Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true, 'parent_id' => $parent->id]);
        $product = Product::create([
            'name' => 'TEREA Sienna Carton', 'slug' => 'terea-sienna-carton', 'type' => 'simple',
            'price' => 220, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
        $product->categories()->attach($child);

        $node = $this->findNode($this->jsonLd($product->url()), 'BreadcrumbList');
        $this->assertNotNull($node, 'BreadcrumbList must be emitted on product pages');

        $items = $node['itemListElement'];

        // Home → parent category → child category → product.
        $this->assertCount(4, $items);

        foreach ($items as $i => $item) {
            $this->assertSame('ListItem', $item['@type']);
            $this->assertSame($i + 1, $item['position']); // sequential from 1
            $this->assertNotEmpty($item['name']);
        }

        // Every crumb except the current page carries its URL (Google rule:
        // the last item's URL is optional and may be omitted).
        $this->assertSame(url('/'), $items[0]['item']);
        $this->assertStringContainsString('/category/heated-tobacco', $items[1]['item']);
        $this->assertStringContainsString('/category/terea-uae', $items[2]['item']);
        $this->assertSame('TEREA Sienna Carton', $items[3]['name']);
        $this->assertArrayNotHasKey('item', $items[3]);

        // Blog posts get Home → Blog → title.
        $post = Post::create([
            'title' => 'Breadcrumb Check Post', 'slug' => 'breadcrumb-check-post',
            'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now(),
            'author_id' => User::factory()->create()->id,
        ]);

        $blogCrumbs = $this->findNode($this->jsonLd(route('blog.show', $post->slug)), 'BreadcrumbList');
        $this->assertNotNull($blogCrumbs);
        $this->assertCount(3, $blogCrumbs['itemListElement']);
        $this->assertSame('Blog', $blogCrumbs['itemListElement'][1]['name']);
    }
}
