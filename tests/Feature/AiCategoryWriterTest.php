<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Ai\CategoryWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCategoryWriterTest extends TestCase
{
    use RefreshDatabase;

    protected function category(): Category
    {
        $parent = Category::create(['name' => 'Heated Tobacco', 'slug' => 'heated-tobacco', 'is_active' => true]);
        $category = Category::create(['name' => 'TEREA Kazakhstan', 'slug' => 'terea-kazakhstan', 'is_active' => true, 'parent_id' => $parent->id]);
        Category::create(['name' => 'TEREA KZ Menthol', 'slug' => 'terea-kz-menthol', 'is_active' => true, 'parent_id' => $category->id]);

        foreach ([['TEREA Amber KZ', 'Rich roasted tobacco for ILUMA.'], ['TEREA Silver KZ', 'Light crisp blend, low intensity.']] as $i => [$name, $desc]) {
            $product = Product::create([
                'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
                'price' => 30 + $i, 'status' => 'published', 'short_description' => "<p>{$desc}</p>",
            ]);
            $product->categories()->attach($category->id);
        }

        return $category;
    }

    protected function fakeWriter(array $overrides = []): void
    {
        Setting::set('ai.anthropic_api_key', 'test-key');

        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode($overrides + [
            'content_html' => '<h2>Kazakhstan TEREA at a Glance</h2><p>Bold blends made for ILUMA, from '
                .'<a href="/product/terea-amber-kz">TEREA Amber KZ</a> at AED 30.</p><script>alert(1)</script>'
                .'<h2>Which KZ Flavor Fits You</h2><p>Silver KZ stays light; Amber KZ runs rich and warm.</p>',
            'description' => 'Kazakhstan-market TEREA sticks for IQOS ILUMA with fast UAE delivery.',
            'meta_title' => 'Buy TEREA Kazakhstan in UAE | Fast Dubai Delivery',
            'meta_description' => 'Genuine Kazakhstan-market TEREA sticks for IQOS ILUMA. Bold flavors, sealed cartons and 1-hour Dubai delivery, cash or card on delivery across the UAE.',
            'focus_keyword' => 'terea kazakhstan uae',
            'secondary_keywords' => ['kazakhstan terea sticks', 'terea kz flavors', 'kazakhstan terea sticks'],
            'faqs' => array_map(fn ($i) => ['question' => "KZ question {$i}?", 'answer' => "KZ answer {$i}."], range(1, 5)),
        ])]]])]);
    }

    public function test_prompt_carries_products_market_analysis_and_keyword_research(): void
    {
        $category = $this->category();

        $system = CategoryWriter::systemFor();
        $user = CategoryWriter::userPromptFor($category);

        // The agent's three pillars: grounding, market analysis, keyword research.
        $this->assertStringContainsString('top 10 competing category pages', $system);
        $this->assertStringContainsString('KEYWORD RESEARCH', $system);
        $this->assertStringContainsString('E-E-A-T', $system);

        // Grounding input: this category's product titles + short descriptions.
        $this->assertStringContainsString('TEREA Amber KZ', $user);
        $this->assertStringContainsString('Rich roasted tobacco for ILUMA.', $user);
        // Hierarchy context + linkable catalog.
        $this->assertStringContainsString('Heated Tobacco > TEREA Kazakhstan', $user);
        $this->assertStringContainsString('TEREA KZ Menthol (sub-category)', $user);
        $this->assertStringContainsString('/product/terea-amber-kz', $user);
    }

    public function test_write_and_apply_updates_content_seo_and_faqs(): void
    {
        $category = $this->category();
        $this->fakeWriter();

        $result = CategoryWriter::forProvider('anthropic')->write($category);
        $applied = CategoryWriter::apply($category, $result['output']);

        $category->refresh();

        // Content stored, sanitized (script stripped), links kept.
        $this->assertStringContainsString('Kazakhstan TEREA at a Glance', $category->content_block);
        $this->assertStringContainsString('href="/product/terea-amber-kz"', $category->content_block);
        $this->assertStringNotContainsString('<script', $category->content_block);

        // Short description + full SEO meta from the keyword research.
        $this->assertSame('Kazakhstan-market TEREA sticks for IQOS ILUMA with fast UAE delivery.', $category->description);
        $this->assertSame('Buy TEREA Kazakhstan in UAE | Fast Dubai Delivery', $category->seoMeta->title);
        $this->assertSame('terea kazakhstan uae', $category->seoMeta->focus_keyword);
        $this->assertSame(['kazakhstan terea sticks', 'terea kz flavors'], $category->seoMeta->secondary_keywords); // deduped
        $this->assertNotEmpty($category->seoMeta->description);

        // FAQs created because the category had none.
        $this->assertTrue($applied['faqs_written']);
        $this->assertSame(5, $category->faqs()->count());
    }

    public function test_existing_faqs_are_never_overwritten(): void
    {
        $category = $this->category();
        $category->faqs()->create(['question' => 'Curated Q?', 'answer' => 'Curated A.', 'sort_order' => 0, 'is_active' => true]);
        $this->fakeWriter();

        $result = CategoryWriter::forProvider('anthropic')->write($category);
        $applied = CategoryWriter::apply($category, $result['output']);

        $this->assertFalse($applied['faqs_written']);
        $this->assertSame(1, $category->faqs()->count());
        $this->assertSame('Curated Q?', $category->faqs()->first()->question);
    }

    public function test_independent_reviewer_critiques_and_writer_fixes(): void
    {
        $category = $this->category();
        \App\Models\Setting::set('ai.anthropic_api_key', 'test-key');

        $draft = fn (string $marker) => ['content' => [['text' => json_encode([
            'content_html' => "<h2>Kazakhstan TEREA at a Glance</h2><p>{$marker} Bold blends from TEREA Amber KZ at AED 30.</p>"
                .'<h2>Which KZ Flavor Fits You</h2><p>Silver KZ stays light; Amber KZ runs rich.</p>',
            'description' => 'Kazakhstan-market TEREA sticks for IQOS ILUMA.',
            'meta_title' => 'Buy TEREA Kazakhstan in UAE | Fast Delivery',
            'meta_description' => 'Genuine Kazakhstan-market TEREA sticks for IQOS ILUMA. Bold flavors, sealed cartons and 1-hour Dubai delivery, cash or card on delivery across the UAE.',
            'focus_keyword' => 'terea kazakhstan uae',
            'secondary_keywords' => ['kazakhstan terea sticks'],
            'faqs' => array_map(fn ($i) => ['question' => "Q{$i}?", 'answer' => "A{$i}."], range(1, 5)),
        ])]]];

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($draft('DRAFT.'))                                                     // writer: first draft
                ->push(['content' => [['text' => '{"approved": false, "issues": ["Opening paragraph is too thin — add a concrete delivery promise."]}']]]) // reviewer: reject
                ->push($draft('FIXED with 1-hour Dubai delivery.'))                          // writer: fix
                ->push(['content' => [['text' => '{"approved": true, "issues": []}']]]),     // reviewer: approve
        ]);

        $reviewer = \App\Services\Ai\LlmClient::for('anthropic')->withContext('category');
        $result = CategoryWriter::forProvider('anthropic')->write($category, passes: 2, reviewer: $reviewer);

        // The reviewer's critique drove exactly one fix cycle, then approval.
        $this->assertSame(1, $result['passes_used']);
        $this->assertSame([], $result['issues']);
        $this->assertStringContainsString('FIXED with 1-hour Dubai delivery.', $result['output']['content_html']);
    }

    public function test_background_command_writes_the_category_and_reports_done_status(): void
    {
        $category = $this->category();
        $this->fakeWriter();

        $this->artisan('category:write', ['category' => $category->id, '--provider' => 'anthropic'])
            ->assertSuccessful();

        // The detached command did the work and published a done status.
        $category->refresh();
        $this->assertStringContainsString('Kazakhstan TEREA at a Glance', $category->content_block);
        $this->assertSame('done', CategoryWriter::status($category->id)['status']);
    }

    public function test_background_command_records_a_failed_status_and_never_blanks_on_error(): void
    {
        $category = $this->category();
        $category->update(['content_block' => '<p>Hand-written copy that must survive.</p>']);
        \App\Models\Setting::set('ai.anthropic_api_key', 'test-key');
        // AI returns empty content → apply() refuses it.
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode(['content_html' => '<p></p>', 'meta_title' => 'X'])]]])]);

        $this->artisan('category:write', ['category' => $category->id, '--provider' => 'anthropic'])
            ->assertFailed();

        $this->assertSame('failed', CategoryWriter::status($category->id)['status']);
        $this->assertStringContainsString('must survive', $category->fresh()->content_block);
    }

    public function test_apply_refuses_empty_content_and_never_blanks_the_category(): void
    {
        $category = $this->category();
        $category->update(['content_block' => '<p>Existing hand-written content that must survive.</p>']);

        try {
            CategoryWriter::apply($category, ['content_html' => '<p></p>', 'meta_title' => 'X']);
            $this->fail('Expected degenerate output to be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no usable category content', $e->getMessage());
        }

        // Nothing was overwritten.
        $this->assertStringContainsString('must survive', $category->fresh()->content_block);
        $this->assertNull($category->fresh()->seoMeta);
    }

    public function test_em_dashes_are_stripped_and_meta_title_clamped(): void
    {
        $category = $this->category();
        $this->fakeWriter([
            'meta_title' => 'Buy TEREA Kazakhstan — the very best premium selection available in the whole UAE today',
        ]);

        $result = CategoryWriter::forProvider('anthropic')->write($category);
        CategoryWriter::apply($category, $result['output']);

        $title = $category->refresh()->seoMeta->title;
        $this->assertStringNotContainsString('—', $title);
        $this->assertLessThanOrEqual(60, mb_strlen($title));
    }
}
