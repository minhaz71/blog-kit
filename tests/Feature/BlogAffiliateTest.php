<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\AiImportItem;
use App\Models\User;
use App\Services\Ai\BlogPublisher;
use App\Services\Ai\BlogWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAffiliateTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_links_column_parses_names_and_urls(): void
    {
        $links = BlogWriter::affiliateLinks(['affiliate_links' => "Widget A | https://aff.link/a\nhttps://aff.link/b ; Gizmo | https://go.example/g"]);

        $this->assertCount(3, $links);
        $this->assertSame('Widget A', $links[0]['name']);
        $this->assertSame('https://aff.link/a', $links[0]['url']);
        $this->assertSame('aff.link', $links[1]['name']);           // bare URL → host as name
        $this->assertSame('https://go.example/g', $links[2]['url']);
    }

    public function test_user_prompt_lists_affiliate_products(): void
    {
        $batch = AiImportBatch::create(['name' => 'Aff', 'kind' => 'blog', 'prompt' => 'brief', 'provider' => 'anthropic', 'csv_path' => '', 'affiliate_mode' => true]);
        $item = AiImportItem::create(['batch_id' => $batch->id, 'status' => 'pending', 'row' => [
            'name' => 'Best Widgets 2026', 'role' => 'affiliate',
            'affiliate_links' => 'Widget A | https://aff.link/a',
        ]]);

        $prompt = BlogWriter::userPromptFor($item);
        $this->assertStringContainsString('AFFILIATE PRODUCTS', $prompt);
        $this->assertStringContainsString('https://aff.link/a', $prompt);
    }

    public function test_affiliate_mode_injects_rulebook_and_disclosure(): void
    {
        $batch = AiImportBatch::create(['name' => 'Aff', 'kind' => 'blog', 'prompt' => 'brief', 'provider' => 'anthropic', 'csv_path' => '', 'affiliate_mode' => true, 'affiliate_disclosure' => 'We may earn a commission.']);

        $system = BlogWriter::systemFor($batch);
        $this->assertStringContainsString('AFFILIATE ARTICLE RULES', $system);
        $this->assertStringContainsString('We may earn a commission.', $system);
        $this->assertStringContainsString('bd-affiliate-btn', $system);
    }

    public function test_non_affiliate_batch_has_no_affiliate_rules(): void
    {
        $batch = AiImportBatch::create(['name' => 'Plain', 'kind' => 'blog', 'prompt' => 'brief', 'provider' => 'anthropic', 'csv_path' => '']);
        $this->assertStringNotContainsString('AFFILIATE ARTICLE RULES', BlogWriter::systemFor($batch));
    }

    public function test_publisher_adds_sponsored_nofollow_to_external_links(): void
    {
        $html = '<p>Try <a href="https://aff.link/a">Widget A</a> and read our <a href="/blog/guide">guide</a>. '
            .'<a class="bd-affiliate-btn" href="https://aff.link/a">Check price</a></p>';

        $out = (new BlogPublisher)->enforceAffiliateRel($html);

        // External affiliate links get the correct rel + target.
        $this->assertStringContainsString('rel="sponsored nofollow noopener"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
        // The internal link is untouched (no rel/target added).
        $this->assertStringContainsString('<a href="/blog/guide">guide</a>', $out);
        // The CTA button class survives.
        $this->assertStringContainsString('class="bd-affiliate-btn"', $out);
        // Applying twice must not double-stack rel attributes.
        $this->assertSame($out, (new BlogPublisher)->enforceAffiliateRel($out));
    }

    public function test_csv_affiliate_links_marks_the_row_as_affiliate(): void
    {
        $author = User::create(['name' => 'A', 'email' => 'aff@x.example', 'password' => bcrypt('x'), 'is_active' => true]);
        $csv = "title,keywords,affiliate\nBest Widgets,widgets,\"Widget A | https://aff.link/a\"\n";
        \Illuminate\Support\Facades\Storage::disk('local')->put('ai-imports/aff.csv', $csv);

        $batch = AiImportBatch::create(['name' => 'B', 'kind' => 'blog', 'csv_path' => 'ai-imports/aff.csv', 'prompt' => 'brief', 'provider' => 'anthropic', 'user_id' => $author->id]);
        (new \App\Services\Ai\BlogPlanner)->plan($batch);

        $row = $batch->items()->first()->row;
        $this->assertSame('affiliate', $row['role']);                       // auto-detected
        $this->assertSame('Widget A | https://aff.link/a', $row['affiliate_links']); // "affiliate" aliased
    }
}
