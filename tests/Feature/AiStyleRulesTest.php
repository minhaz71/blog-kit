<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Ai\ContentReviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiStyleRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function cleanOutput(): array
    {
        return [
            'short_description_html' => '<p>Good widget.</p>',
            'description_html' => '<h2>About</h2><p>A solid widget worth buying.</p>',
            'meta_title' => 'T', 'meta_description' => 'D',
            'faqs' => array_fill(0, 5, ['question' => 'Q?', 'answer' => 'A.']),
        ];
    }

    public function test_lint_blocks_em_dashes_in_any_field(): void
    {
        $this->assertSame([], ContentReviewer::lint($this->cleanOutput()));

        // Em dash in the body.
        $output = $this->cleanOutput();
        $output['description_html'] = '<p>Bold flavor — smooth finish.</p>';
        $issues = ContentReviewer::lint($output);
        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'dashes')));

        // En dash hiding in a FAQ answer is caught too.
        $output = $this->cleanOutput();
        $output['faqs'][2]['answer'] = 'Works with ILUMA – all versions.';
        $issues = ContentReviewer::lint($output);
        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'dashes')));
    }

    public function test_lint_requires_key_fact_bullets_in_product_short_description(): void
    {
        // PRODUCT output (suggested_price present) with a thin one-liner → violation.
        $output = $this->cleanOutput();
        $output['suggested_price'] = 32;

        $issues = ContentReviewer::lint($output);
        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'key-fact bullets')));

        // With the bullet list → clean.
        $output['short_description_html'] = '<p>Good widget.</p><ul>'
            .'<li><strong>Flavor:</strong> Menthol</li>'
            .'<li><strong>Pack:</strong> 1 carton = 10 packs x 20 sticks = 200 sticks</li>'
            .'<li><strong>Compatibility:</strong> IQOS ILUMA series only</li>'
            .'<li><strong>Strength:</strong> Medium</li></ul>';
        $this->assertSame([], ContentReviewer::lint($output));

        // Blog-shaped output (no suggested_price) is never held to this rule.
        $this->assertSame([], ContentReviewer::lint($this->cleanOutput()));
    }

    public function test_product_prompt_carries_bullet_contract_heading_bans_and_concreteness(): void
    {
        $batch = \App\Models\AiImportBatch::create([
            'name' => 'Prompt check', 'csv_path' => '', 'prompt' => 'brief', 'provider' => 'anthropic',
        ]);

        $system = \App\Services\Ai\ProductWriter::systemFor($batch);

        // Short description = hook + key-fact bullets.
        $this->assertStringContainsString('key-fact bullets', $system);
        $this->assertStringContainsString('1 carton = 10 packs x 20 sticks = 200 sticks', $system);

        // Headings must teach; boilerplate labels are banned; keyword allowed in 1-2.
        $this->assertStringContainsString('BANNED heading styles', $system);
        $this->assertStringContainsString('could a buyer skim ONLY the headings', $system);
        $this->assertStringContainsString('MAY appear in 1-2 headings', $system);

        // Anti-thin-content rule.
        $this->assertStringContainsString('CONCRETENESS', $system);
    }

    public function test_lint_flags_keyword_stuffed_headings(): void
    {
        // 3 of 4 headings repeat the focus keyword → stuffing violation.
        $output = $this->cleanOutput();
        $output['focus_keyword'] = 'terea yellow';
        $output['description_html'] = '<h2>TEREA Yellow Overview</h2><p>a</p>'
            .'<h2>Buy TEREA Yellow in Dubai</h2><p>b</p>'
            .'<h2>TEREA Yellow Price</h2><p>c</p>'
            .'<h2>Delivery Details</h2><p>d</p>';

        $issues = ContentReviewer::lint($output);
        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'headings')));

        // Descriptive semantic headings pass, even with one keyword mention.
        $output['description_html'] = '<h2>TEREA Yellow Overview</h2><p>a</p>'
            .'<h2>Flavor Experience</h2><p>b</p>'
            .'<h2>Compatible Devices</h2><p>c</p>'
            .'<h2>Delivery Details</h2><p>d</p>';

        $this->assertSame([], ContentReviewer::lint($output));

        // The product name (H1) repeated across headings is stuffing too,
        // even when the focus keyword differs.
        $output['description_html'] = '<h2>IQOS Terea Amber Intro</h2><p>a</p>'
            .'<h2>Why IQOS Terea Amber</h2><p>b</p>'
            .'<h2>IQOS Terea Amber FAQs</h2><p>c</p>'
            .'<h2>Ordering</h2><p>d</p>';
        $output['focus_keyword'] = 'buy heated tobacco';

        $issues = ContentReviewer::lint($output, pageTitle: 'IQOS Terea Amber');
        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'headings')));

        // Short copy (under 4 headings) is never flagged — too few
        // headings to judge distribution.
        $output['description_html'] = '<h2>IQOS Terea Amber</h2><p>a</p><h2>IQOS Terea Amber Price</h2><p>b</p>';
        $this->assertSame([], ContentReviewer::lint($output, pageTitle: 'IQOS Terea Amber'));
    }

    public function test_lint_blocks_ai_style_words(): void
    {
        foreach (['Let us delve into the details.', 'A seamless experience awaits.', 'Meticulously engineered housing.'] as $sentence) {
            $output = $this->cleanOutput();
            $output['description_html'] = "<p>{$sentence}</p>";
            $issues = ContentReviewer::lint($output);
            $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'Banned phrase')), "not flagged: {$sentence}");
        }

        // Word-boundary safety: "delved" contains "delve" but "seamstress"
        // must not trip "seam"-anything; normal copy stays clean.
        $output = $this->cleanOutput();
        $output['description_html'] = '<p>The seamstress stitched the case liner by hand.</p>';
        $this->assertSame([], ContentReviewer::lint($output));
    }

    public function test_strip_command_cleans_existing_content(): void
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget', 'type' => 'simple', 'price' => 10,
            'status' => 'published', 'stock_status' => 'in_stock',
            'description' => '<p>Rich flavor — with a 2–4 second warm-up.</p>',
        ]);

        $this->artisan('content:strip-em-dashes')->assertSuccessful();

        $product->refresh();
        $this->assertStringNotContainsString('—', $product->description);
        $this->assertStringContainsString('Rich flavor, with', $product->description); // word dash → comma
        $this->assertStringContainsString('2-4 second', $product->description);        // numeric range → hyphen
    }

    public function test_lint_blocks_meta_title_equal_to_h1(): void
    {
        $output = $this->cleanOutput();
        $output["meta_title"] = "IQOS Terea Amber";

        $issues = \App\Services\Ai\ContentReviewer::lint($output, pageTitle: "IQOS Terea Amber");
        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, "identical to the page H1")));

        // A differentiated snippet passes.
        $output["meta_title"] = "Buy IQOS Terea Amber in Dubai | 1-Hour Delivery";
        $this->assertSame([], \App\Services\Ai\ContentReviewer::lint($output, pageTitle: "IQOS Terea Amber"));
    }

    public function test_crafted_meta_titles_render_without_site_suffix(): void
    {
        \App\Models\Setting::set("seo.site_title", "Terea Hub");

        $custom = new \App\Services\Seo\SeoData(title: "Buy X in Dubai | Fast Delivery", customTitle: true);
        $this->assertSame("Buy X in Dubai | Fast Delivery", $custom->fullTitle());

        $fallback = new \App\Services\Seo\SeoData(title: "Shop");
        $this->assertSame("Shop | Terea Hub", $fallback->fullTitle());
    }
}