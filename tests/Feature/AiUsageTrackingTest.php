<?php

namespace Tests\Feature;

use App\Jobs\StartAiImportBatch;
use App\Models\AiImportBatch;
use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Services\Ai\LlmClient;
use App\Services\Ai\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_anthropic_usage_is_recorded_with_cache_aware_cost(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => '{}']],
            'usage' => [
                'input_tokens' => 100,
                'cache_read_input_tokens' => 2000,
                'cache_creation_input_tokens' => 0,
                'output_tokens' => 500,
            ],
        ])]);

        LlmClient::for('anthropic')->withContext('write', null, null)->complete('S', 'U', cacheStatic: true);

        $log = AiUsageLog::first();
        $this->assertSame(2100, $log->input_tokens);
        $this->assertSame(2000, $log->cached_tokens);
        $this->assertSame(500, $log->output_tokens);

        // claude-sonnet-5: cache-aware split at whatever pricing is current
        // (introductory $2/$10/$0.20 through 2026-08-31, then $3/$15/$0.30).
        [$in, $out, $cached] = AiUsageLog::priceFor('claude-sonnet-5');
        $expected = round((100 * $in + 2000 * $cached + 500 * $out) / 1_000_000, 6);
        $this->assertSame($expected, (float) $log->cost);
        $this->assertSame('write', $log->purpose);
    }

    public function test_sonnet_5_pricing_switches_after_introductory_period(): void
    {
        $this->travelTo('2026-08-15');
        $this->assertSame([2.00, 10.00, 0.20], AiUsageLog::priceFor('claude-sonnet-5'));

        $this->travelTo('2026-09-01');
        $this->assertSame([3.00, 15.00, 0.30], AiUsageLog::priceFor('claude-sonnet-5'));

        $this->travelBack();
    }

    public function test_openai_and_gemini_usage_recorded(): void
    {
        Setting::set('ai.openai_api_key', 'k');
        Setting::set('ai.gemini_api_key', 'k');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{}']]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20, 'prompt_tokens_details' => ['cached_tokens' => 10]],
            ]),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{}']]]]],
                'usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 15],
            ]),
        ]);

        LlmClient::for('openai')->complete('S', 'U');
        LlmClient::for('gemini')->complete('S', 'U');

        $this->assertSame(2, AiUsageLog::count());
        $this->assertSame(10, AiUsageLog::where('provider', 'openai')->first()->cached_tokens);
        $this->assertSame(30, AiUsageLog::where('provider', 'gemini')->first()->input_tokens);
    }

    public function test_targeting_fields_enter_the_cached_system_prompt(): void
    {
        $batch = AiImportBatch::create([
            'name' => 'T', 'csv_path' => 'x.csv', 'prompt' => 'p', 'provider' => 'anthropic',
            'target_country' => 'United Arab Emirates',
            'target_city' => 'Dubai',
            'target_language' => 'English (UK)',
            'audience_note' => 'Value-conscious mobile shoppers',
        ])->fresh();

        $system = ProductWriter::systemFor($batch);

        $this->assertStringContainsString('LOCAL TARGETING', $system);
        $this->assertStringContainsString('Dubai', $system);
        $this->assertStringContainsString('United Arab Emirates', $system);
        $this->assertStringContainsString('Value-conscious mobile shoppers', $system);
    }

    public function test_batch_with_fewer_than_two_products_fails(): void
    {
        Storage::disk('local')->put('ai-imports/one.csv', "name,regular_price\nOnly One,5\n");

        $batch = AiImportBatch::create([
            'name' => 'One', 'csv_path' => 'ai-imports/one.csv', 'prompt' => 'p', 'provider' => 'anthropic',
        ]);

        (new StartAiImportBatch($batch))->handle();

        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertStringContainsString('At least 2 products', $batch->error);
        $this->assertSame(0, $batch->items()->count());
    }
}
