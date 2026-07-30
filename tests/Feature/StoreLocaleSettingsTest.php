<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WooCommerce-style shop settings: selling locations restrict checkout
 * countries (locked when only one), and the currency display (symbol text,
 * decimals, position) is fully owner-customizable. All values live in the
 * settings table, so they ride along with every database backup.
 */
class StoreLocaleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_countries_defaults_to_all(): void
    {
        $countries = store_countries();

        $this->assertGreaterThan(150, count($countries));
        $this->assertSame('United Arab Emirates', $countries['AE']);
    }

    public function test_specific_mode_restricts_the_country_list(): void
    {
        Setting::set('general.sell_to_mode', 'specific');
        Setting::set('general.sell_to_countries', ['AE', 'SA']);

        $this->assertEqualsCanonicalizing(
            ['AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia'],
            store_countries(),
        );
    }

    public function test_specific_mode_with_empty_selection_falls_back_to_all(): void
    {
        Setting::set('general.sell_to_mode', 'specific');
        Setting::set('general.sell_to_countries', []);

        $this->assertGreaterThan(150, count(store_countries()));
    }

    public function test_price_format_honors_custom_symbol_decimals_and_position(): void
    {
        Setting::set('general.currency', 'AED');
        Setting::set('general.currency_symbol', 'AED ');
        Setting::set('general.currency_decimals', 0);

        $this->assertSame('AED 1,299', price_format(1299.00));

        // Arabic symbol, 2 decimals, symbol after the amount.
        Setting::set('general.currency_symbol', 'د.إ');
        Setting::set('general.currency_decimals', 2);
        Setting::set('general.currency_position', 'right');

        $this->assertSame('30.50 د.إ', price_format(30.5));
    }

    public function test_price_format_falls_back_to_currency_default_symbol(): void
    {
        Setting::set('general.currency', 'USD');
        Setting::set('general.currency_symbol', '');
        Setting::set('general.currency_decimals', 2);
        Setting::set('general.currency_position', 'left');

        $this->assertSame('$5.00', price_format(5));
    }

    /** A logged-in user with one product in their cart. */
    protected function userWithCart(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create();

        $product = \App\Models\Product::create([
            'name' => 'W', 'slug' => 'w-'.uniqid(), 'type' => 'simple', 'price' => 10,
            'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);

        $cart = \App\Models\Cart::create(['user_id' => $user->id, 'status' => 'active']);
        \App\Models\CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'qty' => 1]);

        return $user;
    }

    public function test_single_country_checkout_shows_it_locked(): void
    {
        Setting::set('general.sell_to_mode', 'specific');
        Setting::set('general.sell_to_countries', ['AE']);

        $response = $this->actingAs($this->userWithCart())->get(route('checkout.index'));

        $response->assertOk();
        // Locked: hidden input instead of a dropdown of other countries.
        $response->assertSee('type="hidden" name="shipping[country]" value="AE"', false);
        $response->assertSee('United Arab Emirates');
        $response->assertDontSee('<option value="US"', false);
    }

    public function test_checkout_address_endpoint_rejects_disallowed_country(): void
    {
        Setting::set('general.sell_to_mode', 'specific');
        Setting::set('general.sell_to_countries', ['AE']);

        $user = $this->userWithCart();

        $this->actingAs($user)
            ->postJson(route('checkout.shipping-options'), ['country' => 'US'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['country']);

        $this->actingAs($user)
            ->postJson(route('checkout.shipping-options'), ['country' => 'AE'])
            ->assertOk();
    }
}
