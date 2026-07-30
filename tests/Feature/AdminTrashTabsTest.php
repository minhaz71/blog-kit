<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * WordPress-style status tabs: trashed products live only under the Trash
 * tab, never mixed into All/Published/Draft.
 */
class AdminTrashTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function product(string $name, string $status = 'published'): Product
    {
        return Product::create([
            'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
            'price' => 10, 'status' => $status,
        ]);
    }

    public function test_trashed_products_appear_only_in_the_trash_tab(): void
    {
        $this->actingAs($this->admin());

        $live = $this->product('Live Widget');
        $trashed = $this->product('Dead Widget');
        $trashed->delete();

        // Default (All) tab: live only, trashed hidden.
        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$trashed]);

        // Trash tab: trashed only, live hidden.
        Livewire::test(ListProducts::class)
            ->set('activeTab', 'trash')
            ->assertCanSeeTableRecords([$trashed])
            ->assertCanNotSeeTableRecords([$live]);
    }

    public function test_draft_tab_excludes_published_and_trashed(): void
    {
        $this->actingAs($this->admin());

        $draft = $this->product('Draft Widget', 'draft');
        $published = $this->product('Published Widget', 'published');
        $trashedDraft = $this->product('Trashed Draft', 'draft');
        $trashedDraft->delete();

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$published, $trashedDraft]);
    }
}
