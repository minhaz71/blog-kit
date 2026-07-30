<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function order(string $status = 'pending'): Order
    {
        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'status' => $status, 'currency' => 'USD',
            'subtotal' => 100, 'total' => 100, 'payment_status' => 'pending',
            'billing_address' => [], 'shipping_address' => [],
            'customer_email' => 'a@b.com',
        ]);
    }

    public function test_bulk_mark_completed_completes_eligible_orders_only(): void
    {
        $this->actingAs($this->admin());

        $pending = $this->order('pending');
        $processing = $this->order('processing');
        $alreadyDone = $this->order('completed');

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('bulkComplete', [$pending, $processing, $alreadyDone]);

        $this->assertSame('completed', $pending->refresh()->status);
        $this->assertSame('completed', $processing->refresh()->status);
        $this->assertSame('completed', $alreadyDone->refresh()->status); // unchanged, still completed
    }

    public function test_bulk_move_to_trash_soft_deletes_orders(): void
    {
        $this->actingAs($this->admin());

        $a = $this->order();
        $b = $this->order();

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('delete', [$a, $b]);

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        // Reports still work off the non-trashed set.
        $this->assertSame(0, Order::count());
        $this->assertSame(2, Order::withTrashed()->count());
    }

    public function test_list_pagination_defaults_to_twenty_and_offers_up_to_999(): void
    {
        $this->actingAs($this->admin());

        $table = Livewire::test(ListOrders::class)->instance()->getTable();

        $this->assertSame(20, $table->getDefaultPaginationPageOption());
        $this->assertContains(999, $table->getPaginationPageOptions());
        $this->assertContains(50, $table->getPaginationPageOptions());
    }
}
