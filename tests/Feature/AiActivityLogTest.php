<?php

namespace Tests\Feature;

use App\Jobs\StartAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBatch(): AiImportBatch
    {
        Storage::disk('local')->put('ai-imports/log.csv', "name,regular_price\nLog Widget,10\nOther Widget,12\n");

        return AiImportBatch::create([
            'name' => 'Log batch', 'csv_path' => 'ai-imports/log.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic', 'require_approval' => false, 'publish_mode' => 'publish',
        ]);
    }

    public function test_pipeline_writes_a_full_activity_trail(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode([
                    'short_description_html' => '<p>x</p>', 'description_html' => '<p>y</p>', 'css' => '',
                    'suggested_price' => 10, 'meta_title' => 't', 'meta_description' => 'd', 'focus_keyword' => 'k',
                    'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
                ])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);

        $batch = $this->makeBatch();
        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $stages = AiActivityLog::where('batch_id', $batch->id)->pluck('stage')->all();

        $this->assertContains('parse', $stages);
        $this->assertContains('write', $stages);
        $this->assertContains('review', $stages);
        $this->assertContains('publish', $stages);

        // Parse log mentions the queue count; publish log names the product.
        $this->assertTrue(AiActivityLog::where('batch_id', $batch->id)->where('message', 'like', '%2 products queued%')->exists());
        $this->assertTrue(AiActivityLog::where('batch_id', $batch->id)->where('message', 'like', '%Log Widget%')->exists());
    }

    public function test_failures_log_a_clear_error_entry(): void
    {
        // No API key configured → item fails with a readable message.
        $batch = $this->makeBatch();
        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $error = AiActivityLog::where('batch_id', $batch->id)->where('level', 'error')->first();

        $this->assertNotNull($error);
        $this->assertStringContainsString('No API key configured', $error->message);
        $this->assertStringContainsString('Log Widget', $error->message);
    }

    public function test_live_monitor_page_renders(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        $batch = $this->makeBatch();
        AiActivityLog::write($batch->id, null, 'parse', 'CSV parsed — 2 products queued.', 'success');

        $this->actingAs($user)
            ->get("/admin/ai-import-batches/{$batch->id}/monitor")
            ->assertStatus(200)
            ->assertSee('Live activity')
            ->assertSee('CSV parsed');
    }
}
