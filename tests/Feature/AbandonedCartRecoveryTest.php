<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Setting;
use App\Mail\TemplatedMail;
use App\Support\AbandonedCartFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AbandonedCartRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        return Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 100, 'status' => 'published', 'visibility' => 'visible',
            'stock_status' => 'in_stock', 'manage_stock' => false,
        ]);
    }

    protected function seedAbandonedTemplate(): void
    {
        \App\Models\EmailTemplate::create([
            'key' => 'abandoned_cart', 'name' => 'Abandoned cart', 'subject' => 'You left something',
            'heading' => 'Still there?', 'recipient' => 'customer',
            'body' => '<p>Hi {{customer_name}}, {{item_count}} item(s) waiting. {{cart_url}}</p>', 'is_active' => true,
        ]);
    }

    /** A guest cart with items, captured email, idle $minutes ago. */
    protected function abandonedGuestCart(int $minutes = 40, array $attrs = []): Cart
    {
        $cart = Cart::create(array_merge([
            'session_id' => 'sess-'.uniqid(), 'status' => 'active', 'email' => 'guest@example.com', 'customer_name' => 'Guest',
        ], $attrs));
        $cart->items()->create(['product_id' => $this->product()->id, 'qty' => 2]);
        Cart::withoutTimestamps(fn () => $cart->forceFill(['updated_at' => now()->subMinutes($minutes)])->save());

        return $cart->fresh();
    }

    // ── Capture ───────────────────────────────────────────────────

    public function test_checkout_email_is_captured_onto_the_cart(): void
    {
        // The cart resolver is identical for guests (session) and members
        // (user_id); use a member cart for a deterministic assertion.
        $user = \App\Models\User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id, 'status' => 'active']);
        $cart->items()->create(['product_id' => $this->product()->id, 'qty' => 1]);

        $this->actingAs($user)
            ->postJson(route('cart.identify'), ['email' => 'lead@example.com', 'name' => 'Sam Lead', 'phone' => '+9715000'])
            ->assertOk();

        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'email' => 'lead@example.com', 'customer_name' => 'Sam Lead', 'phone' => '+9715000']);
    }

    public function test_identify_never_creates_an_empty_cart(): void
    {
        $this->postJson(route('cart.identify'), ['email' => 'nobody@example.com'])->assertOk();

        $this->assertDatabaseCount('carts', 0);
    }

    // ── Sequence ──────────────────────────────────────────────────

    public function test_first_stage_reminder_is_sent_and_stage_advances(): void
    {
        Mail::fake();
        $this->seedAbandonedTemplate();
        $cart = $this->abandonedGuestCart(40); // past the 30-min first stage

        $this->artisan('email:abandoned-cart')->assertExitCode(0);

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $m) => $m->hasTo('guest@example.com'));
        $cart->refresh();
        $this->assertSame(1, (int) $cart->reminder_stage);
        $this->assertNotNull($cart->last_reminder_at);
        // Anchor (updated_at) must NOT be bumped by sending the reminder.
        $this->assertTrue($cart->updated_at->lt(now()->subMinutes(30)));
    }

    public function test_does_not_resend_before_the_next_stage_is_due(): void
    {
        Mail::fake();
        $this->seedAbandonedTemplate();
        $cart = $this->abandonedGuestCart(40);

        $this->artisan('email:abandoned-cart'); // stage 0 -> 1
        $this->artisan('email:abandoned-cart'); // stage 1 needs 1 day; nothing due

        Mail::assertSentCount(1);
        $this->assertSame(1, (int) $cart->fresh()->reminder_stage);
    }

    public function test_missing_template_does_not_advance_the_stage(): void
    {
        Mail::fake();
        // No abandoned_cart template seeded → send() returns false.
        $cart = $this->abandonedGuestCart(40);

        $this->artisan('email:abandoned-cart');

        Mail::assertNothingSent();
        // Stage must NOT advance — the cart is retried next run, never silently
        // burned through the sequence sending nothing.
        $this->assertSame(0, (int) $cart->fresh()->reminder_stage);
    }

    public function test_cart_reminder_email_shows_the_cart_not_order_chrome(): void
    {
        $this->seedAbandonedTemplate();
        $tpl = \App\Models\EmailTemplate::where('key', 'abandoned_cart')->first();
        $r = $tpl->render(['customer_name' => 'Sam', 'item_count' => 2, 'store_name' => 'Store', 'cart_url' => 'http://x/cart']);

        $html = (new TemplatedMail($r['subject'], $r['heading'] ?: $r['subject'], $r['body'], [
            'audience' => 'cart',
            'cart_url' => 'http://x/cart',
            'subtotal' => 'AED 100',
            'items' => [['name' => 'TEREA Amber', 'qty' => 1, 'total' => 'AED 100', 'options' => null, 'image' => null]],
        ]))->render();

        // Cart context, NOT order context.
        $this->assertStringContainsString('Complete your order', $html);
        $this->assertStringContainsString('Your saved cart', $html);
        $this->assertStringContainsString('TEREA Amber', $html);
        $this->assertStringNotContainsString('View Your Order', $html);
        $this->assertStringNotContainsString('Download PDF Invoice', $html);
        $this->assertStringNotContainsString('Order Confirmed', $html); // no fulfilment tracker
    }

    public function test_recovery_link_restores_the_cart_into_the_current_session(): void
    {
        $cart = Cart::create(['session_id' => 'old-device-session', 'status' => 'active', 'email' => 'guest@example.com']);
        $cart->items()->create(['product_id' => $this->product()->id, 'qty' => 1]);

        $this->get($cart->recoveryUrl())->assertRedirect(route('cart.index'));

        // The cart was adopted into this request's session (no longer the old one).
        $this->assertNotSame('old-device-session', $cart->fresh()->session_id);
    }

    public function test_forged_recovery_code_is_rejected(): void
    {
        $cart = Cart::create(['session_id' => 'x', 'status' => 'active']);

        $this->get(route('cart.restore', ['cart' => $cart->id, 'code' => str_repeat('a', 64)]))
            ->assertNotFound();
    }

    public function test_disabled_flow_sends_nothing(): void
    {
        Mail::fake();
        Setting::set('abandoned.enabled', false);
        $this->abandonedGuestCart(40);

        $this->artisan('email:abandoned-cart');

        Mail::assertNothingSent();
    }

    public function test_cart_without_a_reachable_email_is_skipped(): void
    {
        Mail::fake();
        $cart = Cart::create(['session_id' => 'anon', 'status' => 'active']); // no email, no user
        $cart->items()->create(['product_id' => $this->product()->id, 'qty' => 1]);
        Cart::withoutTimestamps(fn () => $cart->forceFill(['updated_at' => now()->subMinutes(40)])->save());

        $this->artisan('email:abandoned-cart');

        Mail::assertNothingSent();
        $this->assertSame(0, (int) $cart->fresh()->reminder_stage);
    }

    public function test_recent_cart_is_not_yet_abandoned(): void
    {
        Mail::fake();
        $this->abandonedGuestCart(5); // only 5 min idle < 30-min first stage

        $this->artisan('email:abandoned-cart');

        Mail::assertNothingSent();
    }

    // ── Cleanup interplay ─────────────────────────────────────────

    public function test_cleanup_preserves_carts_still_in_sequence(): void
    {
        // In-sequence reachable cart, old, not finished.
        $inFlow = $this->abandonedGuestCart(60 * 24 * 40, ['reminder_stage' => 1]);
        // Unreachable old cart -> should be deleted.
        $anon = Cart::create(['session_id' => 'old-anon', 'status' => 'active']);
        Cart::withoutTimestamps(fn () => $anon->forceFill(['updated_at' => now()->subDays(40)])->save());

        $this->artisan('ecommerce:clear-expired-carts --days=30');

        $this->assertDatabaseHas('carts', ['id' => $inFlow->id]);
        $this->assertDatabaseMissing('carts', ['id' => $anon->id]);
    }

    // ── Recovery tracking ─────────────────────────────────────────

    public function test_converting_a_reminded_cart_records_recovery(): void
    {
        $product = $this->product();
        $cart = Cart::create(['session_id' => 'rec', 'status' => 'active', 'email' => 'r@example.com', 'reminder_stage' => 2]);
        $cart->items()->create(['product_id' => $product->id, 'qty' => 1]);
        $cart->load('items.product');

        $addr = ['first_name' => 'R', 'last_name' => 'C', 'address_line_1' => '1 St', 'city' => 'Dubai', 'country' => 'AE'];
        $order = app(\App\Services\Checkout\CheckoutService::class)->placeOrder($cart, [
            'email' => 'r@example.com', 'phone' => null, 'note' => null,
            'payment_method' => 'cash_on_delivery', 'shipping_method_id' => null,
            'billing_address' => $addr, 'shipping_address' => $addr,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $cart->refresh();
        $this->assertSame('converted', $cart->status);
        $this->assertSame($order->id, (int) $cart->order_id);
        $this->assertNotNull($cart->recovered_at);
    }

    // ── Flow config ───────────────────────────────────────────────

    public function test_flow_defaults_and_custom_stages(): void
    {
        $this->assertSame(4, AbandonedCartFlow::stageCount());
        $this->assertSame(30, AbandonedCartFlow::firstDelayMinutes());

        Setting::set('abandoned.stages', [
            ['enabled' => true, 'delay' => 2, 'unit' => 'hours', 'template' => 'abandoned_cart'],
            ['enabled' => false, 'delay' => 3, 'unit' => 'days', 'template' => 'abandoned_cart'],
        ]);
        $stages = AbandonedCartFlow::stages();
        $this->assertCount(1, $stages); // disabled stage dropped
        $this->assertSame(120, $stages[0]['minutes']);
    }
}
