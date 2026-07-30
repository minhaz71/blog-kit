<?php

namespace Tests\Feature;

use App\Models\CustomSchema;
use App\Models\Product;
use App\Services\Seo\SeoManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $slug): Product
    {
        return Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'type' => 'simple', 'price' => 10, 'status' => 'published',
        ]);
    }

    public function test_global_and_per_product_schemas_are_injected_into_the_graph(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna');

        CustomSchema::create([
            'name' => 'Warranty (global)',
            'json_ld' => ['@type' => 'WarrantyPromise', 'durationOfWarranty' => 'P1Y'],
            'is_active' => true,
        ]);

        CustomSchema::create([
            'name' => 'Amber demo video',
            'schemable_type' => Product::class,
            'schemable_id' => $amber->id,
            // Own @context must be stripped — the @graph wrapper provides it.
            'json_ld' => ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => 'Amber demo'],
            'is_active' => true,
        ]);

        CustomSchema::create([
            'name' => 'Disabled block',
            'json_ld' => ['@type' => 'Thing', 'name' => 'inactive'],
            'is_active' => false,
        ]);

        $jsonAmber = app(SeoManager::class)->jsonLd(app(SeoManager::class)->forProduct($amber->fresh()));
        $jsonSienna = app(SeoManager::class)->jsonLd(app(SeoManager::class)->forProduct($sienna->fresh()));

        // Global block on every page; per-product block only on its product.
        $this->assertStringContainsString('WarrantyPromise', $jsonAmber);
        $this->assertStringContainsString('VideoObject', $jsonAmber);
        $this->assertStringContainsString('WarrantyPromise', $jsonSienna);
        $this->assertStringNotContainsString('VideoObject', $jsonSienna);

        // Inactive blocks never render; nested @context stripped.
        $this->assertStringNotContainsString('inactive', $jsonAmber);
        $this->assertSame(1, substr_count($jsonAmber, '"@context"'));
    }
}
