<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class CartOrphanedItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_prunes_items_whose_product_was_deleted(): void
    {
        $keep = Product::create([
            'name' => 'Kept', 'slug' => 'kept', 'sku' => 'K1', 'type' => 'simple',
            'price' => 10.00, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
        $gone = Product::create([
            'name' => 'Gone', 'slug' => 'gone', 'sku' => 'G1', 'type' => 'simple',
            'price' => 20.00, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);

        $cart = Cart::create(['session_id' => Session::getId(), 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $keep->id, 'qty' => 1]);
        $orphan = CartItem::create(['cart_id' => $cart->id, 'product_id' => $gone->id, 'qty' => 1]);

        // Product is soft-deleted → its cart item is now orphaned.
        $gone->delete();

        $resolved = app(CartService::class)->current();

        // The orphaned item is dropped (DB + in-memory) and only the valid one remains.
        $this->assertCount(1, $resolved->items);
        $this->assertDatabaseMissing('cart_items', ['id' => $orphan->id]);

        // Subtotal computes without dereferencing the missing product.
        $this->assertSame(10.00, (float) $resolved->subtotal());
    }
}
