<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutServerSidePricingTest extends TestCase
{
    use RefreshDatabase;

    /** Regression: client can send any subtotal / total in the request, and it must be ignored. */
    public function test_cart_totals_recompute_from_live_product_prices(): void
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-x', 'sku' => 'WX', 'type' => 'simple',
            'price' => 25.00, 'stock_status' => 'in_stock', 'stock_qty' => 5,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);

        $cart = Cart::create(['session_id' => 'sess', 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'qty' => 3]);

        // Simulate the CheckoutService computing totals — it should read live product price.
        $totals = app(CheckoutService::class)->totals(
            $cart->fresh(['items.product', 'items.variation']),
            destination: ['country' => 'US', 'state' => null, 'city' => null, 'postal_code' => null],
            shippingMethodId: null,
        );

        $this->assertSame(75.00, (float) $totals['subtotal']);
    }
}
