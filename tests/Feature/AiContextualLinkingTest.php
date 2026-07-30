<?php

namespace Tests\Feature;

use App\Jobs\StartAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiImportBatch;
use App\Models\Product;
use App\Services\Ai\ContentReviewer;
use App\Services\Ai\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiContextualLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function parsedBatch(): AiImportBatch
    {
        Queue::fake([WriteAiProduct::class]);

        Storage::disk('local')->put('ai-imports/link.csv', "name,regular_price\nTEREA Amber,32\nTEREA Sienna,32\n");

        $batch = AiImportBatch::create([
            'name' => 'Link batch', 'csv_path' => 'ai-imports/link.csv', 'prompt' => 'p', 'provider' => 'anthropic',
        ]);

        (new StartAiImportBatch($batch))->handle();

        return $batch->fresh();
    }

    public function test_non_utf8_csv_is_sanitized_and_does_not_break(): void
    {
        \Illuminate\Support\Facades\Queue::fake([WriteAiProduct::class]);

        // Windows-1252 bytes: 0x92 = curly apostrophe, 0xE9 = é — invalid UTF-8.
        $rows = "name,regular_price\n"
            ."IQOS TEREA \x92Sun\xE9 Pearl,32\n"
            ."IQOS TEREA Amber,30\n";
        \Illuminate\Support\Facades\Storage::disk('local')->put('ai-imports/bad.csv', $rows);

        $batch = AiImportBatch::create([
            'name' => 'Bad encoding', 'csv_path' => 'ai-imports/bad.csv', 'prompt' => 'p', 'provider' => 'anthropic',
        ]);

        // Must not throw a JsonEncodingException.
        (new StartAiImportBatch($batch))->handle();
        $batch->refresh();

        $this->assertSame(2, $batch->total_items);
        $first = $batch->items()->first();
        // Row is stored and valid UTF-8 (survives JSON encode/decode).
        $this->assertTrue(mb_check_encoding($first->row['name'], 'UTF-8'));
        $this->assertStringContainsString('Pearl', $first->row['name']);
        $this->assertIsString(json_encode($first->row));
    }

    public function test_parse_reserves_slugs_and_builds_link_catalog(): void
    {
        Product::create(['name' => 'Existing Device', 'slug' => 'existing-device', 'type' => 'simple', 'price' => 89, 'status' => 'published']);

        $batch = $this->parsedBatch();

        // Every item gets a unique slug reserved before any writing starts.
        $slugs = $batch->items()->pluck('reserved_slug')->all();
        $this->assertSame(['terea-amber', 'terea-sienna'], $slugs);

        // Catalog = batch products (live URLs known up front) + existing store products.
        $catalog = collect($batch->link_catalog);
        $this->assertCount(3, $catalog);
        $this->assertContains(route('product.show', 'terea-amber'), $catalog->pluck('url'));
        $this->assertContains(route('product.show', 'existing-device'), $catalog->pluck('url'));
    }

    public function test_catalog_includes_categories_and_guides_for_semantic_linking(): void
    {
        $category = \App\Models\Category::create(['name' => 'TEREA Japan', 'slug' => 'terea-japan', 'is_active' => true]);
        $post = \App\Models\Post::create([
            'title' => 'TEREA Flavor Guide', 'slug' => 'terea-flavor-guide', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => now()->subDay(),
            'author_id' => \App\Models\User::factory()->create()->id,
        ]);

        $batch = $this->parsedBatch();
        $catalog = collect($batch->link_catalog);

        // Product copy can now link UP to its category and OUT to guides.
        $this->assertContains($category->url(), $catalog->pluck('url'));
        $this->assertContains($post->url(), $catalog->pluck('url'));
        $this->assertSame('category', $catalog->firstWhere('url', $category->url())['type'] ?? null);
        $this->assertSame('guide', $catalog->firstWhere('url', $post->url())['type'] ?? null);

        // Labels + the semantic-flow rule reach the cached system prompt.
        $system = ProductWriter::systemFor($batch);
        $this->assertStringContainsString('TEREA Japan (category)', $system);
        $this->assertStringContainsString('TEREA Flavor Guide (guide)', $system);
        $this->assertStringContainsString('SEMANTIC LINK FLOW', $system);
    }

    public function test_slug_collisions_are_resolved_at_parse_time(): void
    {
        Product::create(['name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple', 'price' => 32, 'status' => 'published']);

        $batch = $this->parsedBatch();

        $this->assertSame('terea-amber-2', $batch->items()->first()->reserved_slug);
    }

    public function test_catalog_and_linking_rules_enter_the_cached_system_prompt_once(): void
    {
        $batch = $this->parsedBatch();
        $system = ProductWriter::systemFor($batch);

        $this->assertStringContainsString('INTERNAL LINKING', $system);
        $this->assertStringContainsString('NEVER a link list', $system);
        $this->assertStringContainsString('Never link this product to itself', $system);

        // Each catalog URL appears exactly once — sent one time per batch, cached.
        $url = route('product.show', 'terea-amber');
        $this->assertSame(1, substr_count($system, $url));

        // Byte-identical across items → provider prompt cache always hits.
        $this->assertSame($system, ProductWriter::systemFor($batch->fresh()));
    }

    public function test_search_engine_and_aio_rules_are_in_the_system_prompt(): void
    {
        $batch = $this->parsedBatch();
        $system = ProductWriter::systemFor($batch);

        $this->assertStringContainsString('SEARCH & AI-ANSWER OPTIMIZATION', $system);
        $this->assertStringContainsString('PageRank flows through internal links', $system);
        $this->assertStringContainsString('E-E-A-T', $system);
        $this->assertStringContainsString('BING (Bing Webmaster Guidelines)', $system);
        $this->assertStringContainsString('AI ANSWER ENGINES / AIO', $system);
        $this->assertStringContainsString('Google AI Overviews, ChatGPT, Perplexity, Bing Copilot', $system);
        $this->assertStringContainsString('40-60 word answer', $system);
        $this->assertStringContainsString('UGC-STYLE AUTHENTICITY', $system);
        $this->assertStringContainsString('NEVER fabricate reviews', $system);
    }

    public function test_later_items_get_a_compacted_batch_memory_digest(): void
    {
        $batch = $this->parsedBatch();
        [$first, $second] = $batch->items()->orderBy('id')->get();

        // First item: nothing written yet → no digest.
        $this->assertStringNotContainsString('BATCH MEMORY', ProductWriter::userPromptFor($first));

        $first->update(['ai_output' => [
            'description_html' => '<h2>How TEREA Amber Actually Tastes</h2><p>The first draw opens with roasted warmth.</p>',
        ]]);

        // Second item: compact digest of used headings/openers — not the full output.
        $prompt = ProductWriter::userPromptFor($second->fresh());
        $this->assertStringContainsString('BATCH MEMORY', $prompt);
        $this->assertStringContainsString('How TEREA Amber Actually Tastes', $prompt);
        $this->assertStringNotContainsString('<h2>', $prompt); // digest is plain text, not replayed HTML
    }

    public function test_lint_flags_bad_anchors_self_links_and_invented_urls(): void
    {
        $selfUrl = route('product.show', 'my-widget');
        $allowedUrl = route('product.show', 'other-widget');

        $violations = implode("\n", ContentReviewer::lint([
            'description_html' => '<p><a href="'.$allowedUrl.'">click here</a> and '
                .'<a href="'.$selfUrl.'">My Widget</a> and '
                .'<a href="'.route('product.show', 'invented').'">Invented</a>.</p>',
            'short_description_html' => '<p>Widget for tests, 20 per pack.</p>',
            'meta_title' => 'My Widget', 'meta_description' => 'A widget.',
            'faqs' => array_fill(0, 5, ['question' => 'Q?', 'answer' => 'A.']),
        ], [$allowedUrl], $selfUrl));

        $this->assertStringContainsString('Generic anchor text "click here"', $violations);
        $this->assertStringContainsString('links the product to itself', $violations);
        $this->assertStringContainsString('Invented internal URL', $violations);
    }

    public function test_lint_flags_a_link_dump_at_the_end(): void
    {
        $urls = [route('product.show', 'a'), route('product.show', 'b')];

        $violations = implode("\n", ContentReviewer::lint([
            'description_html' => '<p>Fine copy about the product.</p>'
                .'<ul><li><a href="'.$urls[0].'">A</a></li><li><a href="'.$urls[1].'">B</a></li></ul>',
            'short_description_html' => '<p>x</p>',
            'meta_title' => 'T', 'meta_description' => 'D',
            'faqs' => array_fill(0, 5, ['question' => 'Q?', 'answer' => 'A.']),
        ], $urls, null));

        $this->assertStringContainsString('dumped in a list at the end', $violations);
    }

    public function test_publisher_uses_the_reserved_slug_so_catalog_links_resolve(): void
    {
        Storage::disk('local')->put('ai-imports/slug.csv', "name,regular_price\nSlug Widget,10\nOther,5\n");
        $batch = AiImportBatch::create([
            'name' => 'S', 'csv_path' => 'ai-imports/slug.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'publish_mode' => 'publish',
        ]);
        $item = $batch->items()->create(['row' => ['name' => 'Slug Widget', 'regular_price' => '10'], 'reserved_slug' => 'slug-widget']);

        $product = (new \App\Services\Ai\ProductPublisher(new \App\Services\Ai\DriveImageFetcher))->publish($item, [
            'short_description_html' => '<p>x</p>', 'description_html' => '<p>y</p>', 'css' => '',
            'meta_title' => 'T', 'meta_description' => 'D', 'focus_keyword' => 'k',
            'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c', 'faqs' => [],
        ]);

        $this->assertSame('slug-widget', $product->slug);
        $this->assertSame(route('product.show', 'slug-widget'), $product->url());
    }
}
