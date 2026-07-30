<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Ai\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeApiComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('ai.anthropic_api_key', 'k');
    }

    public function test_refusal_stop_reason_surfaces_category_and_explanation(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [],
            'stop_reason' => 'refusal',
            'stop_details' => ['type' => 'refusal', 'category' => 'cyber', 'explanation' => 'Cannot help with this.'],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 1],
        ], 200, ['request-id' => 'req_test123'])]);

        try {
            LlmClient::for('anthropic')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Claude refused this request (category: cyber)', $e->getMessage());
            $this->assertStringContainsString('Cannot help with this.', $e->getMessage());
            $this->assertStringContainsString('req_test123', $e->getMessage());
        }
    }

    public function test_max_tokens_truncation_is_explained(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"partial":']],
            'stop_reason' => 'max_tokens',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 4096],
        ])]);

        try {
            LlmClient::for('anthropic')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('max_tokens', $e->getMessage());
            $this->assertStringContainsString('incomplete', $e->getMessage());
        }
    }

    public function test_text_is_collected_across_blocks_skipping_thinking(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'thinking', 'thinking' => 'internal reasoning', 'signature' => 'sig'],
                ['type' => 'text', 'text' => '{"ok":'],
                ['type' => 'text', 'text' => 'true}'],
            ],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ])]);

        $reply = LlmClient::for('anthropic')->complete('s', 'u');

        $this->assertSame('{"ok":true}', $reply);
    }

    public function test_pause_turn_asks_for_retry(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'partial']],
            'stop_reason' => 'pause_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ])]);

        try {
            LlmClient::for('anthropic')->complete('s', 'u');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pause_turn', $e->getMessage());
        }
    }

    public function test_default_anthropic_model_is_claude_5_generation(): void
    {
        $this->assertSame('claude-sonnet-5', LlmClient::defaultModel('anthropic'));
        // Sonnet 5 introductory pricing runs through 2026-08-31.
        $this->assertSame([2.00, 10.00, 0.20], \App\Models\AiUsageLog::priceFor('claude-sonnet-5'));
        $this->assertSame([10.00, 50.00, 1.00], \App\Models\AiUsageLog::priceFor('claude-fable-5'));
    }
}
