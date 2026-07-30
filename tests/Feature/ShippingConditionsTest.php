<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingConditionsTest extends TestCase
{
    use RefreshDatabase;

    protected function cartWith(float $unit, int $qty, float $weight = 0): Cart
    {
        $product = Product::create([
            'name' => 'P '.uniqid(), 'slug' => 'p-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'type' => 'simple', 'price' => $unit, 'weight' => $weight,
            'stock_status' => 'in_stock', 'stock_qty' => 100,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
        $cart = Cart::create(['session_id' => 'sess-'.uniqid(), 'status' => 'active']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'qty' => $qty]);

        return $cart->fresh(['items.product', 'items.variation']);
    }

    protected function zone(): ShippingZone
    {
        return ShippingZone::create([
            'name' => 'US', 'countries' => ['US'], 'is_active' => true,
        ]);
    }

    public function test_quantity_condition_filters_methods(): void
    {
        $zone = $this->zone();
        $method = ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'type' => 'flat_rate',
            'title' => 'Bulk only',
            'cost' => 5,
            'is_active' => true,
            'conditions' => ['min_qty' => 5],
        ]);

        $small = $this->cartWith(10, qty: 2);
        $big = $this->cartWith(10, qty: 6);

        $svc = app(ShippingCalculator::class);
        $this->assertEmpty($svc->optionsFor($small, ['country' => 'US']), 'method requires 5+ items — small cart should get 0 options.');
        $this->assertNotEmpty($svc->optionsFor($big, ['country' => 'US']));
    }

    public function test_weight_condition_filters_methods(): void
    {
        $zone = $this->zone();
        ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'type' => 'flat_rate',
            'title' => 'Heavy only',
            'cost' => 20,
            'is_active' => true,
            'conditions' => ['min_weight_kg' => 10],
        ]);

        $light = $this->cartWith(10, qty: 1, weight: 1);
        $heavy = $this->cartWith(10, qty: 1, weight: 15);

        $svc = app(ShippingCalculator::class);
        $this->assertEmpty($svc->optionsFor($light, ['country' => 'US']));
        $this->assertNotEmpty($svc->optionsFor($heavy, ['country' => 'US']));
    }

    public function test_postcode_prefix_allowlist(): void
    {
        $zone = $this->zone();
        ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'type' => 'flat_rate',
            'title' => 'SW postal only',
            'cost' => 5,
            'is_active' => true,
            'conditions' => ['allowed_postcodes' => ['SW*']],
        ]);

        $cart = $this->cartWith(10, qty: 1);
        $svc = app(ShippingCalculator::class);

        $this->assertNotEmpty($svc->optionsFor($cart, ['country' => 'US', 'postal_code' => 'SW1A 1AA']));
        $this->assertEmpty($svc->optionsFor($cart, ['country' => 'US', 'postal_code' => 'E1 6AN']));
    }

    public function test_weight_based_tier_pricing(): void
    {
        $zone = $this->zone();
        ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'type' => 'weight_based',
            'title' => 'Weight tiers',
            'cost' => 5,
            'is_active' => true,
            'weight_tiers' => [
                ['up_to_kg' => 1, 'cost' => 5],
                ['up_to_kg' => 5, 'cost' => 12],
                ['up_to_kg' => 20, 'cost' => 25],
            ],
        ]);

        $svc = app(ShippingCalculator::class);
        $tiny = $this->cartWith(10, qty: 1, weight: 0.5);
        $mid = $this->cartWith(10, qty: 1, weight: 3);
        $huge = $this->cartWith(10, qty: 1, weight: 30);  // over last tier

        $this->assertSame(5.0, (float) $svc->optionsFor($tiny, ['country' => 'US'])[0]['cost']);
        $this->assertSame(12.0, (float) $svc->optionsFor($mid, ['country' => 'US'])[0]['cost']);
        $this->assertSame(25.0, (float) $svc->optionsFor($huge, ['country' => 'US'])[0]['cost']);
    }
}
