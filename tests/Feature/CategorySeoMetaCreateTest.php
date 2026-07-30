<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Models\Category;
use App\Models\SeoMeta;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression: creating a category through the Filament form used to throw a
 * duplicate-key error on seo_meta — the saved-observer created one row and
 * the SeoForm relationship inserted a second. Now the parent observer only
 * updates an existing row and the SeoMeta observer computes the score, so
 * exactly one row is created and scored.
 */
class CategorySeoMetaCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_creating_a_category_via_filament_makes_exactly_one_scored_seo_meta(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Terea Indonesian',
                'slug' => 'terea-indonesian',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('slug', 'terea-indonesian')->firstOrFail();

        // Exactly one seo_meta row — no duplicate-key crash.
        $this->assertSame(1, SeoMeta::where('metable_type', Category::class)
            ->where('metable_id', $category->id)->count());

        // And the SeoMeta observer computed a score for it.
        $this->assertNotNull($category->seoMeta);
        $this->assertNotNull($category->seoMeta->seo_analysis);
        $this->assertIsInt($category->seoMeta->seo_score);
    }

    public function test_editing_category_content_updates_the_single_seo_meta_row(): void
    {
        $this->actingAs($this->admin());
        $category = Category::create(['name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true]);

        // Simulate a re-save (as the edit form does) — must not create a 2nd row.
        $category->update(['description' => 'Genuine TEREA cartons delivered across the UAE.']);
        $category->seoMeta()->updateOrCreate([], ['focus_keyword' => 'terea uae']);

        $this->assertSame(1, SeoMeta::where('metable_type', Category::class)
            ->where('metable_id', $category->id)->count());
    }

    public function test_getOrCreateSeoMeta_is_idempotent(): void
    {
        $category = Category::create(['name' => 'Terea Japan', 'slug' => 'terea-japan', 'is_active' => true]);

        $a = $category->getOrCreateSeoMeta();
        $b = $category->fresh()->getOrCreateSeoMeta();

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, SeoMeta::where('metable_type', Category::class)
            ->where('metable_id', $category->id)->count());
    }
}
