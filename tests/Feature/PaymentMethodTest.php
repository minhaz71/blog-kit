<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Payments\Gateways\OfflineGateway;
use App\Payments\PaymentManager;
use App\Services\Checkout\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_methods_are_seeded_and_enabled(): void
    {
        $this->assertDatabaseHas('payment_methods', ['key' => 'cash_on_delivery', 'is_active' => true]);
        $this->assertDatabaseHas('payment_methods', ['key' => 'card_on_delivery']);

        $enabled = collect(app(PaymentManager::class)->enabled())->map->key()->all();
        $this->assertContains('cash_on_delivery', $enabled);
        $this->assertContains('card_on_delivery', $enabled);
    }

    public function test_key_is_auto_generated_from_name(): void
    {
        $m = PaymentMethod::create(['name' => 'Pay at Store Counter', 'is_active' => true]);
        $this->assertSame('pay_at_store_counter', $m->key);
    }

    public function test_offline_gateway_exposes_name_and_message(): void
    {
        $method = PaymentMethod::where('key', 'card_on_delivery')->first();
        $gateway = new OfflineGateway($method);

        $this->assertSame('card_on_delivery', $gateway->key());
        $this->assertSame('Card on Delivery', $gateway->title());
        $this->assertStringContainsString('card on delivery', strtolower((string) $gateway->instructions()));
    }

    public function test_manager_resolves_and_reports_enabled(): void
    {
        $manager = app(PaymentManager::class);
        $this->assertTrue($manager->isEnabled('cash_on_delivery'));
        $this->assertInstanceOf(OfflineGateway::class, $manager->gateway('cash_on_delivery'));

        PaymentMethod::where('key', 'card_on_delivery')->update(['is_active' => false]);
        $this->assertFalse($manager->isEnabled('card_on_delivery'));
    }

    public function test_named_surcharge_is_added_to_checkout_total(): void
    {
        // A card-on-delivery method with a 5 flat + 2% surcharge named "Card payment charge".
        PaymentMethod::where('key', 'card_on_delivery')->update([
            'fee_fixed' => 5, 'fee_percent' => 2, 'fee_label' => 'Card payment charge',
        ]);

        $product = Product::create(['name' => 'Widget', 'slug' => 'widget', 'type' => 'simple', 'price' => 100, 'status' => 'published', 'stock_status' => 'in_stock']);
        $cart = Cart::create(['session_id' => 'test']);
        $cart->items()->create(['product_id' => $product->id, 'qty' => 1]);
        $cart->load('items.product');

        $checkout = app(CheckoutService::class);

        $with = $checkout->totals($cart, ['country' => 'AE'], null, 'card_on_delivery');
        // 100 + 5 flat + 2% (=2) = 107
        $this->assertSame(7.0, $with['payment_fee']);
        $this->assertSame('Card payment charge', $with['payment_fee_label']);
        $this->assertSame(107.0, $with['total']);

        // Cash on delivery has no fee → no surcharge line.
        $without = $checkout->totals($cart, ['country' => 'AE'], null, 'cash_on_delivery');
        $this->assertSame(0.0, $without['payment_fee']);
        $this->assertNull($without['payment_fee_label']);
        $this->assertSame(100.0, $without['total']);
    }
}
