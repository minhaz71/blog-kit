<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\CheckoutFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CheckoutFieldsConfigTest extends TestCase
{
    use RefreshDatabase;

    private function setCheckout(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            Setting::set("checkout.{$k}", $v);
        }
        Cache::forget('settings.checkout');
    }

    public function test_default_config_requires_last_name_and_leaves_postal_optional(): void
    {
        $rules = CheckoutFields::addressRules(['AE']);

        $this->assertSame('required', $rules['last_name'][0]);
        $this->assertSame('nullable', $rules['postal_code'][0]);
        $this->assertContains('required', $rules['country']);
    }

    public function test_disabling_last_name_makes_it_not_required(): void
    {
        $this->setCheckout(['last_name_enabled' => false]);

        $rules = CheckoutFields::addressRules(['AE']);

        $this->assertSame('nullable', $rules['last_name'][0]);
    }

    public function test_enabling_required_postal_code_makes_it_required(): void
    {
        $this->setCheckout(['postal_code_enabled' => true, 'postal_code_required' => true]);

        $rules = CheckoutFields::addressRules(['AE']);

        $this->assertSame('required', $rules['postal_code'][0]);
    }

    public function test_disabled_field_is_accepted_but_never_blocks(): void
    {
        $this->setCheckout(['state_enabled' => false]);

        $rules = CheckoutFields::addressRules(['AE']);

        // A stale/tampered value for a hidden field must be nullable, not an error.
        $this->assertSame('nullable', $rules['state'][0]);
    }

    public function test_custom_labels_are_exposed(): void
    {
        $this->setCheckout(['state_label' => 'Emirate', 'postal_code_label' => 'PO Box']);

        $fields = CheckoutFields::fields();

        $this->assertSame('Emirate', $fields['state']['label']);
        $this->assertSame('PO Box', $fields['postal_code']['label']);
    }

    public function test_checkout_browser_title_comes_from_setting(): void
    {
        $product = \App\Models\Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 100, 'status' => 'published', 'visibility' => 'visible',
            'stock_status' => 'in_stock', 'manage_stock' => false,
        ]);

        $this->setCheckout(['browser_title' => 'Secure Checkout Page']);
        $this->actingAs(\App\Models\User::factory()->create());
        $this->post(route('cart.add'), ['product_id' => $product->id, 'qty' => 1]);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Secure Checkout Page', false);
    }

    public function test_checkout_browser_title_defaults_when_unset(): void
    {
        $product = \App\Models\Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber-2', 'type' => 'simple',
            'price' => 100, 'status' => 'published', 'visibility' => 'visible',
            'stock_status' => 'in_stock', 'manage_stock' => false,
        ]);

        $this->actingAs(\App\Models\User::factory()->create());
        $this->post(route('cart.add'), ['product_id' => $product->id, 'qty' => 1]);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('<title>', false); // renders a title, no error
    }
}
