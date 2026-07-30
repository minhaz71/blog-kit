<?php

namespace Tests\Feature;

use App\Jobs\FinalizeAiImportBatch;
use App\Jobs\StartAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiImportBatch;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Ai\InternalLinker;
use App\Services\Ai\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiProductPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeAnthropicWriting(): void
    {
        Setting::set('ai.anthropic_api_key', 'test-key');

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                // Writer response
                ->push(['content' => [['text' => json_encode([
                    'short_description_html' => '<p>A great blue widget.</p>',
                    'description_html' => '<div class="pd-hero"><h2>Blue Widget</h2><p>The best widget.</p></div>',
                    'css' => '.pd-hero { padding: 16px; }',
                    'suggested_price' => 19.99,
                    'meta_title' => 'Blue Widget — Buy Online',
                    'meta_description' => 'The best blue widget, shipped fast.',
                    'focus_keyword' => 'blue widget',
                    'secondary_keywords' => ['compact widget', 'widget for beginners', 'compact widget'],
                    'image_alt' => 'Blue widget on white background',
                    'image_title' => 'Blue Widget',
                    'image_caption' => 'The blue widget.',
                ])]]])
                // Reviewer approves on the first pass
                ->push(['content' => [['text' => '{"approved": true}']]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);
    }

    public function test_csv_batch_writes_reviews_and_publishes_a_product(): void
    {
        $this->fakeAnthropicWriting();
        Storage::disk('local')->put('ai-imports/test.csv', implode("\n", [
            'Product Name,Regular Price,Sale Price,Short Description,Specifications',
            'Blue Widget,25,20,A widget that is blue,"Color: Blue | Weight: 100g"',
            'Red Widget,30,25,A widget that is red,"Color: Red"',
        ]));

        $batch = AiImportBatch::create([
            'name' => 'Test batch',
            'csv_path' => 'ai-imports/test.csv',
            'prompt' => 'Write premium copy.',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic', 'require_approval' => false,
            'review_passes' => 3,
            'publish_mode' => 'publish',
            'price_mode' => 'csv',
        ]);

        (new StartAiImportBatch($batch))->handle();
        $batch->refresh();

        $this->assertSame(2, $batch->total_items);

        (new WriteAiProduct($batch->items->first()->id))->handle();
        $batch->refresh();

        $item = $batch->items()->first();
        $this->assertContains($item->status, ['published', 'linked'], $item->error ?? '');

        $product = $item->product;
        $this->assertNotNull($product);
        $this->assertSame('Blue Widget', $product->name);
        $this->assertSame(25.0, (float) $product->price);
        $this->assertSame(20.0, (float) $product->sale_price);
        $this->assertSame('published', $product->status);
        $this->assertStringContainsString('pd-hero', $product->description);
        // CSS separated into custom_css, not left inline.
        $this->assertStringContainsString('.pd-hero { padding: 16px; }', $product->custom_css);
        $this->assertSame(['Color' => 'Blue', 'Weight' => '100g'], $product->specifications);
        // Store style rule: em dashes in AI copy are rewritten to commas.
        $this->assertSame('Blue Widget, Buy Online', $product->seoMeta->title);
        // secondary_keywords persist (deduped) — they feed the Link Agent's
        // anchor vocabulary via LinkDictionary.
        $this->assertSame(['compact widget', 'widget for beginners'], $product->seoMeta->secondary_keywords);
        $this->assertGreaterThanOrEqual(1, $item->passes_done);
    }

    public function test_style_tags_from_ai_html_are_separated_automatically(): void
    {
        // AI returns <style> inside description_html instead of the css field.
        Setting::set('ai.anthropic_api_key', 'test-key');
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode([
                    'short_description_html' => '<p>x</p>',
                    'description_html' => '<style>.inline-block { margin: 4px; }</style><div class="inline-block">Copy</div>',
                    'css' => '',
                    'suggested_price' => 5,
                    'meta_title' => 'T', 'meta_description' => 'D', 'focus_keyword' => 'k',
                    'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
                ])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);
        Storage::disk('local')->put('ai-imports/t2.csv', "name,regular_price\nInline Thing,9\nOther Thing,4\n");

        $batch = AiImportBatch::create([
            'name' => 'T2', 'csv_path' => 'ai-imports/t2.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic', 'require_approval' => false, 'publish_mode' => 'publish',
        ]);

        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $product = $batch->items()->first()->product;
        $this->assertStringNotContainsString('<style', $product->description);
        $this->assertStringContainsString('.inline-block { margin: 4px; }', $product->custom_css);
    }

    public function test_audit_keeps_catalog_links_and_unwraps_invalid_ones(): void
    {
        $b = Product::create(['name' => 'Beta Pack', 'slug' => 'beta-pack', 'type' => 'simple', 'price' => 12, 'status' => 'published',
            'description' => '<p>No links.</p>']);
        $a = Product::create(['name' => 'Alpha Kit', 'slug' => 'alpha-kit', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>Bolder than <a href="'.$b->url().'">Beta Pack</a>, unlike '
                .'<a href="'.route('product.show', 'invented-thing').'">this invented one</a> or '
                .'<a href="'.route('product.show', 'alpha-kit').'">itself</a>.</p>']);

        // Self URL is in the catalog too (every batch product is) — it must still unwrap.
        $stats = (new InternalLinker)->audit($a, [$b->url(), route('product.show', 'alpha-kit')]);

        $this->assertSame(['kept' => 1, 'unwrapped' => 2], $stats);
        $fresh = $a->fresh()->description;
        // Catalog link survives; invented URL and self-link unwrap to plain text.
        $this->assertStringContainsString('<a href="'.$b->url().'">Beta Pack</a>', $fresh);
        $this->assertStringNotContainsString('invented-thing', $fresh);
        $this->assertStringContainsString('this invented one', $fresh);
        $this->assertSame(1, substr_count($fresh, '<a '));
    }

    public function test_finalize_unwraps_links_to_products_that_never_went_live(): void
    {
        Storage::disk('local')->put('ai-imports/t3.csv', "name,regular_price\nGamma One,5\nGamma Two,6\n");
        $batch = AiImportBatch::create([
            'name' => 'T3', 'csv_path' => 'ai-imports/t3.csv', 'prompt' => 'p', 'provider' => 'anthropic', 'reviewer_provider' => 'anthropic', 'require_approval' => false,
        ]);

        $deadUrl = route('product.show', 'gamma-two');
        $product = Product::create(['name' => 'Gamma One', 'slug' => 'gamma-one', 'type' => 'simple', 'price' => 5,
            'status' => 'published', 'description' => '<p>Compare with <a href="'.$deadUrl.'">Gamma Two</a> before choosing.</p>']);

        $batch->items()->create(['row' => ['name' => 'Gamma One'], 'reserved_slug' => 'gamma-one', 'product_id' => $product->id, 'status' => 'published']);
        // Gamma Two failed — its reserved URL was in the catalog but never went live.
        $batch->items()->create(['row' => ['name' => 'Gamma Two'], 'reserved_slug' => 'gamma-two', 'status' => 'failed']);

        (new FinalizeAiImportBatch($batch))->handle();

        $this->assertSame('completed', $batch->fresh()->status);
        $fresh = $product->fresh()->description;
        $this->assertStringNotContainsString($deadUrl, $fresh);
        $this->assertStringContainsString('Compare with Gamma Two before choosing.', strip_tags($fresh));
    }

    public function test_missing_api_key_fails_the_item_gracefully(): void
    {
        Storage::disk('local')->put('ai-imports/t4.csv', "name,regular_price\nNo Key,5\nAlso No Key,6\n");
        $batch = AiImportBatch::create([
            'name' => 'T4', 'csv_path' => 'ai-imports/t4.csv', 'prompt' => 'p', 'provider' => 'openai',
        ]);

        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $item = $batch->items()->first();
        $this->assertSame('failed', $item->status);
        $this->assertStringContainsString('No API key', $item->error);
    }
}
