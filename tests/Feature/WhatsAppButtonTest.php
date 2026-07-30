<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        return Product::create([
            'name' => 'Terea Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
    }

    public function test_hidden_by_default(): void
    {
        $this->get('/')->assertOk()->assertDontSee('wa-fab');
    }

    public function test_hidden_when_enabled_but_no_number(): void
    {
        Setting::set('whatsapp.enabled', true);
        Setting::set('whatsapp.number', '');

        $this->get('/')->assertOk()->assertDontSee('wa-fab');
    }

    public function test_shows_on_storefront_with_stripped_number_and_prefilled_message(): void
    {
        Setting::set('whatsapp.enabled', true);
        Setting::set('whatsapp.number', '+971 50 123 4567');
        Setting::set('whatsapp.position', 'left');
        Setting::set('whatsapp.delay_seconds', 3);
        Setting::set('whatsapp.message', 'Hi! A question about TEREA.');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('wa-fab wa-left', $html);
        // Number stripped to digits only for the wa.me deep link.
        $this->assertStringContainsString('https://wa.me/971501234567?text=', $html);
        $this->assertStringContainsString('data-wa-delay="3"', $html);
        // Message URL-encoded into the link.
        $this->assertStringContainsString(rawurlencode('Hi! A question about TEREA.'), $html);
    }

    public function test_position_right_variant(): void
    {
        Setting::set('whatsapp.enabled', true);
        Setting::set('whatsapp.number', '971501234567');
        Setting::set('whatsapp.position', 'right');

        $this->get('/')->assertOk()->assertSee('wa-fab wa-right', false);
    }

    public function test_appears_on_product_pages_too(): void
    {
        Setting::set('whatsapp.enabled', true);
        Setting::set('whatsapp.number', '971501234567');
        $product = $this->product();

        $this->get($product->url())->assertOk()->assertSee('wa-fab', false);
    }

    public function test_never_shows_in_admin(): void
    {
        Setting::set('whatsapp.enabled', true);
        Setting::set('whatsapp.number', '971501234567');

        // Admin uses the Filament layout, not the storefront layout.
        $this->get('/admin/login')->assertOk()->assertDontSee('wa-fab');
    }
}
