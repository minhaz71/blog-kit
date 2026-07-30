<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBarTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function product(): Product
    {
        return Product::create([
            'name' => 'Terea Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
    }

    public function test_guests_and_customers_never_see_the_admin_bar(): void
    {
        $product = $this->product();

        $this->get($product->url())->assertOk()->assertDontSee('adminbar');

        $customer = User::factory()->create(['is_active' => true]); // no staff role
        $this->actingAs($customer)->get($product->url())->assertOk()->assertDontSee('adminbar');
    }

    public function test_staff_see_context_edit_links_per_page_type(): void
    {
        $staff = $this->staff();
        $product = $this->product();
        $category = Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true]);

        // Product page: edit product link (+ template when one is assigned).
        $this->actingAs($staff)->get($product->url())
            ->assertOk()
            ->assertSee('adminbar')
            ->assertSee('Edit product')
            ->assertSee("/admin/products/{$product->id}/edit");

        // Category page.
        $this->actingAs($staff)->get('/category/terea-uae')
            ->assertOk()
            ->assertSee('Edit category')
            ->assertSee("/admin/categories/{$category->id}/edit");

        // Homepage: jump to the sections editor.
        $this->actingAs($staff)->get('/')
            ->assertOk()
            ->assertSee('Edit homepage sections');
    }
}
