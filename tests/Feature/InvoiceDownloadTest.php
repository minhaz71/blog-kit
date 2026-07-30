<?php

namespace Tests\Feature;

use App\Models\InvoiceDownload;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public, login-free invoice link (used by the order email) must:
 *  - stream a PDF for anyone with the correct per-order code (guests included),
 *  - reject a wrong/forged code,
 *  - record a trackable download event (so the store sees how many opened it).
 */
class InvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(): Order
    {
        $product = Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 100, 'status' => 'published', 'visibility' => 'visible',
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'status' => 'processing', 'currency' => 'USD',
            'subtotal' => 100, 'discount_total' => 0, 'shipping_total' => 10,
            'tax_total' => 0, 'total' => 110,
            'payment_method' => 'card_on_delivery', 'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Sam', 'last_name' => 'Shopper', 'address_line_1' => '1 St', 'city' => 'Dubai', 'country' => 'UAE'],
            'shipping_address' => ['first_name' => 'Sam', 'last_name' => 'Shopper', 'address_line_1' => '1 St', 'city' => 'Dubai', 'country' => 'UAE'],
            'customer_email' => 'sam@example.com',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'name' => 'TEREA Amber', 'sku' => 'AMB',
            'qty' => 1, 'unit_price' => 100, 'subtotal' => 100, 'total' => 100,
        ]);

        return $order;
    }

    public function test_valid_code_downloads_pdf_and_records_the_event(): void
    {
        $order = $this->makeOrder();

        $res = $this->get($order->invoiceUrl('email'));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $res->headers->get('content-disposition'));

        $this->assertDatabaseHas('invoice_downloads', [
            'order_id' => $order->id, 'source' => 'email', 'token' => $order->invoiceCode(),
        ]);
    }

    public function test_forged_code_is_rejected(): void
    {
        $order = $this->makeOrder();

        // Wrong code is indistinguishable from an unknown order (both 404).
        $this->get(route('invoice.download', [
            'orderNumber' => $order->order_number,
            'code' => str_repeat('a', 64),
        ]))->assertNotFound();

        $this->assertSame(0, InvoiceDownload::count());
    }

    public function test_source_is_recorded_from_query(): void
    {
        $order = $this->makeOrder();

        $this->get($order->invoiceUrl('admin'))->assertOk();

        $this->assertDatabaseHas('invoice_downloads', ['order_id' => $order->id, 'source' => 'admin']);
    }

    public function test_bots_do_not_inflate_the_count(): void
    {
        $order = $this->makeOrder();

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get($order->invoiceUrl('email'))->assertOk();

        $this->assertSame(0, InvoiceDownload::count());
    }

    public function test_account_route_downloads_for_the_owner_and_records_source(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $order = $this->makeOrder();
        $order->update(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('account.invoice', $order->order_number))
            ->assertOk();

        $this->assertDatabaseHas('invoice_downloads', ['order_id' => $order->id, 'source' => 'account']);
    }

    public function test_invoice_code_is_stable_and_verified(): void
    {
        $order = $this->makeOrder();

        $this->assertSame(64, strlen($order->invoiceCode()));
        $this->assertTrue($order->verifyInvoiceCode($order->invoiceCode()));
        $this->assertFalse($order->verifyInvoiceCode('nope'));
    }
}
