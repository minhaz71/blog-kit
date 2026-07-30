<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkSuggestion;
use App\Models\LinkTarget;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\Seo\LinkApplier;
use App\Services\Seo\LinkDictionary;
use App\Services\Seo\LinkSuggestionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $name, string $slug, string $description = '<p>plain text</p>'): Product
    {
        return Product::create([
            'name' => $name, 'slug' => $slug, 'type' => 'simple', 'price' => 30,
            'status' => 'published', 'description' => $description,
        ]);
    }

    protected function rebuild(): void
    {
        app(LinkDictionary::class)->rebuild();
    }

    // ── Dictionary ──────────────────────────────────────────────────

    public function test_dictionary_builds_ngrams_and_sets_with_df_filter(): void
    {
        $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');
        $this->product('IQOS Terea Amber', 'iqos-terea-amber');
        $this->product('IQOS Terea Sienna', 'iqos-terea-sienna');
        $this->product('IQOS Terea Green', 'iqos-terea-green');

        $this->rebuild();

        // Unique long phrase survives, unambiguous.
        $this->assertTrue(LinkTarget::where('phrase', 'terea amber kazakhstan')->where('is_ambiguous', false)->exists());
        $this->assertTrue(LinkTarget::where('phrase', 'amber kazakhstan')->where('kind', 'phrase')->exists());

        // Token SET (sorted) exists for reordered matching.
        $this->assertTrue(LinkTarget::where('kind', 'set')->where('phrase', 'amber kazakhstan')->exists());

        // The catalog defines its own stopwords: shared-by-4 phrases die.
        $this->assertFalse(LinkTarget::where('phrase', 'iqos terea')->exists());

        // "terea amber" belongs to 2 targets → kept but ambiguous.
        $this->assertSame(2, LinkTarget::where('phrase', 'terea amber')->where('kind', 'phrase')->where('is_ambiguous', true)->count());
    }

    // ── Engine ──────────────────────────────────────────────────────

    public function test_engine_finds_exact_reordered_and_filler_mentions(): void
    {
        $target = $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');

        $source = $this->product('IQOS Terea Green', 'iqos-terea-green',
            '<p>Fans of the Kazakhstan Amber edition love its roasted depth.</p>'
            .'<h2>Amber Kazakhstan compared</h2>'
            .'<p>Some prefer the amber from Kazakhstan over the local version because the blend is bolder.</p>');

        $this->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($source);

        $suggestions = LinkSuggestion::where('source_id', $source->id)->get();

        // One suggestion per (source, target) — the best mention wins; the
        // heading mention must never be it (headings are ineligible).
        $this->assertCount(1, $suggestions);
        $s = $suggestions->first();
        $this->assertSame($target->id, $s->target_id);
        $this->assertNotSame('Amber Kazakhstan compared', $s->sentence);
        $this->assertTrue(
            in_array(mb_strtolower($s->anchor), ['kazakhstan amber', 'amber from kazakhstan'], true),
            "Unexpected anchor: {$s->anchor}",
        );
    }

    public function test_engine_skips_already_linked_targets_and_linked_paragraphs(): void
    {
        $target = $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');

        // Paragraph already contains a link — no suggestions inside it.
        $source = $this->product('IQOS Terea Green', 'iqos-terea-green',
            '<p>Try the <a href="/product/iqos-terea-sienna">Sienna</a> or the Kazakhstan Amber blend.</p>');

        $this->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($source);
        $this->assertSame(0, LinkSuggestion::where('source_id', $source->id)->count());

        // Source already links the target elsewhere → blocked via the index.
        $source2 = $this->product('IQOS Terea Sienna', 'iqos-terea-sienna',
            '<p>See <a href="/product/iqos-terea-amber-kazakhstan">this pack</a>.</p>'
            .'<p>The Kazakhstan Amber has a bolder finish.</p>');
        app(\App\Services\Seo\InternalLinkScanner::class)->scanSource($source2);
        app(LinkSuggestionEngine::class)->scanSource($source2);

        $this->assertSame(0, LinkSuggestion::where('source_id', $source2->id)->where('target_id', $target->id)->count());
    }

    public function test_ambiguous_phrase_resolved_by_paragraph_context(): void
    {
        $kz = $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');
        $this->product('IQOS Terea Amber', 'iqos-terea-amber');

        $author = User::factory()->create();
        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        $post = Post::create([
            'title' => 'Flavor Guide', 'slug' => 'flavor-guide', 'status' => 'published',
            'published_at' => now(), 'author_id' => $author->id, 'post_category_id' => $category->id,
            'content' => '<p>The Terea Amber sourced for the Kazakhstan market has a deeper roast profile.</p>',
        ]);

        $this->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($post);

        // "Terea Amber" is ambiguous — "Kazakhstan" in the paragraph votes KZ.
        $s = LinkSuggestion::where('source_id', $post->id)->first();
        $this->assertNotNull($s);
        $this->assertSame($kz->id, $s->target_id);
        $this->assertLessThanOrEqual(55, $s->score); // ambiguous cap
    }

    // ── Apply / undo ────────────────────────────────────────────────

    public function test_apply_wraps_the_exact_occurrence_and_undo_restores(): void
    {
        $target = $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');
        $source = $this->product('IQOS Terea Green', 'iqos-terea-green',
            '<p>The Kazakhstan Amber blend is the boldest of the line.</p>');

        $this->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($source);
        $suggestion = LinkSuggestion::where('source_id', $source->id)->firstOrFail();

        app(LinkApplier::class)->apply($suggestion);

        $html = $source->fresh()->description;
        $this->assertStringContainsString('<a href="/product/iqos-terea-amber-kazakhstan">Kazakhstan Amber</a>', $html);
        $this->assertSame('applied', $suggestion->fresh()->status);

        // The observer chain indexed the new link immediately.
        $this->assertSame(1, \App\Models\InternalLink::where('source_id', $source->id)->where('target_id', $target->id)->count());

        // Undo restores the original text and re-queues the suggestion.
        app(LinkApplier::class)->undo($suggestion->fresh());
        $this->assertStringNotContainsString('<a', $source->fresh()->description);
        $this->assertSame('pending', $suggestion->fresh()->status);
    }

    public function test_stale_suggestion_is_dismissed_not_guessed(): void
    {
        $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');
        $source = $this->product('IQOS Terea Green', 'iqos-terea-green',
            '<p>The Kazakhstan Amber blend is the boldest.</p>');

        $this->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($source);
        $suggestion = LinkSuggestion::where('source_id', $source->id)->firstOrFail();

        // Content changes so the mention disappears — apply must refuse.
        Product::whereKey($source->id)->toBase()->update(['description' => '<p>Totally rewritten.</p>']);

        try {
            app(LinkApplier::class)->apply($suggestion);
            $this->fail('Expected the applier to refuse stale content.');
        } catch (\RuntimeException) {
        }

        $this->assertSame('dismissed', $suggestion->fresh()->status);
        $this->assertStringNotContainsString('<a', $source->fresh()->description);
    }

    public function test_apply_refuses_when_the_page_already_links_the_target_with_any_anchor(): void
    {
        $target = $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');
        $source = $this->product('IQOS Terea Green', 'iqos-terea-green',
            '<p>The Kazakhstan Amber blend is the boldest.</p>');

        $this->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($source);
        $suggestion = LinkSuggestion::where('source_id', $source->id)->firstOrFail();

        // Admin manually links the SAME target with completely different
        // anchor text after the suggestion was created.
        $source->update(['description' => '<p>Read about <a href="/product/iqos-terea-amber-kazakhstan">this bolder blend</a>.</p><p>The Kazakhstan Amber blend is the boldest.</p>']);

        try {
            app(LinkApplier::class)->apply($suggestion->fresh() ?? $suggestion);
            $this->fail('Expected the duplicate guard to refuse.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already links', $e->getMessage());
        }

        // Exactly ONE link to the target remains — no duplicate inserted.
        $this->assertSame(1, substr_count($source->fresh()->description, 'iqos-terea-amber-kazakhstan'));
    }

    // ── Category as source + target (semantic SEO linking completeness) ──

    public function test_category_mentioned_in_a_post_is_a_suggestible_target(): void
    {
        $category = Category::create(['name' => 'IQOS Terea Yellow Collection', 'slug' => 'terea-yellow-collection', 'is_active' => true]);

        $author = User::factory()->create();
        $postCategory = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        $post = Post::create([
            'title' => 'Flavor Guide', 'slug' => 'flavor-guide', 'status' => 'published',
            'published_at' => now(), 'author_id' => $author->id, 'post_category_id' => $postCategory->id,
            'content' => '<p>Browse the full Terea Yellow Collection lineup before you decide.</p>',
        ]);

        $this->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($post);

        $suggestion = LinkSuggestion::where('source_id', $post->id)->where('target_type', Category::class)->first();
        $this->assertNotNull($suggestion);
        $this->assertSame($category->id, $suggestion->target_id);
    }

    public function test_category_content_block_is_scanned_as_a_source(): void
    {
        $target = $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');

        $category = Category::create([
            'name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true,
            'content_block' => '<p>Popular picks include the Kazakhstan Amber blend for a bolder profile.</p>',
        ]);

        $this->rebuild();
        $suggestions = app(LinkSuggestionEngine::class)->scanSource($category);

        $this->assertGreaterThan(0, $suggestions);
        $this->assertSame($target->id, LinkSuggestion::where('source_type', Category::class)->where('source_id', $category->id)->firstOrFail()->target_id);
    }

    public function test_scan_all_includes_categories(): void
    {
        $category = Category::create([
            'name' => 'Terea UAE', 'slug' => 'terea-uae', 'is_active' => true,
            'content_block' => '<p>Nothing to link here.</p>',
        ]);

        $this->rebuild();
        $stats = app(LinkSuggestionEngine::class)->scanAll();

        $this->assertGreaterThanOrEqual(1, $stats['sources']);
    }

    public function test_product_attribute_values_are_disambiguation_context(): void
    {
        $product = $this->product('IQOS Terea Turquoise', 'iqos-terea-turquoise');
        $attribute = \App\Models\Attribute::create(['name' => 'Flavor Family', 'slug' => 'flavor-family', 'type' => 'select']);
        $value = $attribute->values()->create(['value' => 'Menthol', 'slug' => 'menthol']);
        $product->attributeValues()->attach($value->id);

        $engine = new LinkSuggestionEngine;
        (new \ReflectionMethod($engine, 'loadIndexes'))->invoke($engine);

        $context = (new \ReflectionProperty($engine, 'context'))->getValue($engine);

        // "Menthol" is now a context token — "the menthol option" near an
        // ambiguous mention votes for this product.
        $this->assertArrayHasKey('menthol', $context[Product::class.'#'.$product->id]);
    }

    public function test_dismissed_suggestions_never_resurface(): void
    {
        $this->product('IQOS Terea Amber Kazakhstan', 'iqos-terea-amber-kazakhstan');
        $source = $this->product('IQOS Terea Green', 'iqos-terea-green',
            '<p>The Kazakhstan Amber blend is the boldest.</p>');

        $this->rebuild();
        $engine = app(LinkSuggestionEngine::class);
        $engine->scanSource($source);

        LinkSuggestion::where('source_id', $source->id)->update(['status' => 'dismissed']);

        $engine->scanSource($source);
        $this->assertSame(0, LinkSuggestion::where('source_id', $source->id)->where('status', 'pending')->count());
    }
}
