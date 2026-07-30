<?php

namespace Tests\Feature;

use App\Jobs\StartAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiImportBatch;
use App\Models\Setting;
use App\Services\Ai\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiBatchControlsTest extends TestCase
{
    use RefreshDatabase;

    /** Parse the CSV but keep item jobs queued (not executed inline). */
    protected function makeBatch(): AiImportBatch
    {
        Queue::fake([WriteAiProduct::class]);

        Storage::disk('local')->put('ai-imports/ctl.csv', "name,regular_price\nCtl One,10\nCtl Two,12\n");

        $batch = AiImportBatch::create([
            'name' => 'Ctl', 'csv_path' => 'ai-imports/ctl.csv', 'prompt' => 'p', 'provider' => 'gemini',
        ]);

        (new StartAiImportBatch($batch))->handle();

        return $batch->fresh();
    }

    public function test_provider_error_body_is_surfaced_with_hint(): void
    {
        Setting::set('ai.gemini_api_key', 'bad-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT'],
            ], 400),
        ]);

        $batch = $this->makeBatch();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $item = $batch->items()->first();
        $this->assertSame('failed', $item->status);
        $this->assertStringContainsString('Gemini API error (HTTP 400)', $item->error);
        $this->assertStringContainsString('API key not valid', $item->error);
    }

    public function test_401_gets_invalid_key_hint(): void
    {
        Setting::set('ai.openai_api_key', 'k');
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);

        try {
            LlmClient::for('openai')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Incorrect API key provided', $e->getMessage());
            $this->assertStringContainsString('invalid or revoked', $e->getMessage());
        }
    }

    public function test_404_gets_model_name_hint(): void
    {
        Setting::set('ai.openai_api_key', 'k');
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'The model `gpt-99` does not exist']], 404)]);

        try {
            LlmClient::for('openai', 'gpt-99')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('gpt-99', $e->getMessage());
            $this->assertStringContainsString('Check the model name', $e->getMessage());
        }
    }

    public function test_paused_batch_skips_processing_and_item_stays_pending(): void
    {
        Setting::set('ai.gemini_api_key', 'k');
        $batch = $this->makeBatch();
        $batch->update(['status' => 'paused']);

        Http::fake();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $this->assertSame('pending', $batch->items()->first()->status);
        Http::assertNothingSent();
    }

    public function test_stopped_batch_also_skips(): void
    {
        Setting::set('ai.gemini_api_key', 'k');
        $batch = $this->makeBatch();
        $batch->update(['status' => 'stopped']);

        Http::fake();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $this->assertSame('pending', $batch->items()->first()->status);
        Http::assertNothingSent();
    }

    public function test_resume_requeues_pending_items(): void
    {
        $batch = $this->makeBatch();
        $batch->update(['status' => 'paused']);

        Queue::fake([WriteAiProduct::class]);

        // Simulate the monitor's Resume action.
        $batch->update(['status' => 'processing', 'error' => null]);
        foreach ($batch->items()->whereIn('status', ['pending', 'failed'])->pluck('id') as $itemId) {
            WriteAiProduct::dispatch($itemId);
        }

        Queue::assertPushed(WriteAiProduct::class, 2);
    }

    public function test_health_check_reports_ok(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'OK']],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 1],
        ])]);

        [$ok, $message] = LlmClient::for('anthropic')->healthCheck();

        $this->assertTrue($ok);
        $this->assertStringContainsString('responded in', $message);
    }

    public function test_health_check_reports_failure_with_provider_message(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);

        [$ok, $message] = LlmClient::for('anthropic')->healthCheck();

        $this->assertFalse($ok);
        $this->assertStringContainsString('overloaded', $message);
    }
}
