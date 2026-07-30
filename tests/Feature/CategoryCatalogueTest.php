<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function category(string $slug, int $products = 0): Category
    {
        $category = Category::create(['name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'is_active' => true]);

        for ($i = 1; $i <= $products; $i++) {
            $product = Product::create([
                'name' => "{$slug} item {$i}", 'slug' => "{$slug}-item-{$i}", 'type' => 'simple',
                'price' => 10, 'status' => 'published', 'visibility' => 'visible',
            ]);
            $category->products()->attach($product->id);
        }

        return $category;
    }

    public function test_catalogue_shows_selected_categories_in_order_with_counts(): void
    {
        $this->category('terea-uae', 3);
        $this->category('terea-japan', 2);
        $this->category('not-selected', 5);

        HomepageSection::create([
            'type' => 'category_catalogue',
            'title' => 'Browse Popular Categories',
            'sort_order' => 12,
            'is_active' => true,
            'settings' => ['categories' => ['terea-japan', 'terea-uae'], 'columns' => 4, 'rows' => 2, 'show_count' => true],
        ]);

        $html = $this->catalogueSection($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('Browse Popular Categories', $html);

        // Admin's order wins: japan before uae; unselected category absent.
        $this->assertLessThan(strpos($html, 'Terea Uae'), strpos($html, 'Terea Japan'));
        $this->assertStringNotContainsString('Not Selected', $html);

        // Product-count pills render the published-product counts (terea-japan
        // has 2). The pill is the tile's absolute-positioned badge.
        $this->assertMatchesRegularExpression('/shadow-sm">\s*2\s*<\/span>/', $html);
    }

    /**
     * The header nav also lists categories — assert inside the marketplace
     * catalogue section only. It's uniquely identified by its "All products"
     * link; slice from the enclosing <section> to its </section>.
     */
    protected function catalogueSection(string $html): string
    {
        $marker = strpos($html, 'All products');
        $this->assertNotFalse($marker, 'Catalogue section not rendered.');

        $start = strrpos(substr($html, 0, $marker), '<section');
        $end = strpos($html, '</section>', $marker);

        return substr($html, $start, $end - $start);
    }

    public function test_rows_times_columns_caps_the_number_of_cards(): void
    {
        foreach (range(1, 6) as $i) {
            $this->category("cat-{$i}", 1);
        }

        HomepageSection::create([
            'type' => 'category_catalogue',
            'sort_order' => 12,
            'is_active' => true,
            // 2 columns × 2 rows = max 4 cards even with 6 selected.
            'settings' => ['categories' => ['cat-1', 'cat-2', 'cat-3', 'cat-4', 'cat-5', 'cat-6'], 'columns' => 2, 'rows' => 2],
        ]);

        $html = $this->catalogueSection($this->get('/')->assertOk()->getContent());

        // Each tile is an <a class="group w-32 …"> card; 2×2 caps at 4.
        $this->assertSame(4, substr_count($html, 'class="group w-32'));
        $this->assertStringContainsString('Cat 4', $html);
        $this->assertStringNotContainsString('Cat 5', $html);
    }
}
