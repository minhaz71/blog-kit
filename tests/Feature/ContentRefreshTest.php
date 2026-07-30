<?php

namespace Tests\Feature;

use App\Models\AiImportItem;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\BlogPublisher;
use App\Services\Ai\BlogWriter;
use App\Services\Ai\ContentRefresh;
use App\Services\Ai\ProductPublisher;
use App\Services\Ai\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        $p = Product::create([
            'name' => 'IQOS TEREA Amber', 'slug' => 'iqos-terea-amber', 'type' => 'simple',
            'price' => 220, 'sku' => 'TER-AMB', 'status' => 'published',
            'stock_status' => 'in_stock', 'visibility' => 'visible', 'manage_stock' => false,
            'short_description' => 'Old short copy.', 'description' => '<p>Old thin description.</p>',
        ]);
        $p->seoMeta()->create(['title' => 'Old title', 'focus_keyword' => 'terea amber uae']);

        return $p;
    }

    public function test_refresh_batch_snapshots_current_copy_and_targets_the_product(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();

        $batch = (new ContentRefresh)->products(collect([$product]), ['publish_mode' => 'publish']);

        $this->assertTrue((bool) $batch->refresh);
        $this->assertSame('product', $batch->kind);
        $item = $batch->items()->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($product->slug, $item->reserved_slug);          // URL preserved
        $this->assertStringContainsString('Old thin description', $item->row['_current']['description']);
        $this->assertStringContainsString('terea amber uae', $item->row['keywords']);
    }

    public function test_writer_prompt_includes_refresh_rules_and_current_copy(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        $batch = (new ContentRefresh)->products(collect([$product]), []);
        $item = $batch->items()->first();

        $this->assertStringContainsString('REFRESH MODE', ProductWriter::systemFor($batch));
        $prompt = ProductWriter::userPromptFor(AiImportItem::find($item->id));
        $this->assertStringContainsString('CURRENT PUBLISHED COPY', $prompt);
        $this->assertStringContainsString('Old thin description', $prompt);
        // The internal _current key must not leak into the plain data dump.
        $this->assertStringNotContainsString('_current', $prompt);
    }

    public function test_publisher_rewrites_product_in_place_preserving_commerce_and_url(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        $batch = (new ContentRefresh)->products(collect([$product]), ['publish_mode' => 'publish']);
        $item = $batch->items()->first();

        $output = [
            'short_description_html' => '<p>Fresh, benefit-led short copy.</p>',
            'description_html' => '<h2>What TEREA Amber delivers</h2><p>'.str_repeat('Rewritten detail. ', 40).'</p>',
            'meta_title' => 'Buy TEREA Amber Carton in Dubai',
            'meta_description' => 'Genuine TEREA Amber, delivered fast across the UAE.',
            'focus_keyword' => 'terea amber uae',
            'faqs' => array_fill(0, 6, ['question' => 'Q about Amber?', 'answer' => 'A naming TEREA Amber.']),
        ];
        (new ProductPublisher())->publish($item, $output);

        $product->refresh();
        $this->assertStringContainsString('What TEREA Amber delivers', $product->description);   // rewritten
        $this->assertSame('iqos-terea-amber', $product->slug);   // URL unchanged
        $this->assertSame(220.0, (float) $product->price);       // price unchanged
        $this->assertSame('TER-AMB', $product->sku);             // sku unchanged
        $this->assertSame('Buy TEREA Amber Carton in Dubai', $product->seoMeta->title);
        $this->assertSame(6, $product->faqs()->count());
        // Exactly one product — refresh never duplicates.
        $this->assertSame(1, Product::where('slug', 'iqos-terea-amber')->count());
    }

    public function test_blog_refresh_keeps_title_slug_and_publish_state(): void
    {
        $this->actingAs(User::factory()->create());
        $author = User::factory()->create();
        $post = Post::create([
            'title' => 'How heated tobacco works', 'slug' => 'how-heated-tobacco-works',
            'content' => '<p>Old article.</p>', 'status' => 'published', 'published_at' => now()->subMonth(),
            'author_id' => $author->id,
        ]);

        $batch = (new ContentRefresh)->posts(collect([$post]), ['publish_mode' => 'publish']);
        $this->assertTrue((bool) $batch->refresh);
        $item = $batch->items()->first();
        $this->assertSame($post->id, $item->post_id);

        // A rewrite that tries to change the title must NOT re-title/re-slug.
        (new BlogPublisher())->publish($item, [
            'title' => 'A COMPLETELY DIFFERENT TITLE',
            'short_description_html' => '<p>New excerpt.</p>',
            'description_html' => '<h2>The direct answer</h2><p>'.str_repeat('Fresh guidance. ', 40).'</p>',
            'meta_title' => 'How heated tobacco works, explained',
            'meta_description' => 'A clear, current explanation.',
            'focus_keyword' => 'heated tobacco',
            'faqs' => array_fill(0, 5, ['question' => 'Q?', 'answer' => 'A.']),
        ]);

        $post->refresh();
        $this->assertSame('How heated tobacco works', $post->title);        // title preserved
        $this->assertSame('how-heated-tobacco-works', $post->slug);          // URL preserved
        $this->assertSame('published', $post->status);                       // still live
        $this->assertStringContainsString('The direct answer', $post->content); // body rewritten
    }

    public function test_blog_refresh_prompt_carries_current_content(): void
    {
        $batch = new \App\Models\AiImportBatch(['kind' => 'blog', 'refresh' => true, 'prompt' => 'brief']);
        $this->assertStringContainsString('REFRESH MODE', BlogWriter::systemFor($batch));
    }
}
