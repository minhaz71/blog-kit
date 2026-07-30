<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariationStockQtyTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_stock_qty_is_coerced_to_zero_on_save(): void
    {
        $product = Product::create([
            'name' => 'Var Parent', 'slug' => 'var-parent', 'sku' => 'VP', 'type' => 'variable',
            'price' => 100, 'stock_status' => 'in_stock', 'stock_qty' => 0,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);

        // A blank stock quantity must not violate the NOT NULL column.
        $variation = ProductVariation::create([
            'product_id' => $product->id,
            'sku' => 'VP-1',
            'price' => 130,
            'stock_qty' => null,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->assertSame(0, (int) $variation->fresh()->stock_qty);
    }
}
