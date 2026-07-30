<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryProductCountTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function categoryWith(string $name, int $count): Category
    {
        $category = Category::create(['name' => $name, 'slug' => str($name)->slug(), 'is_active' => true]);

        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'name' => "{$name} {$i}", 'slug' => str("{$name} {$i}")->slug(),
                'type' => 'simple', 'price' => 10, 'status' => 'published',
            ])->categories()->attach($category->id);
        }

        return $category;
    }

    public function test_category_list_shows_product_count_column(): void
    {
        $this->actingAs($this->admin());
        $indo = $this->categoryWith('Terea Indonesian', 16);
        $this->categoryWith('Terea Switzerland', 3);

        Livewire::test(ListCategories::class)
            ->assertCanRenderTableColumn('products_count')
            ->assertCanSeeTableRecords([$indo]);

        // withCount populates the accessor used by the column + the filter.
        $this->assertSame(16, Category::withCount('products')->find($indo->id)->products_count);
    }

    public function test_product_category_filter_labels_include_the_count(): void
    {
        $this->actingAs($this->admin());
        $indo = $this->categoryWith('Terea Indonesian', 16);

        // Preloaded option labels render "Name (count)" into the page.
        Livewire::test(ListProducts::class)
            ->assertSee('Terea Indonesian (16)');
    }

    public function test_filtering_products_by_category_returns_only_its_products(): void
    {
        $this->actingAs($this->admin());
        $indo = $this->categoryWith('Terea Indonesian', 4);
        $swiss = $this->categoryWith('Terea Switzerland', 2);

        Livewire::test(ListProducts::class)
            ->filterTable('categories', $indo->id)
            ->assertCanSeeTableRecords($indo->products)
            ->assertCanNotSeeTableRecords($swiss->products);
    }
}
