<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\Seo\SchemaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_schema_omits_rating_when_no_real_reviews(): void
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget', 'sku' => 'W-1', 'type' => 'simple',
            'price' => 9.99, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
            'reviews_count' => 0, 'avg_rating' => 0,
        ]);

        $schema = app(SchemaGenerator::class)->product($product);

        $this->assertArrayNotHasKey('aggregateRating', $schema, 'Should not fabricate ratings when reviews_count = 0.');
        $this->assertSame('Product', $schema['@type']);
        $this->assertSame('Widget', $schema['name']);
    }

    public function test_product_schema_description_is_clean_plain_text(): void
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-desc', 'sku' => 'W-D', 'type' => 'simple',
            'price' => 9.99, 'stock_status' => 'in_stock', 'status' => 'published',
            'visibility' => 'visible', 'published_at' => now(),
            'short_description' => '<p>Cools on the exhale.</p><p>Flavor: menthol &amp; fruit</p><ul><li>It isn&#039;t one-note</li></ul>',
        ]);

        $desc = app(SchemaGenerator::class)->product($product)['description'];

        // Block boundaries become spaces (no "exhale.Flavor" run-on).
        $this->assertStringContainsString('exhale. Flavor', $desc);
        // Entities decoded, tags gone.
        $this->assertStringContainsString("isn't one-note", $desc);
        $this->assertStringContainsString('menthol & fruit', $desc);
        $this->assertStringNotContainsString('&#039;', $desc);
        $this->assertStringNotContainsString('&amp;', $desc);
        $this->assertStringNotContainsString('<', $desc);
    }

    public function test_product_schema_includes_rating_when_real_reviews_exist(): void
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget', 'sku' => 'W-1', 'type' => 'simple',
            'price' => 9.99, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
            'reviews_count' => 12, 'avg_rating' => 4.5,
        ]);

        $schema = app(SchemaGenerator::class)->product($product);

        $this->assertArrayHasKey('aggregateRating', $schema);
        $this->assertSame(4.5, (float) $schema['aggregateRating']['ratingValue']);
        $this->assertSame(12, (int) $schema['aggregateRating']['reviewCount']);
    }

    public function test_offer_price_currency_matches_setting(): void
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-x', 'sku' => 'W-X', 'type' => 'simple',
            'price' => 25.00, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);

        $schema = app(SchemaGenerator::class)->product($product);

        $this->assertSame('25.00', number_format((float) $schema['offers']['price'], 2, '.', ''));
        $this->assertNotEmpty($schema['offers']['priceCurrency']);
    }

    public function test_product_schema_includes_additional_property_from_attribute_values(): void
    {
        $product = Product::create([
            'name' => 'TEREA Test', 'slug' => 'terea-test', 'sku' => 'T-1', 'type' => 'simple',
            'price' => 29.00, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);

        $flavor = Attribute::create(['name' => 'Flavor Family', 'slug' => 'flavor-family', 'type' => 'select']);
        $value = AttributeValue::create(['attribute_id' => $flavor->id, 'value' => 'Menthol', 'slug' => 'menthol']);
        $product->attributeValues()->attach($value->id);

        $schema = app(SchemaGenerator::class)->product($product->fresh());

        $this->assertArrayHasKey('additionalProperty', $schema);
        $this->assertSame([
            '@type' => 'PropertyValue',
            'name' => 'Flavor Family',
            'value' => 'Menthol',
        ], $schema['additionalProperty'][0]);
    }

    public function test_product_schema_omits_additional_property_when_no_attributes(): void
    {
        $product = Product::create([
            'name' => 'Plain Widget', 'slug' => 'plain-widget', 'sku' => 'W-P', 'type' => 'simple',
            'price' => 9.99, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);

        $schema = app(SchemaGenerator::class)->product($product);

        $this->assertArrayNotHasKey('additionalProperty', $schema);
    }

    public function test_product_schema_category_is_the_full_breadcrumb_string(): void
    {
        $parent = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $child = Category::create(['name' => 'TEREA Yellow', 'slug' => 'terea-yellow', 'parent_id' => $parent->id, 'is_active' => true]);

        $product = Product::create([
            'name' => 'TEREA Yellow Pack', 'slug' => 'terea-yellow-pack', 'sku' => 'T-2', 'type' => 'simple',
            'price' => 25.00, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
        $product->categories()->attach($child->id);

        $schema = app(SchemaGenerator::class)->product($product->fresh());

        $this->assertSame('TEREA UAE > TEREA Yellow', $schema['category']);
    }

    public function test_category_schema_item_list_reflects_paginated_products(): void
    {
        $category = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $products = collect([
            Product::create(['name' => 'A', 'slug' => 'a', 'type' => 'simple', 'price' => 10, 'status' => 'published']),
            Product::create(['name' => 'B', 'slug' => 'b', 'type' => 'simple', 'price' => 10, 'status' => 'published']),
        ]);

        $schema = app(SchemaGenerator::class)->category($category, productCount: 5, products: $products, page: 2, perPage: 2);

        $this->assertSame(5, $schema['mainEntity']['numberOfItems']);
        $this->assertCount(2, $schema['mainEntity']['itemListElement']);
        $this->assertSame(3, $schema['mainEntity']['itemListElement'][0]['position']);
        $this->assertSame(4, $schema['mainEntity']['itemListElement'][1]['position']);
    }

    public function test_category_schema_omits_item_list_element_when_no_products_given(): void
    {
        $category = Category::create(['name' => 'Empty', 'slug' => 'empty', 'is_active' => true]);

        $schema = app(SchemaGenerator::class)->category($category);

        $this->assertArrayNotHasKey('itemListElement', $schema['mainEntity']);
    }

    public function test_comparison_item_list_references_both_compared_products(): void
    {
        $a = Product::create(['name' => 'TEREA Yellow', 'slug' => 'terea-yellow', 'type' => 'simple', 'price' => 25, 'status' => 'published']);
        $b = Product::create(['name' => 'TEREA Bronze', 'slug' => 'terea-bronze', 'type' => 'simple', 'price' => 25, 'status' => 'published']);

        $post = Post::create([
            'title' => 'TEREA Yellow vs Bronze', 'slug' => 'yellow-vs-bronze', 'content' => 'x',
            'status' => 'published', 'author_id' => User::factory()->create()->id,
            'compared_product_ids' => [$a->id, $b->id],
        ]);

        $schema = app(SchemaGenerator::class)->comparisonItemList($post);

        $this->assertSame('ItemList', $schema['@type']);
        $this->assertCount(2, $schema['itemListElement']);
        $this->assertSame('TEREA Yellow', $schema['itemListElement'][0]['item']['name']);
        $this->assertSame('TEREA Bronze', $schema['itemListElement'][1]['item']['name']);
    }

    public function test_comparison_item_list_is_null_when_post_has_no_compared_products(): void
    {
        $post = Post::create([
            'title' => 'Regular Post', 'slug' => 'regular-post', 'content' => 'x',
            'status' => 'published', 'author_id' => User::factory()->create()->id,
        ]);

        $this->assertNull(app(SchemaGenerator::class)->comparisonItemList($post));
    }
}
