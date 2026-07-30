<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaLibrary;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        // Screens are permission-gated now — the library needs a real role.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_featured_images_get_a_media_record_automatically(): void
    {
        // Observer path: saving a product with a featured image creates the
        // media record exactly once (idempotent on later saves).
        $product = Product::create([
            'name' => 'Terea Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 10, 'status' => 'published',
            'featured_image' => 'products/terea-amber-featured.jpg',
        ]);

        $this->assertSame(1, $product->images()->where('path', 'products/terea-amber-featured.jpg')->count());

        $product->update(['price' => 12]);
        $this->assertSame(1, $product->images()->count(), 'Re-saving must not duplicate the record.');

        // Its metadata is now editable and drives the frontend alt/title.
        $product->images()->first()->update(['alt' => 'TEREA Amber pack front view', 'title' => 'TEREA Amber']);
        $this->assertSame('TEREA Amber pack front view', $product->fresh()->load('images')->featuredImageRecord()->altText());
    }

    public function test_backfill_command_creates_missing_records(): void
    {
        // Bypass observers to simulate legacy data.
        Product::withoutEvents(fn () => Product::create([
            'name' => 'Legacy', 'slug' => 'legacy', 'type' => 'simple',
            'price' => 5, 'status' => 'published',
            'featured_image' => 'products/legacy.jpg',
        ]));

        $this->assertSame(0, ProductImage::count());

        $this->artisan('media:sync-featured')->assertExitCode(0);

        $this->assertSame(1, ProductImage::where('path', 'products/legacy.jpg')->count());
    }

    public function test_media_library_edits_details_but_never_the_permalink(): void
    {
        $product = Product::create([
            'name' => 'Terea Sienna', 'slug' => 'terea-sienna', 'type' => 'simple',
            'price' => 10, 'status' => 'published',
            'featured_image' => 'products/terea-sienna.jpg',
        ]);

        $image = $product->images()->first();

        Livewire::actingAs($this->admin())
            ->test(MediaLibrary::class)
            ->assertSee('Terea Sienna')
            ->call('edit', $image->id)
            ->assertSet('editingId', $image->id)
            ->set('editAlt', 'Sienna pack, woody tobacco sticks')
            ->set('editTitle', 'TEREA Sienna pack')
            ->set('editCaption', '20 sticks per pack for IQOS ILUMA.')
            ->call('save');

        $image->refresh();
        $this->assertSame('Sienna pack, woody tobacco sticks', $image->alt);
        $this->assertSame('TEREA Sienna pack', $image->title);
        $this->assertSame('20 sticks per pack for IQOS ILUMA.', $image->caption);
        // Permalink untouched — the path has no edit route at all.
        $this->assertSame('products/terea-sienna.jpg', $image->path);
    }

    public function test_view_toggle_and_missing_alt_filter(): void
    {
        Product::create([
            'name' => 'Has Alt', 'slug' => 'has-alt', 'type' => 'simple', 'price' => 5,
            'status' => 'published', 'featured_image' => 'products/has-alt.jpg',
        ])->images()->first()->update(['alt' => 'Filled']);

        Product::create([
            'name' => 'No Alt', 'slug' => 'no-alt', 'type' => 'simple', 'price' => 5,
            'status' => 'published', 'featured_image' => 'products/no-alt.jpg',
        ]);

        Livewire::actingAs($this->admin())
            ->test(MediaLibrary::class)
            ->call('setView', 'list')
            ->assertSet('view_mode', 'list')
            ->assertSee('Has Alt')
            ->set('missingOnly', true)
            ->assertSee('No Alt')
            ->assertDontSee('has-alt.jpg');
    }
}
