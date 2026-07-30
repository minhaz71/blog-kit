<?php

namespace Tests\Feature;

use App\Filament\Pages\ImageSeoTools;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImageSeoToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        // Screens are permission-gated now — the tools need a real role.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function imageFor(string $slug, ?string $alt, ?string $title = null): ProductImage
    {
        $product = Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'type' => 'simple', 'price' => 10, 'status' => 'published',
        ]);

        return ProductImage::create(['product_id' => $product->id, 'path' => "products/{$slug}.jpg", 'alt' => $alt, 'title' => $title]);
    }

    public function test_find_and_replace_updates_matching_fields_only(): void
    {
        $a = $this->imageFor('terea-amber', 'IMG_2026 photo', 'IMG_2026');
        $b = $this->imageFor('terea-sienna', 'Sienna pack front');

        Livewire::actingAs($this->admin())
            ->test(ImageSeoTools::class)
            ->set('findText', 'IMG_2026')
            ->set('replaceText', 'IQOS TEREA Amber Flavor UAE')
            ->set('fields', ['alt', 'title'])
            ->call('preview')
            ->assertSet('previewCount', 1)
            ->call('apply');

        $this->assertSame('IQOS TEREA Amber Flavor UAE photo', $a->fresh()->alt);
        $this->assertSame('IQOS TEREA Amber Flavor UAE', $a->fresh()->title);
        $this->assertSame('Sienna pack front', $b->fresh()->alt);
    }

    public function test_auto_fill_fills_only_empty_alt_and_title(): void
    {
        $empty = $this->imageFor('terea-yellow', null);
        $kept = $this->imageFor('terea-bronze', 'Custom alt text');

        Livewire::actingAs($this->admin())
            ->test(ImageSeoTools::class)
            ->call('autoFill');

        $this->assertSame('Terea Yellow', $empty->fresh()->alt);
        $this->assertSame('Terea Yellow', $empty->fresh()->title);
        $this->assertSame('Custom alt text', $kept->fresh()->alt);
    }
}
