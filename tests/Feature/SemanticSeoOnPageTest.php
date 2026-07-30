<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Structured data must describe content the visitor can actually SEE:
 * taxonomy attributes render on the product page, and comparison posts
 * show the compared products — not schema-only claims.
 */
class SemanticSeoOnPageTest extends TestCase
{
    use RefreshDatabase;

    protected function publishedProduct(string $name): Product
    {
        return Product::create([
            'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
            'price' => 32, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
    }

    protected function attachFacet(Product $product, string $attributeName, string $value): void
    {
        $attribute = Attribute::firstOrCreate(
            ['slug' => str($attributeName)->slug()],
            ['name' => $attributeName, 'type' => 'select'],
        );
        $attributeValue = $attribute->values()->firstOrCreate(
            ['slug' => str($value)->slug()],
            ['value' => $value],
        );
        $product->attributeValues()->syncWithoutDetaching([$attributeValue->id]);
    }

    public function test_attribute_facts_joins_multi_value_attributes(): void
    {
        $product = $this->publishedProduct('TEREA Yellow');
        $this->attachFacet($product, 'Flavor Family', 'Menthol');
        $this->attachFacet($product, 'Device Compatibility', 'IQOS ILUMA');
        $this->attachFacet($product, 'Device Compatibility', 'IQOS ILUMA i');

        $facts = $product->fresh()->attributeFacts();

        $this->assertSame('Menthol', $facts['Flavor Family']);
        $this->assertSame('IQOS ILUMA, IQOS ILUMA i', $facts['Device Compatibility']);
    }

    public function test_product_page_shows_taxonomy_attributes_visibly(): void
    {
        $product = $this->publishedProduct('TEREA Yellow');
        $this->attachFacet($product, 'Flavor Family', 'Menthol');
        $this->attachFacet($product, 'Tobacco Strength', 'Medium');

        $response = $this->get(route('product.show', $product->slug));

        $response->assertOk();
        // The facts users see match the additionalProperty schema claims.
        $response->assertSee('Flavor Family');
        $response->assertSee('Menthol');
        $response->assertSee('Tobacco Strength');
        $response->assertSee('Medium');
    }

    public function test_legacy_specification_does_not_duplicate_a_taxonomy_row(): void
    {
        $product = $this->publishedProduct('TEREA Bronze');
        $this->attachFacet($product, 'Flavor Family', 'Regular');
        $product->update(['specifications' => ['Flavor Family' => 'Old free-text value', 'Weight' => '100g']]);

        $response = $this->get(route('product.show', $product->slug));

        $response->assertOk();
        // Taxonomy wins the duplicate label; unrelated legacy specs survive.
        $response->assertSee('Regular');
        $response->assertDontSee('Old free-text value');
        $response->assertSee('Weight');
    }

    public function test_comparison_post_shows_the_compared_products_box(): void
    {
        $yellow = $this->publishedProduct('TEREA Yellow');
        $bronze = $this->publishedProduct('TEREA Bronze');

        $post = Post::create([
            'title' => 'TEREA Yellow vs Bronze', 'slug' => 'yellow-vs-bronze', 'content' => '<p>Comparison body.</p>',
            'status' => 'published', 'published_at' => now()->subHour(),
            'author_id' => User::factory()->create()->id,
            'compared_product_ids' => [$yellow->id, $bronze->id],
        ]);

        $response = $this->get($post->url());

        $response->assertOk();
        $response->assertSee('Products compared in this article');
        $response->assertSee('TEREA Yellow');
        $response->assertSee('TEREA Bronze');
    }

    public function test_normal_post_has_no_compared_products_box(): void
    {
        $post = Post::create([
            'title' => 'A Normal Guide', 'slug' => 'a-normal-guide', 'content' => '<p>Body.</p>',
            'status' => 'published', 'published_at' => now()->subHour(),
            'author_id' => User::factory()->create()->id,
        ]);

        $this->get($post->url())
            ->assertOk()
            ->assertDontSee('Products compared in this article');
    }

    public function test_compared_products_keeps_order_and_drops_unpublished(): void
    {
        $yellow = $this->publishedProduct('TEREA Yellow');
        $bronze = $this->publishedProduct('TEREA Bronze');
        $bronze->update(['status' => 'draft']);

        $post = Post::create([
            'title' => 'Yellow vs Bronze', 'slug' => 'yvb', 'content' => 'x',
            'status' => 'published', 'published_at' => now()->subHour(),
            'author_id' => User::factory()->create()->id,
            'compared_product_ids' => [$bronze->id, $yellow->id],
        ]);

        $compared = $post->comparedProducts();

        // Unpublished product silently drops; the box never 404-links.
        $this->assertCount(1, $compared);
        $this->assertSame($yellow->id, $compared->first()->id);
    }
}
