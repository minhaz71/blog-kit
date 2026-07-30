<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoUrlHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_and_checkout_pages_are_noindexed(): void
    {
        $product = Product::create([
            'name' => 'W', 'slug' => 'w', 'type' => 'simple', 'price' => 10,
            'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);

        $user = \App\Models\User::factory()->create();
        $cart = \App\Models\Cart::create(['user_id' => $user->id, 'status' => 'active']);
        \App\Models\CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'qty' => 1]);

        $this->actingAs($user)->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_filtered_category_urls_are_noindexed_with_clean_canonical(): void
    {
        $category = Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true]);

        // Clean URL: indexable, canonical to itself.
        $clean = $this->get('/category/terea-uae')->assertOk();
        $clean->assertDontSee('noindex', false);
        $clean->assertSee('rel="canonical" href="'.url('/category/terea-uae').'"', false);

        // Filtered URL: noindex,follow + canonical still the clean URL.
        $filtered = $this->get('/category/terea-uae?sort=price_asc&min_price=20')->assertOk();
        $filtered->assertSee('content="noindex, follow"', false);
        $filtered->assertSee('rel="canonical" href="'.url('/category/terea-uae').'"', false);
    }

    public function test_product_offer_includes_shipping_details_when_configured(): void
    {
        Setting::set('general.currency', 'AED');
        Setting::set('general.sell_to_mode', 'specific');
        Setting::set('general.sell_to_countries', ['AE']);
        Setting::set('seo.shipping_rate', 0);
        Setting::set('seo.shipping_transit_days', 1);
        Setting::set('seo.return_policy_days', 7);

        $product = Product::create([
            'name' => 'W', 'slug' => 'w-ship', 'type' => 'simple', 'price' => 30,
            'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);

        $offer = app(\App\Services\Seo\SchemaGenerator::class)->offer($product);

        $this->assertSame('OfferShippingDetails', $offer['shippingDetails']['@type']);
        $this->assertSame('0.00', $offer['shippingDetails']['shippingRate']['value']);
        $this->assertSame('AED', $offer['shippingDetails']['shippingRate']['currency']);
        $this->assertSame('AE', $offer['shippingDetails']['shippingDestination']['addressCountry']);
        $this->assertSame(1, $offer['shippingDetails']['deliveryTime']['transitTime']['maxValue']);
        $this->assertSame('AE', $offer['hasMerchantReturnPolicy']['applicableCountry']);

        // No rate configured → no shippingDetails (never emit guesses).
        Setting::set('seo.shipping_rate', '');
        $this->assertArrayNotHasKey('shippingDetails', app(\App\Services\Seo\SchemaGenerator::class)->offer($product));
    }

    public function test_local_business_schema_carries_full_gmb_style_data(): void
    {
        Setting::set('seo.local_business_enabled', true);
        Setting::set('seo.local_business_type', 'Store');
        Setting::set('seo.local_business_phone', '+97150000000');
        Setting::set('seo.local_business_email', 'shop@tereahub.ae');
        Setting::set('seo.local_business_address', 'Sheikh Zayed Road 1');
        Setting::set('seo.local_business_city', 'Dubai');
        Setting::set('seo.local_business_region', 'Dubai');
        Setting::set('seo.local_business_postal_code', '00000');
        Setting::set('seo.local_business_country', 'AE');
        Setting::set('seo.local_business_latitude', '25.2048');
        Setting::set('seo.local_business_longitude', '55.2708');
        Setting::set('seo.local_business_map_url', 'https://maps.google.com/?cid=123');
        Setting::set('seo.local_business_price_range', 'AED 30 - 300');
        Setting::set('seo.local_business_payment', 'Cash on delivery, Card on delivery');
        Setting::set('seo.local_business_area_served', 'Dubai, Sharjah, Ajman');
        Setting::set('seo.local_business_hours', [
            ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'opens' => '09:00', 'closes' => '23:00'],
            ['days' => ['Sunday'], 'opens' => '14:00', 'closes' => '22:00'],
        ]);

        $schema = app(\App\Services\Seo\SchemaGenerator::class)->localBusiness();

        $this->assertSame('Store', $schema['@type']);
        $this->assertSame('Dubai', $schema['address']['addressLocality']);
        $this->assertSame('00000', $schema['address']['postalCode']);
        $this->assertSame(25.2048, $schema['geo']['latitude']);
        $this->assertSame('https://maps.google.com/?cid=123', $schema['hasMap']);
        $this->assertSame('AED 30 - 300', $schema['priceRange']);
        $this->assertSame('Cash on delivery, Card on delivery', $schema['paymentAccepted']);

        // Structured opening hours — Google's preferred format.
        $this->assertCount(2, $schema['openingHoursSpecification']);
        $this->assertSame('09:00', $schema['openingHoursSpecification'][0]['opens']);
        $this->assertContains('Saturday', $schema['openingHoursSpecification'][0]['dayOfWeek']);

        // Service area as City entities.
        $this->assertSame(['@type' => 'City', 'name' => 'Sharjah'], $schema['areaServed'][1]);

        // And it renders on the homepage graph.
        $this->get('/')->assertSee('openingHoursSpecification');
    }

    public function test_multi_location_local_business_schema_renders(): void
    {
        Setting::set('seo.locations', [
            ['name' => 'Terea Hub Dubai', 'city' => 'Dubai', 'type' => 'Store', 'country' => 'AE'],
            ['name' => 'Terea Hub Sharjah', 'city' => 'Sharjah', 'phone' => '+9715xxxxxxx'],
            ['name' => '', 'city' => 'skipped — no name'],
        ]);

        $blocks = app(\App\Services\Seo\SchemaGenerator::class)->localBusinessLocations();

        $this->assertCount(2, $blocks);
        $this->assertSame('Terea Hub Dubai', $blocks[0]['name']);
        $this->assertSame('Dubai', $blocks[0]['address']['addressLocality']);
        $this->assertSame(['@id' => url('/').'#organization'], $blocks[0]['parentOrganization']);

        // And they render into the homepage graph.
        $this->get('/')->assertSee('Terea Hub Sharjah');
    }
}
