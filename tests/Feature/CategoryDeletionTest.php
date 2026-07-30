<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a category must never delete products or posts. Orphaned
 * products/posts fall back to the default category ("Uncategorized" unless
 * the admin picked one).
 */
class CategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $name): Product
    {
        return Product::create([
            'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
            'price' => 30, 'status' => 'published',
        ]);
    }

    public function test_deleting_a_category_moves_orphaned_products_to_uncategorized(): void
    {
        $category = Category::create(['name' => 'TEREA Japan', 'slug' => 'terea-japan', 'is_active' => true]);
        $product = $this->product('TEREA Yellow');
        $product->categories()->attach($category->id);

        $category->delete();

        // Product survives and is now in the auto-created default.
        $this->assertNotSoftDeleted($product);
        $product->refresh()->load('categories');
        $default = Category::where('slug', 'uncategorized')->first();
        $this->assertNotNull($default);
        $this->assertSame([$default->id], $product->categories->pluck('id')->all());
    }

    public function test_product_with_another_category_is_not_reassigned(): void
    {
        $a = Category::create(['name' => 'TEREA Japan', 'slug' => 'terea-japan', 'is_active' => true]);
        $b = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $product = $this->product('TEREA Yellow');
        $product->categories()->attach([$a->id, $b->id]);

        $a->delete();

        $product->refresh()->load('categories');
        // Keeps B, loses A, and is NOT dumped into Uncategorized.
        $this->assertSame([$b->id], $product->categories->pluck('id')->all());
        $this->assertNull(Category::where('slug', 'uncategorized')->first());
    }

    public function test_admin_chosen_default_is_used_as_the_reassignment_target(): void
    {
        $fallback = Category::create(['name' => 'Accessories', 'slug' => 'accessories', 'is_active' => true]);
        Setting::set('catalog.default_category_id', $fallback->id);

        $category = Category::create(['name' => 'TEREA Japan', 'slug' => 'terea-japan', 'is_active' => true]);
        $product = $this->product('TEREA Yellow');
        $product->categories()->attach($category->id);

        $category->delete();

        $product->refresh()->load('categories');
        $this->assertSame([$fallback->id], $product->categories->pluck('id')->all());
        // No Uncategorized needed — the configured default was used.
        $this->assertNull(Category::where('slug', 'uncategorized')->first());
    }

    public function test_deleting_the_default_category_itself_does_not_crash_or_lose_products(): void
    {
        $default = Category::create(['name' => 'Heated Tobacco', 'slug' => 'heated-tobacco', 'is_active' => true]);
        Category::create(['name' => 'Accessories', 'slug' => 'accessories', 'is_active' => true]);
        Setting::set('catalog.default_category_id', $default->id);

        $product = $this->product('TEREA Yellow');
        $product->categories()->attach($default->id);

        $default->delete();

        // Product survives; deleting the default falls back to a fresh
        // "Uncategorized" rather than dumping products into an unrelated
        // existing category (e.g. Accessories).
        $this->assertNotSoftDeleted($product);
        $product->refresh()->load('categories');
        $fallback = Category::where('slug', 'uncategorized')->first();
        $this->assertNotNull($fallback);
        $this->assertSame([$fallback->id], $product->categories->pluck('id')->all());
    }

    public function test_deleting_a_blog_category_moves_posts_to_default_not_delete_them(): void
    {
        $author = User::factory()->create();
        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        $post = Post::create([
            'title' => 'A Guide', 'slug' => 'a-guide', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => now()->subHour(),
            'author_id' => $author->id, 'post_category_id' => $category->id,
        ]);

        $category->delete();

        // Post survives and is moved to the auto-created default blog category.
        $this->assertNotSoftDeleted($post);
        $default = PostCategory::where('slug', 'uncategorized')->first();
        $this->assertNotNull($default);
        $this->assertSame($default->id, $post->refresh()->post_category_id);
    }

    public function test_blog_default_setting_is_respected(): void
    {
        $author = User::factory()->create();
        $fallback = PostCategory::create(['name' => 'News', 'slug' => 'news']);
        Setting::set('blog.default_post_category_id', $fallback->id);

        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        $post = Post::create([
            'title' => 'A Guide', 'slug' => 'a-guide', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => now()->subHour(),
            'author_id' => $author->id, 'post_category_id' => $category->id,
        ]);

        $category->delete();

        $this->assertSame($fallback->id, $post->refresh()->post_category_id);
        $this->assertNull(PostCategory::where('slug', 'uncategorized')->first());
    }
}
