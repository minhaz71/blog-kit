<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Ai\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_safety_block_gets_a_clear_explanation(): void
    {
        Setting::set('ai.gemini_api_key', 'k');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'promptFeedback' => ['blockReason' => 'PROHIBITED_CONTENT'],
            'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 0],
        ])]);

        try {
            LlmClient::for('gemini')->complete('s', 'write about tobacco sticks');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('blockReason: PROHIBITED_CONTENT', $e->getMessage());
            $this->assertStringContainsString('use Claude (Anthropic) or GPT (OpenAI)', $e->getMessage());
        }
    }

    public function test_gemini_safety_finish_reason_explained(): void
    {
        Setting::set('ai.gemini_api_key', 'k');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'SAFETY', 'content' => ['parts' => []]]],
            'usageMetadata' => [],
        ])]);

        try {
            LlmClient::for('gemini')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('finishReason: SAFETY', $e->getMessage());
        }
    }

    public function test_gemini_max_tokens_cutoff_explained(): void
    {
        Setting::set('ai.gemini_api_key', 'k');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => []]]],
            'usageMetadata' => [],
        ])]);

        try {
            LlmClient::for('gemini')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('MAX_TOKENS', $e->getMessage());
        }
    }

    public function test_transient_503_retries_then_succeeds(): void
    {
        Setting::set('ai.gemini_api_key', 'k');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'high demand']], 503)
                ->push(['error' => ['message' => 'high demand']], 503)
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => '{"ok":1}']]]]],
                    'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
                ]),
        ]);

        $reply = LlmClient::for('gemini')->complete('s', 'u');

        $this->assertSame('{"ok":1}', $reply);
        Http::assertSentCount(3);
    }

    public function test_persistent_503_fails_with_outage_hint_after_retries(): void
    {
        Setting::set('ai.gemini_api_key', 'k');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'high demand']], 503)]);

        try {
            LlmClient::for('gemini')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('high demand', $e->getMessage());
            $this->assertStringContainsString('provider outage', $e->getMessage());
        }

        Http::assertSentCount(3); // 1 original + 2 retries
    }

    public function test_diagnose_command_runs_and_saves_report(): void
    {
        $this->artisan('ai:diagnose')
            ->expectsOutputToContain('API keys')
            ->expectsOutputToContain('Queue')
            ->assertExitCode(0);
    }
}
