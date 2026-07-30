<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\Setting;
use App\Services\Ai\LlmClient;
use App\Services\Ai\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiWriterPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function batch(array $overrides = []): AiImportBatch
    {
        return AiImportBatch::create(array_merge([
            'name' => 'B', 'csv_path' => 'x.csv', 'prompt' => 'Premium tone.',
            'provider' => 'anthropic',
        ], $overrides))->fresh();
    }

    public function test_default_format_asks_for_scoped_pd_classes_and_css(): void
    {
        $system = ProductWriter::systemFor($this->batch());

        $this->assertStringContainsString('pd-hero', $system);
        $this->assertStringContainsString('Premium tone.', $system);
        $this->assertStringContainsString('top 3 competing products', $system);
    }

    public function test_plain_format_forbids_classes_and_css(): void
    {
        $system = ProductWriter::systemFor($this->batch([
            'output_format' => 'html_plain',
            'allowed_tags' => ['h2', 'p', 'table', 'blockquote'],
        ]));

        $this->assertStringContainsString('No class attributes', $system);
        $this->assertStringContainsString('css key must be an empty string', $system);
        $this->assertStringContainsString('h2, p, table, blockquote', $system);
        $this->assertStringNotContainsString('pd-hero', $system);
    }

    public function test_custom_classes_format_injects_the_class_list_once(): void
    {
        $system = ProductWriter::systemFor($this->batch([
            'output_format' => 'html_classes',
            'custom_classes' => "product-intro — opening\nspec-table — specs",
        ]));

        $this->assertStringContainsString('product-intro', $system);
        $this->assertStringContainsString('Do not invent new classes', $system);
    }

    public function test_competitor_count_off_removes_market_section(): void
    {
        $system = ProductWriter::systemFor($this->batch(['competitor_count' => 0]));

        $this->assertStringNotContainsString('competing products', $system);
    }

    public function test_custom_system_prompt_overrides_default(): void
    {
        $system = ProductWriter::systemFor($this->batch(['system_prompt' => 'Write like a pirate.']));

        $this->assertStringContainsString('Write like a pirate.', $system);
        $this->assertStringNotContainsString('senior ecommerce copywriter', $system);
    }

    public function test_global_default_system_prompt_setting_is_used(): void
    {
        Setting::set('ai.default_system_prompt', 'Global house style.');

        $system = ProductWriter::systemFor($this->batch());

        $this->assertStringContainsString('Global house style.', $system);
    }

    public function test_system_prompt_is_identical_across_items_for_caching(): void
    {
        $batch = $this->batch();

        $this->assertSame(ProductWriter::systemFor($batch), ProductWriter::systemFor($batch));
    }

    public function test_semantic_seo_rules_are_present_between_writing_and_search_engine_rules(): void
    {
        $system = ProductWriter::systemFor($this->batch());

        $this->assertStringContainsString('SEMANTIC SEO (mandatory', $system);

        $writingPos = strpos($system, 'WRITING RULES (mandatory)');
        $semanticPos = strpos($system, 'SEMANTIC SEO (mandatory');
        $searchPos = strpos($system, 'SEARCH & AI-ANSWER OPTIMIZATION');

        $this->assertNotFalse($writingPos);
        $this->assertNotFalse($semanticPos);
        $this->assertNotFalse($searchPos);
        $this->assertTrue($writingPos < $semanticPos && $semanticPos < $searchPos);
    }

    public function test_attribute_vocabulary_appears_only_when_attributes_are_seeded(): void
    {
        // "ATTRIBUTE VOCABULARY" alone also appears as a forward-reference
        // inside the always-on SEMANTIC SEO rules, so assert on the actual
        // vocabulary block's heading, which only prints when non-empty.
        $before = ProductWriter::systemFor($this->batch());
        $this->assertStringNotContainsString('ATTRIBUTE VOCABULARY (attribute: allowed values)', $before);
        $this->assertStringNotContainsString('STRUCTURED ATTRIBUTES (also return', $before);

        $attribute = \App\Models\Attribute::create(['name' => 'Cooling Level', 'slug' => 'cooling-level', 'type' => 'select']);
        \App\Models\AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Strong', 'slug' => 'strong']);

        $after = ProductWriter::systemFor($this->batch());
        $this->assertStringContainsString('ATTRIBUTE VOCABULARY (attribute: allowed values)', $after);
        $this->assertStringContainsString('cooling_level: Strong', $after);
        $this->assertStringContainsString('STRUCTURED ATTRIBUTES (also return', $after);
    }

    public function test_anthropic_requests_mark_system_block_cacheable(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => '{}']]])]);

        LlmClient::for('anthropic')->complete('SYSTEM', 'USER', cacheStatic: true);

        Http::assertSent(function ($request): bool {
            $system = $request->data()['system'] ?? null;

            return is_array($system)
                && ($system[0]['cache_control']['type'] ?? null) === 'ephemeral'
                && $system[0]['text'] === 'SYSTEM';
        });
    }
}
