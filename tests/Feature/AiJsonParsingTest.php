<?php

namespace Tests\Feature;

use App\Services\Ai\CrossReviewer;
use App\Services\Ai\LlmClient;
use Tests\TestCase;

/**
 * parseJson must survive what LLMs actually send back: fenced blocks,
 * surrounding prose, literal control characters inside string values and
 * trailing commas. A product must never fail on a repairable reply.
 */
class AiJsonParsingTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_parses_plain_json(): void
    {
        $this->assertSame(['a' => 1], LlmClient::parseJson('{"a": 1}'));
    }

    public function test_parses_fenced_json(): void
    {
        $parsed = LlmClient::parseJson("```json\n{\"short_description_html\": \"<p>Hi</p>\"}\n```");

        $this->assertSame('<p>Hi</p>', $parsed['short_description_html']);
    }

    public function test_parses_fence_without_language_tag(): void
    {
        $this->assertSame(['a' => 1], LlmClient::parseJson("```\n{\"a\": 1}\n```"));
    }

    public function test_parses_json_wrapped_in_prose(): void
    {
        $parsed = LlmClient::parseJson("Here is the copy you asked for:\n{\"a\": 1}\nLet me know if you need changes.");

        $this->assertSame(['a' => 1], $parsed);
    }

    public function test_repairs_literal_newlines_inside_strings(): void
    {
        // Literal (unescaped) newline inside a string value — invalid JSON,
        // the most common LLM fault in long HTML descriptions.
        $raw = "{\"description_html\": \"<h2>About</h2>\n<p>Long copy</p>\", \"css\": \"\"}";

        $parsed = LlmClient::parseJson($raw);

        $this->assertSame("<h2>About</h2>\n<p>Long copy</p>", $parsed['description_html']);
    }

    public function test_repairs_literal_tab_and_trailing_comma(): void
    {
        $raw = "{\"a\": \"x\ty\", \"b\": [1, 2,],}";

        $this->assertSame(['a' => "x\ty", 'b' => [1, 2]], LlmClient::parseJson($raw));
    }

    public function test_repair_keeps_valid_escapes_intact(): void
    {
        $raw = "```json\n{\"a\": \"He said \\\"hi\\\"\nnext line\"}\n```";

        $this->assertSame("He said \"hi\"\nnext line", LlmClient::parseJson($raw)['a']);
    }

    public function test_still_throws_on_hopeless_reply(): void
    {
        $this->expectExceptionMessage('LLM did not return valid JSON');

        LlmClient::parseJson('Sorry, I cannot help with that.');
    }

    public function test_meta_lengths_are_clamped_at_word_boundaries(): void
    {
        $clamped = \App\Services\Ai\ContentReviewer::clampMetaLengths([
            'meta_title' => 'IQOS Terea Oasis Pearl Japan – Cool Melon Heated Tobacco Sticks Dubai',
            'meta_description' => str_repeat('Genuine Japan stock with fast delivery. ', 5),
        ]);

        $this->assertLessThanOrEqual(63, mb_strlen($clamped["meta_title"]));
        $this->assertLessThanOrEqual(160, mb_strlen($clamped['meta_description']));
        // Cut lands on a word boundary — no chopped final word.
        $this->assertMatchesRegularExpression('/\w$/u', $clamped['meta_title']);

        // Already-short values pass through untouched.
        $same = \App\Services\Ai\ContentReviewer::clampMetaLengths(['meta_title' => 'Short title']);
        $this->assertSame('Short title', $same['meta_title']);
    }

    public function test_em_dashes_are_rewritten_across_the_whole_output(): void
    {
        $out = \App\Services\Ai\ContentReviewer::stripEmDashes([
            'short_description_html' => '<p>Woody sticks — made for ILUMA — 20 per pack.</p>',
            'meta_title' => 'TEREA Amber – Rich Tobacco',
            'suggested_price' => 32,
            'faqs' => [['question' => 'Strength?', 'answer' => 'Regular strength — around 12–14 puffs.']],
        ]);

        $this->assertSame('<p>Woody sticks, made for ILUMA, 20 per pack.</p>', $out['short_description_html']);
        $this->assertSame('TEREA Amber, Rich Tobacco', $out['meta_title']);
        $this->assertSame(32, $out['suggested_price']);
        // Numeric range keeps a plain hyphen, prose dash becomes a comma.
        $this->assertSame('Regular strength, around 12-14 puffs.', $out['faqs'][0]['answer']);
    }

    public function test_internal_links_are_made_root_relative_and_stay_valid(): void
    {
        $appUrl = rtrim(config('app.url'), '/');

        $product = \App\Models\Product::create([
            'name' => 'Linked Widget', 'slug' => 'linked-widget', 'price' => 10, 'status' => 'published',
            'description' => '<p>See <a href="'.$appUrl.'/product/sibling-a">Sibling A</a> and <a href="https://example.com/x">external</a>.</p>',
            'short_description' => '<p>Compare with <a href="'.$appUrl.'/product/sibling-b">Sibling B</a>.</p>',
        ]);

        $rewritten = \App\Services\Ai\InternalLinker::relativize($product);
        $product->refresh();

        // Own-domain links become root-relative; external links untouched.
        $this->assertSame(2, $rewritten);
        $this->assertStringContainsString('href="/product/sibling-a"', $product->description);
        $this->assertStringContainsString('href="https://example.com/x"', $product->description);
        $this->assertStringContainsString('href="/product/sibling-b"', $product->short_description);

        // A later audit must recognize the relative form as valid catalog links.
        $stats = (new \App\Services\Ai\InternalLinker)->audit($product, [
            $appUrl.'/product/sibling-a', $appUrl.'/product/sibling-b',
        ]);
        $this->assertSame(2, $stats['kept']);
        $this->assertStringContainsString('href="/product/sibling-a"', $product->fresh()->description);
    }

    public function test_reviewer_localhost_complaints_are_dropped(): void
    {
        $issues = CrossReviewer::dropFalsePositives([
            'Remove localhost URLs in description links; replace with valid product URLs.',
            'Remove 127.0.0.1:8000 links in product comparisons.',
            'Shorten meta_title to ≤60 chars.',
        ]);

        $this->assertSame(['Shorten meta_title to ≤60 chars.'], $issues);
    }
}
