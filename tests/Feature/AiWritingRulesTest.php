<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Services\Ai\ContentReviewer;
use App\Services\Ai\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiWritingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function batch(): AiImportBatch
    {
        return AiImportBatch::create([
            'name' => 'R', 'csv_path' => 'x.csv', 'prompt' => 'p', 'provider' => 'anthropic',
        ])->fresh();
    }

    public function test_writer_system_prompt_contains_the_skill_rulebook(): void
    {
        $system = ProductWriter::systemFor($this->batch());

        // Structure rules
        $this->assertStringContainsString('WRITING RULES', $system);
        $this->assertStringContainsString('machines-first pattern', $system);
        $this->assertStringContainsString('Feature → Advantage → Benefit', $system);
        $this->assertStringContainsString('inhale feel', $system);
        $this->assertStringContainsString('one idea per bullet', $system);
        // Banned phrases present as instructions
        $this->assertStringContainsString('Elevate your experience', $system);
        $this->assertStringContainsString('We are proud to offer', $system);
        // EEAT + FAQ contract
        $this->assertStringContainsString('EEAT', $system);
        $this->assertStringContainsString('"faqs" key', $system);
        $this->assertStringContainsString('faqs: array of {question, answer}', $system);
    }

    public function test_lint_catches_banned_phrases_meta_limits_and_missing_faqs(): void
    {
        $violations = ContentReviewer::lint([
            'description_html' => '<p>When it comes to widgets, this is designed to elevate your experience with a wide range of options.</p><h1>Bad</h1>',
            'short_description_html' => '<p>The perfect choice for everyone.</p>',
            'meta_title' => str_repeat('A', 70),
            'meta_description' => str_repeat('B', 200),
            'faqs' => [],
        ]);

        $text = implode("\n", $violations);

        $this->assertStringContainsString('when it comes to', $text);
        $this->assertStringContainsString('designed to', $text);
        $this->assertStringContainsString('elevate your', $text);
        $this->assertStringContainsString('wide range', $text);
        $this->assertStringContainsString('perfect choice', $text);
        // Owner rule: meta lengths NEVER block — they are clamped
        // mechanically instead of failing the copy.
        $this->assertStringNotContainsString('meta_title exceeds', $text);
        $this->assertStringNotContainsString('meta_description exceeds', $text);
        $this->assertStringContainsString('at least 5 question/answer pairs', $text);
        $this->assertStringContainsString('<h1>', $text);
    }

    public function test_lint_passes_clean_copy(): void
    {
        $violations = ContentReviewer::lint([
            'description_html' => '<h2>TEREA Amber flavor profile</h2><p>The flavor opens with a warm, earthy tobacco note.</p>',
            'short_description_html' => '<p>Rich roasted tobacco sticks for IQOS ILUMA, 20 per pack.</p>',
            'meta_title' => 'IQOS TEREA Amber Rich Tobacco | Dubai UAE',
            'meta_description' => 'Full-bodied roasted tobacco TEREA sticks for ILUMA. Same-day Dubai delivery.',
            'faqs' => [
                ['question' => 'Is it compatible with ILUMA?', 'answer' => 'Yes, all ILUMA devices.'],
                ['question' => 'How strong is it?', 'answer' => 'Regular strength.'],
                ['question' => 'How many sticks per pack?', 'answer' => '20 sticks.'],
                ['question' => 'Is delivery available in Dubai?', 'answer' => 'Yes, same-day.'],
                ['question' => 'Which flavors are similar?', 'answer' => 'Sienna and Bronze.'],
            ],
        ]);

        $this->assertSame([], $violations);
    }

    public function test_rulebook_contains_the_section_guide_and_variation_rules(): void
    {
        $system = ProductWriter::systemFor($this->batch());

        // The 20-section guide with flavor/device split
        $this->assertStringContainsString('SECTIONS TO DRAW FROM', $system);
        $this->assertStringContainsString('Flavor details / product experience', $system);
        $this->assertStringContainsString('For DEVICES instead', $system);
        $this->assertStringContainsString('Who should choose another option', $system);
        $this->assertStringContainsString('Safety / responsible-use note', $system);
        $this->assertStringContainsString('Not compatible with', $system);
        // Variation + store style rules
        $this->assertStringContainsString('UNIQUENESS', $system);
        $this->assertStringContainsString('never use em dashes', $system);
        $this->assertStringContainsString('no medical or health claims', $system);
        $this->assertStringContainsString('6-10 buyer questions', $system);
    }

    public function test_keywords_column_is_parsed_and_drives_the_writer_prompt(): void
    {
        // Comma-separated, trimmed, deduped; first keyword is the primary.
        $this->assertSame(
            ['terea amber uae', 'buy terea amber dubai'],
            ProductWriter::keywordsFor(['keywords' => ' terea amber uae , buy terea amber dubai, terea amber uae ,']),
        );
        $this->assertSame([], ProductWriter::keywordsFor(['name' => 'no keywords']));

        $batch = $this->batch();
        $item = $batch->items()->create(['row' => [
            'name' => 'TEREA Amber',
            'keywords' => 'terea amber uae, buy terea amber dubai',
        ]]);

        $prompt = ProductWriter::userPromptFor($item);

        $this->assertStringContainsString('TARGET KEYWORDS', $prompt);
        $this->assertStringContainsString('Primary: "terea amber uae"', $prompt);
        $this->assertStringContainsString('Secondary: "buy terea amber dubai"', $prompt);

        // No keywords column → no keyword block, prompt unchanged otherwise.
        $plain = $batch->items()->create(['row' => ['name' => 'TEREA Sienna']]);
        $this->assertStringNotContainsString('TARGET KEYWORDS', ProductWriter::userPromptFor($plain));
    }

    public function test_lint_flags_missing_primary_keyword_but_not_secondary(): void
    {
        $output = [
            'description_html' => '<h2>TEREA Amber flavor</h2><p>Warm, earthy tobacco note.</p>',
            'meta_title' => 'IQOS TEREA Amber, Rich Tobacco',
            'meta_description' => 'Full-bodied roasted tobacco TEREA sticks for ILUMA.',
            'faqs' => array_fill(0, 6, ['question' => 'Q?', 'answer' => 'A.']),
        ];

        // Owner rule: direct OR indirect placement counts. 'terea amber uae'
        // has 2 of its 3 meaningful words in the copy → indirect pass.
        $this->assertSame([], ContentReviewer::lint($output, keywords: ['terea amber uae', 'unused secondary phrase']));

        // A genuinely absent primary → blocking violation, secondary ignored.
        $violations = ContentReviewer::lint($output, keywords: ['menthol cooling capsule strength', 'unused secondary phrase']);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('menthol cooling capsule strength', $violations[0]);

        // Primary present (in copy or meta) → clean.
        $output['meta_title'] = 'Buy TEREA Amber UAE, Rich Tobacco';
        $this->assertSame([], ContentReviewer::lint($output, keywords: ['terea amber uae', 'unused secondary phrase']));

        // No keywords → no keyword check at all.
        $output['meta_title'] = 'IQOS TEREA Amber, Rich Tobacco';
        $this->assertSame([], ContentReviewer::lint($output));
    }

    public function test_structure_varies_per_item_while_system_stays_cached(): void
    {
        $batch = $this->batch();
        $a = $batch->items()->create(['row' => ['name' => 'A']]);
        $b = $batch->items()->create(['row' => ['name' => 'B']]);

        // Different variation directives per product (rotating by id)...
        $promptA = ProductWriter::userPromptFor($a);
        $promptB = ProductWriter::userPromptFor($b);
        $this->assertStringContainsString('STRUCTURE VARIATION', $promptA);
        $this->assertNotSame(
            preg_replace('/Product data:.*?STRUCTURE VARIATION/s', '', $promptA),
            preg_replace('/Product data:.*?STRUCTURE VARIATION/s', '', $promptB),
        );

        // ...while the cacheable system block stays byte-identical.
        $this->assertSame(ProductWriter::systemFor($batch), ProductWriter::systemFor($batch->fresh()));
    }

    public function test_publisher_saves_brand_category_keywords_and_all_faqs(): void
    {
        \Illuminate\Support\Facades\Storage::disk('local')->put('ai-imports/full.csv', "name,regular_price\nFull Widget,10\nOther,5\n");

        $batch = AiImportBatch::create([
            'name' => 'FW', 'csv_path' => 'ai-imports/full.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'publish_mode' => 'publish',
        ]);
        $item = $batch->items()->create(['row' => [
            'name' => 'Full Widget', 'regular_price' => '10',
            'brand' => 'IQOS', 'category' => 'Heated Tobacco, Terea Japan',
            'keywords' => 'full widget uae, buy full widget',
        ]]);

        $tenFaqs = array_map(fn ($i) => ['question' => "Q{$i}?", 'answer' => "A{$i}."], range(1, 10));

        $product = (new \App\Services\Ai\ProductPublisher(new \App\Services\Ai\DriveImageFetcher))->publish($item, [
            'short_description_html' => '<p>x</p>', 'description_html' => '<p>y</p>', 'css' => '',
            'meta_title' => 'T', 'meta_description' => 'D',
            'focus_keyword' => '',           // AI left it empty — CSV primary must win
            'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
            'faqs' => $tenFaqs,
        ]);

        // Brand + categories land on real catalog relations, created on first use.
        $this->assertSame('IQOS', $product->brand->name);
        $this->assertEqualsCanonicalizing(
            ['Heated Tobacco', 'Terea Japan'],
            $product->categories()->pluck('name')->all(),
        );

        // Primary CSV keyword backfills an empty focus_keyword.
        $this->assertSame('full widget uae', $product->refresh()->seoMeta->focus_keyword);

        // All 10 FAQs kept (writer is asked for 6-10).
        $this->assertSame(10, $product->faqs()->count());

        // Re-using the same brand/category must not duplicate them.
        $item2 = $batch->items()->create(['row' => [
            'name' => 'Other', 'regular_price' => '5', 'brand' => 'IQOS', 'category' => 'Heated Tobacco',
        ]]);
        (new \App\Services\Ai\ProductPublisher(new \App\Services\Ai\DriveImageFetcher))->publish($item2, [
            'short_description_html' => '<p>x</p>', 'description_html' => '<p>y</p>', 'css' => '',
            'meta_title' => 'T2', 'meta_description' => 'D2', 'focus_keyword' => 'other kw',
            'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c', 'faqs' => [],
        ]);

        $this->assertSame(1, \App\Models\Brand::count());
        $this->assertSame(2, \App\Models\Category::count());
    }

    public function test_publisher_creates_product_faqs_from_output(): void
    {
        \Illuminate\Support\Facades\Storage::disk('local')->put('ai-imports/faq.csv', "name,regular_price\nFaq Widget,10\nOther,5\n");

        $batch = AiImportBatch::create([
            'name' => 'F', 'csv_path' => 'ai-imports/faq.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'publish_mode' => 'publish',
        ]);
        $item = $batch->items()->create(['row' => ['name' => 'Faq Widget', 'regular_price' => '10']]);

        $product = (new \App\Services\Ai\ProductPublisher(new \App\Services\Ai\DriveImageFetcher))->publish($item, [
            'short_description_html' => '<p>x</p>',
            'description_html' => '<p>y</p>',
            'css' => '',
            'meta_title' => 'T', 'meta_description' => 'D', 'focus_keyword' => 'k',
            'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
            'faqs' => [
                ['question' => 'Q1?', 'answer' => 'A1.'],
                ['question' => 'Q2?', 'answer' => 'A2.'],
                ['question' => 'Q3?', 'answer' => 'A3.'],
                ['question' => '', 'answer' => 'skipped — empty question'],
            ],
        ]);

        $this->assertSame(3, $product->faqs()->count());
        $this->assertSame('Q1?', $product->faqs()->orderBy('sort_order')->first()->question);
    }
}
