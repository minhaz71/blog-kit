<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrder(string $status = 'pending'): Order
    {
        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'status' => $status,
            'currency' => 'USD',
            'subtotal' => 0, 'discount_total' => 0, 'shipping_total' => 10,
            'tax_total' => 0, 'payment_fee' => 5, 'payment_fee_label' => 'Card payment charge',
            'total' => 0,
            'payment_method' => 'card_on_delivery', 'payment_status' => 'pending',
            'billing_address' => ['first_name' => 'A', 'last_name' => 'B'],
            'shipping_address' => ['first_name' => 'A', 'last_name' => 'B'],
            'customer_email' => 'a@b.com',
        ]);
    }

    public function test_status_helpers_classify_editable_and_sales(): void
    {
        $this->assertTrue($this->makeOrder('pending')->isEditable());
        $this->assertFalse($this->makeOrder('processing')->isEditable());

        $this->assertTrue($this->makeOrder('completed')->isFinalSale());
        $this->assertFalse($this->makeOrder('processing')->isFinalSale());

        $this->assertTrue($this->makeOrder('on_hold')->isInProcess());
        $this->assertFalse($this->makeOrder('completed')->isInProcess());
    }

    public function test_recalculate_totals_rebuilds_lines_and_order_money(): void
    {
        $product = Product::create(['name' => 'Widget', 'slug' => 'widget', 'type' => 'simple', 'price' => 25, 'status' => 'published', 'stock_status' => 'in_stock']);

        $order = $this->makeOrder('pending');
        // Add a raw line the way the admin repeater would (snapshot fields blank).
        $order->items()->create(['product_id' => $product->id, 'name' => '', 'qty' => 3, 'unit_price' => 0, 'subtotal' => 0, 'total' => 0]);

        $order->recalculateTotals();
        $order->refresh();

        $item = $order->items->first();
        $this->assertSame('Widget', $item->name);              // backfilled from product
        $this->assertSame(25.0, (float) $item->unit_price);     // backfilled from product price
        $this->assertSame(75.0, (float) $item->total);          // 25 * 3

        // subtotal 75 + shipping 10 + fee 5 = 90
        $this->assertSame(75.0, (float) $order->subtotal);
        $this->assertSame(90.0, (float) $order->total);
    }

    public function test_completing_records_history_and_stamps_completed_at(): void
    {
        $order = $this->makeOrder('processing');
        $order->updateStatus('completed');
        $order->refresh();

        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'from_status' => 'processing', 'to_status' => 'completed',
        ]);
    }
}
