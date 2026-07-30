<?php

namespace Tests\Feature;

use App\Jobs\WriteAiProduct;
use App\Models\AiFixPrompt;
use App\Models\AiImportBatch;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiAgentReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function batch(array $overrides = []): AiImportBatch
    {
        Setting::set('ai.anthropic_api_key', 'claude-key');
        Setting::set('ai.openai_api_key', 'gpt-key');

        Storage::disk('local')->put('ai-imports/ma.csv', "name,regular_price\nTEREA Amber,32\nTEREA Sienna,32\n");

        $batch = AiImportBatch::create(array_merge([
            'name' => 'MA', 'csv_path' => 'ai-imports/ma.csv', 'prompt' => 'Premium Dubai shop.',
            'provider' => 'anthropic', 'reviewer_provider' => 'openai',
            'review_passes' => 3, 'publish_mode' => 'publish', 'require_approval' => true,
            'status' => 'processing', 'total_items' => 2,
        ], $overrides));

        $batch->items()->create(['row' => ['name' => 'TEREA Amber', 'regular_price' => '32'], 'reserved_slug' => 'terea-amber']);
        $batch->items()->create(['row' => ['name' => 'TEREA Sienna', 'regular_price' => '32'], 'reserved_slug' => 'terea-sienna']);

        return $batch->fresh();
    }

    /** A complete valid writer JSON payload. */
    protected function writerJson(array $overrides = []): string
    {
        return json_encode($overrides + [
            // Contract shape: <p> hook + key-fact bullet list (lint-enforced).
            'short_description_html' => '<p>TEREA Amber, rich roasted tobacco sticks for IQOS ILUMA.</p><ul>'
                .'<li><strong>Flavor:</strong> Rich roasted tobacco</li>'
                .'<li><strong>Pack:</strong> 20 sticks per pack</li>'
                .'<li><strong>Compatibility:</strong> IQOS ILUMA series only</li></ul>',
            'description_html' => '<h2>How TEREA Amber tastes</h2><p>Warm, earthy tobacco on the inhale.</p>',
            'css' => '.pd-hero{padding:16px}',
            'suggested_price' => 32,
            'meta_title' => 'TEREA Amber Rich Tobacco | Dubai',
            'meta_description' => 'Rich roasted tobacco TEREA sticks for IQOS ILUMA. Same-day Dubai delivery.',
            'focus_keyword' => 'terea amber',
            'image_alt' => 'TEREA Amber pack', 'image_title' => 'TEREA Amber', 'image_caption' => 'TEREA Amber',
            'faqs' => [
                ['question' => 'Compatible with ILUMA?', 'answer' => 'TEREA Amber works with all IQOS ILUMA devices.'],
                ['question' => 'How strong is TEREA Amber?', 'answer' => 'TEREA Amber is regular strength.'],
                ['question' => 'Pack size?', 'answer' => 'TEREA Amber comes 20 sticks per pack.'],
                ['question' => 'Delivery in Dubai?', 'answer' => 'TEREA Amber ships same-day in Dubai.'],
                ['question' => 'Similar flavors?', 'answer' => 'TEREA Sienna is the closest to TEREA Amber.'],
            ],
        ]);
    }

    protected function fakeClaudeWrites(array $overrides = []): array
    {
        return ['content' => [['type' => 'text', 'text' => $this->writerJson($overrides)]], 'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 500, 'output_tokens' => 400]];
    }

    protected function fakeGptCritique(bool $approved, array $issues = []): array
    {
        return ['choices' => [['message' => ['content' => json_encode([
            'approved' => $approved, 'issues' => $issues, 'summary' => $approved ? 'Publish-ready.' : 'Needs fixes.',
        ])]]], 'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 40]];
    }

    public function test_claude_writes_gpt_approves_first_pass_and_publishes(): void
    {
        $batch = $this->batch();
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeClaudeWrites()),
            'api.openai.com/*' => Http::response($this->fakeGptCritique(true)),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $item = $batch->items()->orderBy('id')->first()->fresh();
        $this->assertSame('published', $item->status);
        $this->assertNotNull($item->product);
        $this->assertNotNull($item->preview_url);
        $this->assertSame(0, $item->open_issues);
    }

    public function test_gpt_flags_issues_then_claude_fixes_then_publishes(): void
    {
        $batch = $this->batch();

        Http::fake([
            // Claude: write (v1), then rewrite (v2).
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->fakeClaudeWrites())
                ->push($this->fakeClaudeWrites()),
            // GPT: reject once with issues, then approve.
            'api.openai.com/*' => Http::sequence()
                ->push($this->fakeGptCritique(false, ['Shorten meta_title to under 60 chars', 'Add not-compatible-with line']))
                ->push($this->fakeGptCritique(true)),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $item = $batch->items()->orderBy('id')->first()->fresh();
        $this->assertSame('published', $item->status);
        $this->assertGreaterThanOrEqual(2, $item->passes_done);

        // The fixing instructions were saved for reuse.
        $saved = AiFixPrompt::where('item_id', $item->id)->where('scope', 'item')->first();
        $this->assertNotNull($saved);
        $this->assertStringContainsString('meta_title', $saved->instructions);
    }

    public function test_copy_failing_hard_rules_is_held_and_not_published(): void
    {
        $batch = $this->batch(['review_passes' => 2]);

        // Claude ALWAYS returns copy that violates a hard rule no machine
        // can auto-fix (only 2 FAQs instead of ≥5), so neither the review
        // passes nor the final rewrite can save it — held, never published.
        $tooFewFaqs = [
            ['question' => 'Compatible with ILUMA?', 'answer' => 'TEREA Amber works with all IQOS ILUMA devices.'],
            ['question' => 'Pack size?', 'answer' => 'TEREA Amber comes 20 sticks per pack.'],
        ];

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeClaudeWrites(['faqs' => $tooFewFaqs])),
            'api.openai.com/*' => Http::response($this->fakeGptCritique(false, ['Add more FAQs'])),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $item = $batch->items()->orderBy('id')->first()->fresh();
        $this->assertSame('needs_review', $item->status);
        $this->assertNull($item->product_id, 'Must NOT publish while hard rules fail.');
        $this->assertGreaterThan(0, $item->open_issues);
    }

    public function test_single_review_pass_critiques_once_then_gates_and_publishes(): void
    {
        $batch = $this->batch(['review_passes' => 1]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeClaudeWrites()),
            'api.openai.com/*' => Http::response($this->fakeGptCritique(false, ['Tighten the intro'])),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $item = $batch->items()->orderBy('id')->first()->fresh();
        $this->assertSame('published', $item->status);
        $this->assertSame(1, $item->passes_done);

        // Exactly one reviewer call — the cheap path.
        $reviewerCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.openai.com'))
            ->count();
        $this->assertSame(1, $reviewerCalls);
    }

    public function test_reviewer_nitpicks_converge_via_deterministic_gate_and_publish(): void
    {
        $batch = $this->batch(['review_passes' => 2]);

        Http::fake([
            // Claude always returns lint-clean copy (write, rewrites, final rewrite).
            'api.anthropic.com/*' => Http::response($this->fakeClaudeWrites()),
            // The critic NEVER approves — endless style nitpicks. The
            // deterministic gate must publish anyway once hard rules pass.
            'api.openai.com/*' => Http::response($this->fakeGptCritique(false, ['Consider a warmer tone in the intro'])),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $item = $batch->items()->orderBy('id')->first()->fresh();
        $this->assertSame('published', $item->status);
        $this->assertNotNull($item->product_id);
        $this->assertSame(0, $item->open_issues);
    }

    public function test_same_provider_and_model_uses_combined_single_call_mode(): void
    {
        // Writer AND reviewer = anthropic/claude-sonnet-5 → review+fix is ONE call.
        $batch = $this->batch([
            'reviewer_provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'reviewer_model' => 'claude-sonnet-5',
        ]);

        $this->assertTrue($batch->usesCombinedReview());

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->fakeClaudeWrites())                      // write
                ->push(['content' => [['type' => 'text', 'text' => json_encode([ // combined review: approved
                    'approved' => true, 'issues' => [], 'summary' => 'Publish-ready.',
                ])]], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 100, 'output_tokens' => 30]]),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $this->assertSame('published', $batch->items()->orderBy('id')->first()->fresh()->status);
        $apiCalls = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.anthropic.com'))->count();
        $this->assertSame(2, $apiCalls); // write + ONE combined review — no separate rewrite call
    }

    public function test_combined_mode_fixes_issues_in_the_same_call(): void
    {
        $batch = $this->batch([
            'reviewer_provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'reviewer_model' => 'claude-sonnet-5',
        ]);

        $correctedJson = json_decode($this->writerJson(), true);
        $correctedJson['meta_title'] = 'TEREA Amber: Fixed Title';

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->fakeClaudeWrites())
                // Pass 1: issues + corrected copy in ONE response.
                ->push(['content' => [['type' => 'text', 'text' => json_encode([
                    'approved' => false, 'issues' => ['Shorten meta_title'], 'summary' => 'One fix.',
                    'corrected' => $correctedJson,
                ])]], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 100, 'output_tokens' => 200]])
                // Pass 2: approved.
                ->push(['content' => [['type' => 'text', 'text' => '{"approved":true,"issues":[],"summary":"ok"}']], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 50, 'output_tokens' => 10]]),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $item = $batch->items()->orderBy('id')->first()->fresh();
        $this->assertSame('published', $item->status);
        $this->assertSame('TEREA Amber: Fixed Title', $item->product->seoMeta->title);
        $this->assertNotNull(AiFixPrompt::where('item_id', $item->id)->first());
        $apiCalls = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.anthropic.com'))->count();
        $this->assertSame(3, $apiCalls); // write + 2 combined passes; zero separate rewrites
    }

    public function test_model_options_merge_admin_extra_models(): void
    {
        // Admin pastes new model ids in AI settings — no network calls.
        Setting::set('ai.anthropic_extra_models', "claude-brand-new-6 | Claude Brand New 6\nclaude-bare-id");

        $options = AiImportBatch::modelOptions('anthropic');

        $this->assertStringContainsString('recommended', $options['claude-sonnet-5']); // curated kept
        $this->assertSame('Claude Brand New 6', $options['claude-brand-new-6']);       // labeled extra
        $this->assertSame('claude-bare-id', $options['claude-bare-id']);               // bare id extra
        Http::assertNothingSent();
    }

    public function test_smart_csv_parsing_aliases_delimiters_and_dedupe(): void
    {
        // Semicolon-delimited, aliased headers, a duplicate, an empty row, messy price.
        Storage::disk('local')->put('ai-imports/smart.csv', implode("\n", [
            "\xEF\xBB\xBFTitle;Price;Offer Price;Desc;Image",
            'TEREA Amber;AED 1,299.00;28;Nice sticks;https://example.com/a.jpg',
            ';;;;',
            'TEREA Amber;32;28;Duplicate row;',
            'TEREA Sienna;32;27;Other;',
        ]));

        $batch = AiImportBatch::create([
            'name' => 'Smart', 'csv_path' => 'ai-imports/smart.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic',
        ]);

        (new \App\Jobs\StartAiImportBatch($batch))->handle();

        $this->assertSame(2, $batch->fresh()->total_items); // dupe + empty skipped

        $row = $batch->items()->orderBy('id')->first()->row;
        $this->assertSame('TEREA Amber', $row['name']);            // title → name
        $this->assertSame('1299.00', $row['regular_price']);       // "AED 1,299.00" cleaned
        $this->assertSame('28', $row['sale_price']);               // offer price → sale_price
        $this->assertSame('https://example.com/a.jpg', $row['image_link']); // image → image_link
    }

    public function test_require_approval_off_publishes_best_effort(): void
    {
        $batch = $this->batch(['review_passes' => 2, 'require_approval' => false]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeClaudeWrites()),
            'api.openai.com/*' => Http::response($this->fakeGptCritique(false, ['minor nit'])),
        ]);

        (new WriteAiProduct($batch->items()->orderBy('id')->first()->id))->handle();

        $this->assertSame('published', $batch->items()->orderBy('id')->first()->fresh()->status);
    }
}
