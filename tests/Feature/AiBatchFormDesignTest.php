<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiBatchFormDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_model_options_are_scoped_to_the_selected_provider(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(\App\Filament\Resources\AiImportBatchResource\Pages\CreateAiImportBatch::class)
            ->fillForm(['provider' => 'anthropic'])
            ->assertFormFieldExists('model')
            ->set('data.provider', 'openai')
            ->assertFormSet(['model' => null]);
    }

    public function test_edit_page_renders_with_saved_provider_and_model(): void
    {
        $batch = AiImportBatch::create([
            'name' => 'Design test', 'csv_path' => 'x.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'model' => 'claude-fable-5',
        ]);

        $this->actingAs($this->admin())
            ->get("/admin/ai-import-batches/{$batch->id}/edit")
            ->assertStatus(200)
            ->assertSee('AI engine')
            ->assertSee('Claude Fable 5', escape: false);
    }

    public function test_ai_settings_model_dropdowns_render(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/ai-settings')
            ->assertStatus(200)
            ->assertSee('Default model');
    }
}
