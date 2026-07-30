<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentRule;
use App\Models\Product;
use App\Services\Payments\PaymentRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function cartWithPrice(float $unit, int $qty = 1): Cart
    {
        $product = Product::create([
            'name' => 'P '.uniqid(), 'slug' => 'p-'.uniqid(), 'sku' => 'S-'.uniqid(),
            'type' => 'simple', 'price' => $unit, 'stock_status' => 'in_stock', 'stock_qty' => 100,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
        $cart = Cart::create(['session_id' => 'sess-'.uniqid(), 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'qty' => $qty]);

        return $cart->fresh(['items.product', 'items.variation']);
    }

    public function test_unconstrained_payment_is_always_allowed(): void
    {
        $cart = $this->cartWithPrice(50, 2);
        $svc = app(PaymentRuleService::class);

        $this->assertTrue($svc->isPaymentAllowed('stripe', $cart, ['country' => 'US'], null));
    }

    public function test_cod_blocked_below_minimum_order(): void
    {
        PaymentRule::create([
            'name' => 'COD only over $50',
            'payment_method' => 'cod',
            'min_order_amount' => 50,
            'is_active' => true,
        ]);

        $small = $this->cartWithPrice(10, 2);  // 20
        $big = $this->cartWithPrice(30, 2);    // 60

        $svc = app(PaymentRuleService::class);
        $this->assertFalse($svc->isPaymentAllowed('cod', $small, ['country' => 'US'], null));
        $this->assertTrue($svc->isPaymentAllowed('cod', $big, ['country' => 'US'], null));
    }

    public function test_cod_surcharge_adds_to_total(): void
    {
        PaymentRule::create([
            'name' => 'COD surcharge',
            'payment_method' => 'cod',
            'fee_amount' => 10,
            'is_active' => true,
        ]);

        $cart = $this->cartWithPrice(100);
        $adjust = app(PaymentRuleService::class)->adjustmentFor('cod', $cart, ['country' => 'US'], null);

        $this->assertSame(10.0, $adjust['fee']);
        $this->assertSame(10.0, $adjust['amount']);
    }

    public function test_prepaid_percent_discount_applies(): void
    {
        PaymentRule::create([
            'name' => '5% off prepaid',
            'payment_method' => 'stripe',
            'discount_percent' => 5,
            'is_active' => true,
        ]);

        $cart = $this->cartWithPrice(200);
        $adjust = app(PaymentRuleService::class)->adjustmentFor('stripe', $cart, ['country' => 'US'], null);

        $this->assertSame(10.0, $adjust['discount']);   // 5% of 200
        $this->assertSame(-10.0, $adjust['amount']);
    }

    public function test_city_allow_list_gates_payment(): void
    {
        PaymentRule::create([
            'name' => 'COD only Dubai',
            'payment_method' => 'cod',
            'allowed_cities' => ['dubai'],
            'is_active' => true,
        ]);

        $cart = $this->cartWithPrice(100);
        $svc = app(PaymentRuleService::class);

        $this->assertTrue($svc->isPaymentAllowed('cod', $cart, ['country' => 'AE', 'city' => 'Dubai'], null));
        $this->assertFalse($svc->isPaymentAllowed('cod', $cart, ['country' => 'AE', 'city' => 'Sharjah'], null));
    }

    public function test_prepaid_free_shipping_flag(): void
    {
        PaymentRule::create([
            'name' => 'Free shipping prepaid',
            'payment_method' => 'stripe',
            'free_shipping' => true,
            'is_active' => true,
        ]);

        $cart = $this->cartWithPrice(50);
        $adjust = app(PaymentRuleService::class)->adjustmentFor('stripe', $cart, ['country' => 'US'], null);
        $this->assertTrue($adjust['free_shipping']);

        $adjustCod = app(PaymentRuleService::class)->adjustmentFor('cod', $cart, ['country' => 'US'], null);
        $this->assertFalse($adjustCod['free_shipping']);
    }

    public function test_disallow_coupons_flag(): void
    {
        PaymentRule::create([
            'name' => 'No coupons on COD',
            'payment_method' => 'cod',
            'disallow_coupons' => true,
            'is_active' => true,
        ]);

        $cart = $this->cartWithPrice(50);
        $adjust = app(PaymentRuleService::class)->adjustmentFor('cod', $cart, ['country' => 'US'], null);
        $this->assertTrue($adjust['disallow_coupons']);
    }
}
