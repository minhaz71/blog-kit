<?php

namespace Tests\Feature;

use App\Filament\Pages\FindReplace;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FindReplacePageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function product(): Product
    {
        return Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 120, 'status' => 'published', 'visibility' => 'visible',
            'short_description' => 'Free delivery over 300 AED.',
            'description' => 'Order over 300 AED.',
        ]);
    }

    public function test_dry_run_then_apply_then_undo_through_the_page(): void
    {
        $this->seed();
        $this->actingAs($this->admin());
        $p = $this->product();

        $component = Livewire::test(FindReplace::class)
            ->set('find', '300 AED')
            ->set('replace', '400 AED')
            ->set('scopes', ['products' => true])
            ->call('dryRun');

        // Dry run reports matches but changes nothing.
        $this->assertSame(2, $component->get('preview')['occurrences']);
        $this->assertStringContainsString('300 AED', $p->refresh()->short_description);

        // Apply.
        $component->call('apply');
        $this->assertStringContainsString('400 AED', $p->refresh()->short_description);

        // Undo the most recent batch.
        $batch = \App\Models\ContentReplaceBatch::latest()->first();
        $component->call('undo', $batch->id);
        $this->assertStringContainsString('300 AED', $p->refresh()->short_description);
    }

    public function test_page_defaults_to_content_and_seo_scopes(): void
    {
        $this->seed();
        $this->actingAs($this->admin());

        $component = Livewire::test(FindReplace::class);
        $scopes = array_keys(array_filter($component->get('scopes')));

        $this->assertContains('products', $scopes);
        $this->assertContains('product_seo', $scopes);
        $this->assertNotContains('brands', $scopes); // secondary off by default
    }
}
