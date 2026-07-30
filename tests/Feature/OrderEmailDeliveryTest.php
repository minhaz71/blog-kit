<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Email\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Order emails must actually go out (synchronously — no queue worker on shared
 * hosting), reach the customer, and reach EVERY comma-separated admin recipient.
 */
class OrderEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function seedTemplates(): void
    {
        EmailTemplate::create(['key' => 'order_confirmed', 'name' => 'c', 'subject' => 'Order {{order_number}}', 'heading' => 'Thanks', 'recipient' => 'customer', 'body' => '<p>Hi {{customer_name}}</p>', 'is_active' => true]);
        EmailTemplate::create(['key' => 'new_order_admin', 'name' => 'a', 'subject' => 'New {{order_number}}', 'heading' => 'New order', 'recipient' => 'admin', 'body' => '<p>New order</p>', 'is_active' => true]);
    }

    protected function makeOrder(): Order
    {
        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'status' => 'pending', 'currency' => 'USD',
            'subtotal' => 100, 'discount_total' => 0, 'shipping_total' => 10,
            'tax_total' => 0, 'payment_fee' => 0, 'total' => 110,
            'payment_method' => 'card_on_delivery', 'payment_status' => 'pending',
            'billing_address' => ['first_name' => 'Sam', 'last_name' => 'Shopper'],
            'shipping_address' => ['first_name' => 'Sam', 'last_name' => 'Shopper'],
            'customer_email' => 'sam@example.com',
        ]);
    }

    public function test_order_placed_emails_customer_and_all_admin_recipients(): void
    {
        Mail::fake();
        $this->seedTemplates();
        Setting::set('emails.admin_recipient', 'sales@store.com, owner@store.com , warehouse@store.com');

        event(new OrderPlaced($this->makeOrder()));

        // Customer confirmation.
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $m) => $m->hasTo('sam@example.com'));

        // Admin notification reaches every listed address in one send.
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $m) => $m->hasTo('sales@store.com')
            && $m->hasTo('owner@store.com')
            && $m->hasTo('warehouse@store.com'));

        Mail::assertSentCount(2);
    }

    public function test_emails_are_sent_not_queued(): void
    {
        Mail::fake();
        $this->seedTemplates();
        Setting::set('emails.admin_recipient', 'owner@store.com');

        event(new OrderPlaced($this->makeOrder()));

        // Sent synchronously — never queued (no worker on shared hosting).
        Mail::assertNothingQueued();
        Mail::assertSentCount(2);
    }

    public function test_recipient_normalization_dedupes_validates_and_caps(): void
    {
        $out = EmailService::normalizeRecipients('a@x.com, A@X.com; not-an-email  b@x.com');
        $this->assertSame(['a@x.com', 'b@x.com'], $out);

        $many = implode(',', array_map(fn ($i) => "u{$i}@x.com", range(1, 30)));
        $this->assertCount(20, EmailService::normalizeRecipients($many));

        // Array elements that are themselves comma-lists get split + deduped.
        $this->assertSame(
            ['a@x.com', 'b@x.com', 'c@x.com'],
            EmailService::normalizeRecipients(['a@x.com, b@x.com', 'c@x.com', 'A@X.com'])
        );
    }

    public function test_template_custom_recipients_also_receive_the_email(): void
    {
        Mail::fake();
        EmailTemplate::create([
            'key' => 'order_confirmed', 'name' => 'c', 'subject' => 'S', 'heading' => 'H',
            'recipient' => 'customer', 'custom_recipients' => 'records@store.com, warehouse@store.com',
            'body' => '<p>hi</p>', 'is_active' => true,
        ]);

        app(EmailService::class)->send('order_confirmed', 'buyer@example.com');

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $m) => $m->hasTo('buyer@example.com')
            && $m->hasTo('records@store.com')
            && $m->hasTo('warehouse@store.com'));
    }

    public function test_no_admin_recipient_still_emails_customer(): void
    {
        Mail::fake();
        $this->seedTemplates();
        Setting::set('emails.admin_recipient', '');
        Setting::set('emails.from_email', '');
        config(['mail.from.address' => null]);

        event(new OrderPlaced($this->makeOrder()));

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $m) => $m->hasTo('sam@example.com'));
        Mail::assertSentCount(1);
    }

    protected function orderWithItems(): Order
    {
        $bought = \App\Models\Product::create(['name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple', 'price' => 100, 'status' => 'published', 'visibility' => 'visible']);
        // A second visible product so "you may also like" has something to show.
        \App\Models\Product::create(['name' => 'TEREA Blue', 'slug' => 'terea-blue', 'type' => 'simple', 'price' => 90, 'status' => 'published', 'visibility' => 'visible']);

        $order = $this->makeOrder();
        $order->items()->create(['product_id' => $bought->id, 'name' => 'TEREA Amber', 'sku' => 'AMB', 'qty' => 1, 'unit_price' => 100, 'subtotal' => 100, 'total' => 100]);

        return $order->fresh('items');
    }

    protected function renderEmail(string $templateKey, Order $order): string
    {
        $svc = app(EmailService::class);
        $data = $svc->orderData($order);
        $data['audience'] = str_contains($templateKey, 'admin') ? 'admin' : 'customer';
        $tpl = EmailTemplate::where('key', $templateKey)->first();
        $r = $tpl->render($svc->orderVars($order));

        return (new TemplatedMail($r['subject'], $r['heading'] ?: $r['subject'], $r['body'], $data))->render();
    }

    public function test_customer_email_shows_invoice_button_tracker_and_related(): void
    {
        $this->seedTemplates();
        $order = $this->orderWithItems();

        $html = $this->renderEmail('order_confirmed', $order);

        $this->assertStringContainsString('Download PDF Invoice', $html);
        $this->assertStringContainsString('/invoice/'.$order->order_number.'/', $html);
        $this->assertStringContainsString('You may also like', $html);
        $this->assertStringContainsString('Order Confirmed', $html); // tracker step label
    }

    public function test_admin_email_omits_customer_extras(): void
    {
        $this->seedTemplates();
        $order = $this->orderWithItems();

        $html = $this->renderEmail('new_order_admin', $order);

        $this->assertStringNotContainsString('Download PDF Invoice', $html);
        $this->assertStringNotContainsString('You may also like', $html);
    }

    public function test_invoice_button_can_be_toggled_off(): void
    {
        $this->seedTemplates();
        Setting::set('emails.email_show_invoice_button', false);
        $order = $this->orderWithItems();

        $html = $this->renderEmail('order_confirmed', $order);

        $this->assertStringNotContainsString('Download PDF Invoice', $html);
    }
}
