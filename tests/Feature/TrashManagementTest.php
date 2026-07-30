<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(string $slug = 'widget'): Product
    {
        return Product::create(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'simple', 'price' => 50, 'status' => 'published', 'stock_status' => 'in_stock']);
    }

    protected function makeOrder(): Order
    {
        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'status' => 'pending', 'currency' => 'USD',
            'subtotal' => 100, 'total' => 100,
            'payment_status' => 'pending',
            'billing_address' => [], 'shipping_address' => [],
            'customer_email' => 'a@b.com',
        ]);
    }

    public function test_deleting_moves_to_trash_and_hides_from_storefront(): void
    {
        $product = $this->makeProduct();
        $product->delete();

        // Still in the database, flagged as trashed — the trash box.
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertNotNull(Product::withTrashed()->find($product->id));

        // Invisible to the storefront and to normal queries.
        $this->assertNull(Product::find($product->id));
        $this->get('/product/widget')->assertNotFound();

        // Restore brings it straight back.
        $product->restore();
        $this->assertNotNull(Product::find($product->id));
    }

    public function test_trashed_orders_leave_reports_and_can_be_restored(): void
    {
        $kept = $this->makeOrder();
        $trashed = $this->makeOrder();
        $trashed->delete();

        $this->assertSame(1, Order::count());               // reports see only the kept order
        $this->assertSame(2, Order::withTrashed()->count()); // trash still holds the other

        $trashed->restore();
        $this->assertSame(2, Order::count());
    }

    public function test_purge_command_deletes_only_items_older_than_retention(): void
    {
        $old = $this->makeProduct('old-widget');
        $recent = $this->makeProduct('recent-widget');
        $oldOrder = $this->makeOrder();
        $oldPost = Post::create(['author_id' => User::factory()->create()->id, 'title' => 'Old post', 'slug' => 'old-post', 'status' => 'draft']);
        $oldPage = \App\Models\Page::create(['title' => 'Old page', 'slug' => 'old-page', 'status' => 'draft']);

        foreach ([$old, $oldOrder, $oldPost, $oldPage] as $model) {
            $model->delete();
            $model->forceFill(['deleted_at' => now()->subDays(100)])->saveQuietly();
        }
        $recent->delete(); // trashed today — must survive the purge

        // Dry run deletes nothing.
        $this->artisan('trash:purge', ['--days' => 90, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(2, Product::onlyTrashed()->count());

        $this->artisan('trash:purge', ['--days' => 90])->assertSuccessful();

        $this->assertNull(Product::withTrashed()->find($old->id));       // gone for good
        $this->assertNull(Order::withTrashed()->find($oldOrder->id));
        $this->assertNull(Post::withTrashed()->find($oldPost->id));
        $this->assertNull(\App\Models\Page::withTrashed()->find($oldPage->id)); // pages now purged too
        $this->assertNotNull(Product::onlyTrashed()->find($recent->id)); // still in trash
    }

    public function test_admin_can_force_delete_instantly(): void
    {
        $product = $this->makeProduct();
        $product->delete();
        $product->forceDelete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
