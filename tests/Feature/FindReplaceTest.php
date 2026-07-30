<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentReplaceBatch;
use App\Models\Faq;
use App\Models\Product;
use App\Services\Content\FindReplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FindReplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): FindReplaceService
    {
        return app(FindReplaceService::class);
    }

    protected function product(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber-'.uniqid(), 'type' => 'simple',
            'price' => 120, 'status' => 'published', 'visibility' => 'visible',
            'short_description' => 'Free delivery over 300 AED.',
            'description' => '<p>Order over 300 AED for free delivery.</p><p>Fast shipping.</p>',
        ], $attrs));
    }

    // ── Dry run ────────────────────────────────────────────────────

    public function test_dry_run_finds_matches_and_writes_nothing(): void
    {
        $p = $this->product();

        $res = $this->service()->dryRun('300 AED', ['products']);

        $this->assertSame(1, $res['records']);
        $this->assertSame(2, $res['occurrences']); // short_description + description
        $this->assertStringContainsString('products.', $res['matches'][0]['location']);
        $this->assertStringContainsString('«300 AED»', $res['matches'][0]['preview']);

        // Nothing changed.
        $p->refresh();
        $this->assertStringContainsString('300 AED', $p->short_description);
    }

    public function test_dry_run_empty_search_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->dryRun('   ', ['products']);
    }

    // ── Apply ──────────────────────────────────────────────────────

    public function test_apply_replaces_only_targeted_content(): void
    {
        $p = $this->product();

        $batch = $this->service()->apply('300 AED', '400 AED', ['products']);

        $p->refresh();
        $this->assertStringContainsString('400 AED', $p->short_description);
        $this->assertStringContainsString('400 AED', $p->description);
        $this->assertStringNotContainsString('300 AED', $p->short_description);
        $this->assertSame(1, $batch->records_count);
        $this->assertSame(2, $batch->occurrences_count);
    }

    public function test_apply_preserves_html_tags(): void
    {
        $p = $this->product(['description' => '<p>Order over <strong>300 AED</strong> today.</p>']);

        $this->service()->apply('300 AED', '400 AED', ['products']);

        $this->assertSame('<p>Order over <strong>400 AED</strong> today.</p>', $p->refresh()->description);
    }

    public function test_apply_never_touches_name_price_or_slug(): void
    {
        // "300" appears in the NAME and slug too — must stay untouched.
        $p = $this->product(['name' => 'Combo 300 Pack', 'slug' => 'combo-300', 'price' => 300]);

        $this->service()->apply('300', '400', ['products']);

        $p->refresh();
        $this->assertSame('Combo 300 Pack', $p->name);   // name untouched
        $this->assertSame('combo-300', $p->slug);         // slug untouched
        $this->assertSame(300.0, (float) $p->price);      // price untouched
        $this->assertStringContainsString('400', $p->description); // content changed
    }

    public function test_case_sensitive_by_default_and_insensitive_when_asked(): void
    {
        $p = $this->product([
            'short_description' => 'menthol and menthol and MENTHOL',
            'description' => 'no numbers here',
        ]);

        // Case-sensitive (default): only the two lowercase "menthol".
        $b1 = $this->service()->apply('menthol', 'mint', ['products']);
        $this->assertSame(2, $b1->occurrences_count);
        $this->assertSame('mint and mint and MENTHOL', $p->refresh()->short_description);

        // Case-insensitive: catches the remaining uppercase one.
        $b2 = $this->service()->apply('menthol', 'mint', ['products'], ['case_sensitive' => false]);
        $this->assertSame(1, $b2->occurrences_count);
        $this->assertSame('mint and mint and mint', $p->refresh()->short_description);
    }

    public function test_whole_word_only_does_not_match_substrings(): void
    {
        $p = $this->product([
            'short_description' => 'Price 300 vs 1300 vs 3000.',
            'description' => 'nothing numeric here',
        ]);

        $b = $this->service()->apply('300', '400', ['products'], ['whole_word' => true]);

        // Only the standalone "300" — not 1300 or 3000.
        $this->assertSame(1, $b->occurrences_count);
        $this->assertSame('Price 400 vs 1300 vs 3000.', $p->refresh()->short_description);
    }

    public function test_apply_is_scoped_products_untouched_when_only_faq_selected(): void
    {
        $p = $this->product();
        $faq = $p->faqs()->create(['question' => 'Is delivery 300 AED?', 'answer' => 'Free over 300 AED.', 'is_active' => true]);

        $this->service()->apply('300 AED', '400 AED', ['faqs']);

        // FAQ changed, product left alone.
        $faq->refresh();
        $this->assertStringContainsString('400 AED', $faq->answer);
        $this->assertStringContainsString('300 AED', $p->refresh()->short_description);
    }

    public function test_find_equals_replace_changes_nothing(): void
    {
        $p = $this->product();

        $batch = $this->service()->apply('300 AED', '300 AED', ['products']);

        $this->assertSame(0, $batch->records_count);
        $this->assertSame(0, $batch->occurrences_count);
        $this->assertStringContainsString('300 AED', $p->refresh()->short_description);
    }

    // ── Undo ───────────────────────────────────────────────────────

    public function test_revert_restores_exact_previous_values(): void
    {
        $p = $this->product();
        $before = $p->description;

        $batch = $this->service()->apply('300 AED', '400 AED', ['products']);
        $this->assertStringContainsString('400 AED', $p->refresh()->description);

        $result = $this->service()->revert($batch);

        $this->assertSame(2, $result['restored']);
        $this->assertSame($before, $p->refresh()->description); // byte-for-byte restore
        $this->assertNotNull($batch->refresh()->reverted_at);
    }

    public function test_revert_skips_fields_edited_after_replace(): void
    {
        $p = $this->product();
        $batch = $this->service()->apply('300 AED', '400 AED', ['products']);

        // Someone manually edits the field after the batch.
        $p->refresh();
        $p->short_description = 'Manually rewritten copy.';
        $p->save();

        $result = $this->service()->revert($batch);

        // short_description was edited → skipped; description → restored.
        $this->assertSame(1, $result['restored']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('Manually rewritten copy.', $p->refresh()->short_description);
    }

    public function test_double_revert_is_a_noop(): void
    {
        $p = $this->product();
        $batch = $this->service()->apply('300 AED', '400 AED', ['products']);

        $this->service()->revert($batch);
        $second = $this->service()->revert($batch->refresh());

        $this->assertSame(0, $second['restored']);
    }

    // ── SEO + multi-scope ──────────────────────────────────────────

    public function test_seo_meta_scope_replaces_and_is_labelled_by_parent(): void
    {
        $p = $this->product();
        $seo = $p->seoMeta()->firstOrNew([]);
        $seo->fill(['title' => 'Buy over 300 AED', 'description' => 'Free over 300 AED'])->save();

        $res = $this->service()->dryRun('300 AED', ['product_seo']);
        $this->assertSame(1, $res['records']);
        $this->assertStringContainsString('TEREA Amber', $res['matches'][0]['record']); // parent name

        $this->service()->apply('300 AED', '400 AED', ['product_seo']);
        $this->assertSame('Buy over 400 AED', $seo->refresh()->title);
    }

    public function test_multiple_scopes_at_once(): void
    {
        $p = $this->product();
        $c = Category::create(['name' => 'Devices', 'slug' => 'devices', 'is_active' => true, 'description' => 'Free over 300 AED']);

        $batch = $this->service()->apply('300 AED', '400 AED', ['products', 'categories']);

        $this->assertSame(2, $batch->records_count);
        $this->assertStringContainsString('400 AED', $p->refresh()->short_description);
        $this->assertStringContainsString('400 AED', $c->refresh()->description);
    }

    public function test_unknown_scope_keys_are_ignored_safely(): void
    {
        $p = $this->product();

        $batch = $this->service()->apply('300 AED', '400 AED', ['products', 'users', 'orders', 'nonsense']);

        // Only the valid "products" scope ran; no error from the bogus keys.
        $this->assertSame(1, $batch->records_count);
        $this->assertContains('products', $batch->scopes);
        $this->assertNotContains('orders', $batch->scopes);
    }

    public function test_scope_options_only_expose_whitelisted_content_fields(): void
    {
        $opts = $this->service()->scopeOptions();

        $this->assertArrayHasKey('products', $opts);
        $this->assertArrayHasKey('product_seo', $opts);
        // No scope may target identity/price tables.
        foreach (array_keys(FindReplaceService::SCOPES) as $key) {
            $cfg = FindReplaceService::SCOPES[$key];
            $this->assertNotContains('name', $cfg['columns']);
            $this->assertNotContains('slug', $cfg['columns']);
            $this->assertNotContains('price', $cfg['columns']);
        }
    }
}
