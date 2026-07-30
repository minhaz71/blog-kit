<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\Cart\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function cartWith(float $price, int $qty = 2): Cart
    {
        $product = Product::create([
            'name' => 'Test '.uniqid(),
            'slug' => 'test-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'type' => 'simple',
            'price' => $price,
            'stock_status' => 'in_stock',
            'stock_qty' => 100,
            'status' => 'published',
            'visibility' => 'visible',
            'published_at' => now(),
        ]);
        $cart = Cart::create(['session_id' => 'sess-'.uniqid(), 'status' => 'active']);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => $qty,
        ]);

        return $cart->fresh(['items.product']);
    }

    public function test_percent_coupon_discounts_10_percent_of_subtotal(): void
    {
        $cart = $this->cartWith(price: 50, qty: 4); // subtotal 200
        $coupon = Coupon::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        $this->assertSame(20.0, round(app(CouponService::class)->discountFor($coupon, $cart), 2));
    }

    public function test_fixed_cart_coupon_capped_at_subtotal(): void
    {
        $small = $this->cartWith(price: 5, qty: 3); // 15
        $big = $this->cartWith(price: 50, qty: 2);  // 100

        $coupon = Coupon::create(['code' => 'FLAT30', 'type' => 'fixed_cart', 'value' => 30, 'is_active' => true]);

        $this->assertSame(15.0, app(CouponService::class)->discountFor($coupon, $small));
        $this->assertSame(30.0, app(CouponService::class)->discountFor($coupon, $big));
    }

    public function test_expired_coupon_is_invalid(): void
    {
        $cart = $this->cartWith(price: 50);
        $coupon = Coupon::create([
            'code' => 'OLD', 'type' => 'percent', 'value' => 50, 'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate($coupon, $cart, null, 'x@example.com');
    }

    public function test_usage_limit_prevents_reuse(): void
    {
        $cart = $this->cartWith(price: 50);
        $coupon = Coupon::create([
            'code' => 'LIMIT1', 'type' => 'percent', 'value' => 20,
            'usage_limit' => 1, 'used_count' => 1, 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate($coupon, $cart, null, 'x@example.com');
    }

    public function test_minimum_order_amount_below_threshold_is_rejected(): void
    {
        $tiny = $this->cartWith(price: 10, qty: 2); // 20
        $coupon = Coupon::create([
            'code' => 'MIN50', 'type' => 'percent', 'value' => 10,
            'min_order_amount' => 50, 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate($coupon, $tiny, null, 'x@example.com');
    }

    public function test_minimum_order_amount_above_threshold_passes(): void
    {
        $enough = $this->cartWith(price: 40, qty: 2); // 80
        $coupon = Coupon::create([
            'code' => 'MIN50', 'type' => 'percent', 'value' => 10,
            'min_order_amount' => 50, 'is_active' => true,
        ]);

        app(CouponService::class)->validate($coupon, $enough, null, 'x@example.com');
        $this->assertTrue(true);
    }
}
