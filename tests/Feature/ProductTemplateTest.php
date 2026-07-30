<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductTemplate;
use App\Services\Seo\SeoManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Sun Pearl Japan',
            'slug' => 'sun-pearl-japan',
            'type' => 'simple',
            'price' => 215,
            'sale_price' => 208,
            'sku' => 'SP-JP',
            'status' => 'published',
            'visibility' => 'visible',
            'stock_status' => 'in_stock',
            'short_description' => '<p>Light menthol with smooth herbal elegance.</p>',
            'description' => '<h2>What is Sun Pearl</h2><p>A refined menthol stick.</p>',
            'specifications' => ['Flavor' => 'Menthol', 'Origin' => 'Japan', 'Total Puffs' => '14'],
        ], $overrides));
    }

    public function test_code_default_renders_without_any_db_template(): void
    {
        $template = ProductTemplate::default();

        $this->assertSame('Default', $template->name);
        $this->assertNotEmpty($template->resolvedBlocks());
        $this->assertTrue($template->schemaEnabled('product'));
    }

    public function test_resolve_prefers_the_products_own_template(): void
    {
        $custom = ProductTemplate::create(['name' => 'Custom', 'blocks' => [['type' => 'title', 'data' => []]]]);
        $product = $this->product(['product_template_id' => $custom->id]);

        $this->assertSame($custom->id, ProductTemplate::resolve($product)->id);
    }

    public function test_only_one_template_stays_default(): void
    {
        $a = ProductTemplate::create(['name' => 'A', 'is_default' => true]);
        $b = ProductTemplate::create(['name' => 'B', 'is_default' => true]);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_product_page_renders_blocks_from_the_default_template(): void
    {
        ProductTemplate::create([
            'name' => 'Seeded default',
            'is_default' => true,
            'settings' => ProductTemplate::defaultSettings(),
            'blocks' => ProductTemplate::defaultBlocks(),
        ]);

        $product = $this->product();

        $html = $this->get($product->url())->assertStatus(200)->getContent();

        // Blocks from across the layout are present.
        $this->assertStringContainsString('Sun Pearl Japan', $html);          // title
        $this->assertStringContainsString('Add to cart', $html);              // add_to_cart
        $this->assertStringContainsString('Free Delivery Over AED 300', $html); // delivery_info box
        $this->assertStringContainsString('Cash on Delivery', $html);           // payment chips
        $this->assertStringContainsString('Flavor', $html);                   // key_facts / specs
        $this->assertStringContainsString('What is Sun Pearl', $html);        // description
    }

    public function test_custom_html_block_renders_anywhere(): void
    {
        ProductTemplate::create([
            'name' => 'With HTML',
            'is_default' => true,
            'settings' => ProductTemplate::defaultSettings(),
            'blocks' => [
                ['type' => 'title', 'data' => ['column' => 'right']],
                ['type' => 'html', 'data' => ['column' => 'full', 'content' => '<p>SUN PEARL CUSTOM PROMO</p>']],
            ],
        ]);

        $this->get($this->product()->url())
            ->assertStatus(200)
            ->assertSee('SUN PEARL CUSTOM PROMO');
    }

    public function test_per_block_colour_and_font_size_emit_inline_style(): void
    {
        ProductTemplate::create([
            'name' => 'Styled',
            'is_default' => true,
            'settings' => ProductTemplate::defaultSettings(),
            'blocks' => [
                ['type' => 'title', 'data' => ['column' => 'right', 'text_color' => '#ff0000', 'font_size' => '3xl']],
            ],
        ]);

        $html = $this->get($this->product()->url())->assertStatus(200)->getContent();

        $this->assertStringContainsString('color:#ff0000', $html);
        $this->assertStringContainsString('font-size:1.875rem', $html);
    }

    public function test_schema_toggles_control_json_ld_output(): void
    {
        $seo = app(SeoManager::class);
        $product = $this->product();

        // All on by default (code default).
        $default = json_encode($seo->forProduct($product->fresh())->schemas);
        $this->assertStringContainsString('"Product"', $default);
        $this->assertStringContainsString('"BreadcrumbList"', $default);

        // Turn Product + Breadcrumb schema off via the product's template.
        $template = ProductTemplate::create([
            'name' => 'No product schema',
            'settings' => ['schema' => ['product' => false, 'breadcrumb' => false]],
            'blocks' => ProductTemplate::defaultBlocks(),
        ]);
        $product->update(['product_template_id' => $template->id]);

        $off = json_encode($seo->forProduct($product->fresh())->schemas);
        $this->assertStringNotContainsString('"Product"', $off);
        $this->assertStringNotContainsString('"BreadcrumbList"', $off);
        // Organization stays on (default true when key missing).
        $this->assertStringContainsString('"Organization"', $off);
    }
}
