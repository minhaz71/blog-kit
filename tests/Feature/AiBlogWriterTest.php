<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiBlogWriterTest extends TestCase
{
    use RefreshDatabase;

    protected function articleJson(string $title): string
    {
        return json_encode([
            'title' => $title,
            'short_description_html' => '<p>A practical guide readers can act on.</p>',
            'description_html' => '<h2>The direct answer</h2><p>'.str_repeat('Useful specific guidance sentence. ', 30).'</p>',
            'css' => '',
            'meta_title' => mb_substr($title, 0, 55),
            'meta_description' => 'A practical, specific guide for UAE IQOS users.',
            'focus_keyword' => 'terea guide',
            'image_alt' => 'alt', 'image_title' => 't', 'image_caption' => 'c',
            'faqs' => array_fill(0, 5, ['question' => 'Q about the topic?', 'answer' => 'A direct answer naming the topic.']),
        ]);
    }

    protected function makeBatch(array $overrides = []): AiImportBatch
    {
        Setting::set('ai.anthropic_api_key', 'k');

        return AiImportBatch::create(array_merge([
            'name' => 'Blog run', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'UAE TEREA store brief',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic',
            'require_approval' => false, 'publish_mode' => 'publish',
            'user_id' => User::factory()->create()->id,
        ], $overrides));
    }

    public function test_blog_system_prompt_carries_the_fact_vocabulary_when_attributes_exist(): void
    {
        $batch = $this->makeBatch();

        // Without a taxonomy, no vocabulary block (nothing to ground on).
        $this->assertStringNotContainsString('PRODUCT FACT VOCABULARY', \App\Services\Ai\BlogWriter::systemFor($batch));

        \App\Models\Attribute::create(['name' => 'Cooling Level', 'slug' => 'cooling-level', 'type' => 'select'])
            ->values()->create(['value' => 'Strong', 'slug' => 'strong']);

        $system = \App\Services\Ai\BlogWriter::systemFor($batch->fresh());
        $this->assertStringContainsString('PRODUCT FACT VOCABULARY', $system);
        $this->assertStringContainsString('cooling_level: Strong', $system);
    }

    public function test_writes_every_article_from_given_titles_in_one_run(): void
    {
        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => $this->articleJson('How to clean an IQOS ILUMA')]]])
                ->push(['content' => [['text' => '{"approved": true}']]])
                ->push(['content' => [['text' => $this->articleJson('TEREA Amber vs Sienna compared')]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);

        $batch = $this->makeBatch([
            'topic_ideas' => "How to clean an IQOS ILUMA\nTEREA Amber vs Sienna compared",
            'blog_category_id' => $category->id,
        ]);

        $this->artisan('ai:run-batch', ['batch' => $batch->id])->assertExitCode(0);

        $batch->refresh();
        $this->assertSame(2, $batch->total_items);
        $this->assertSame(2, $batch->done_items);
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, Post::count());

        $post = Post::first();
        $this->assertSame('published', $post->status);
        $this->assertSame($category->id, $post->post_category_id);
        $this->assertNotNull($post->published_at);
        $this->assertGreaterThanOrEqual(1, $post->reading_time);
        $this->assertSame(5, $post->allFaqs()->count());
        $this->assertNotNull($post->seoMeta);
        $this->assertSame('terea guide', $post->seoMeta->focus_keyword);
    }

    public function test_ai_plans_a_cluster_from_the_niche(): void
    {
        $plan = json_encode(['topics' => [
            ['title' => 'The complete UAE guide to IQOS ILUMA', 'role' => 'pillar',
                'primary_keyword' => 'iqos iluma uae', 'secondary_keywords' => ['buy iluma dubai'],
                'angle' => 'Everything a new ILUMA owner needs.', 'outline' => ['What it is', 'Devices compared', 'Where to buy']],
            ['title' => 'Why does my ILUMA blink red?', 'role' => 'spoke',
                'primary_keyword' => 'iluma blinking red', 'secondary_keywords' => [],
                'angle' => 'Troubleshooting from real cases.', 'outline' => ['Causes', 'Fixes']],
        ]]);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => $plan]]])
                ->push(['content' => [['text' => $this->articleJson('The complete UAE guide to IQOS ILUMA')]]])
                ->push(['content' => [['text' => '{"approved": true}']]])
                ->push(['content' => [['text' => $this->articleJson('Why does my ILUMA blink red?')]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);

        $batch = $this->makeBatch(['niche' => 'IQOS ILUMA in the UAE', 'topic_count' => 2]);

        $this->artisan('ai:run-batch', ['batch' => $batch->id])->assertExitCode(0);

        $batch->refresh();
        $this->assertSame(2, $batch->total_items);
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, Post::count());

        // The planner mapped keywords into the item rows (drives the lint + writer).
        $this->assertStringContainsString('iqos iluma uae', (string) $batch->items()->first()->row['keywords']);
        // A link catalog was built for contextual internal linking.
        $this->assertIsArray($batch->link_catalog);
    }

    public function test_csv_bulk_briefs_drive_the_batch(): void
    {
        \Illuminate\Support\Facades\Storage::disk('local')->put('ai-imports/blog.csv',
            "title,keywords,country,details\n"
            ."Best TEREA flavor for menthol lovers,\"terea menthol uae, black menthol\",United Arab Emirates,Compare all menthol variants honestly\n"
            ."ILUMA battery life explained,iluma battery life,United Arab Emirates,Cover charging habits\n");

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => $this->articleJson('Best TEREA flavor for menthol lovers')]]])
                ->push(['content' => [['text' => '{"approved": true}']]])
                ->push(['content' => [['text' => $this->articleJson('ILUMA battery life explained')]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);

        // CSV wins even when a niche is also set — no planning call is made.
        $batch = $this->makeBatch(['csv_path' => 'ai-imports/blog.csv', 'niche' => 'ignored']);

        $this->artisan('ai:run-batch', ['batch' => $batch->id])->assertExitCode(0);

        $batch->refresh();
        $this->assertSame(2, $batch->total_items);
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, Post::count());

        // The row's keywords column drives the keyword directive + lint.
        $item = $batch->items()->first();
        $this->assertSame('terea menthol uae, black menthol', $item->row['keywords']);
        $this->assertSame('United Arab Emirates', $item->row['country']);
    }

    public function test_link_catalog_follows_the_site_mode(): void
    {
        \App\Models\Product::create(['name' => 'Widget', 'slug' => 'widget', 'type' => 'simple', 'price' => 10, 'status' => 'published', 'stock_status' => 'in_stock']);
        \App\Models\Category::create(['name' => 'Sticks', 'slug' => 'sticks', 'is_active' => true]);
        PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        Post::create(['author_id' => User::factory()->create()->id, 'title' => 'Old post', 'slug' => 'old-post', 'status' => 'published', 'published_at' => now()]);

        $planner = new \App\Services\Ai\BlogPlanner;

        $ecommerce = collect($planner->buildLinkCatalog('ecommerce'));
        $this->assertTrue($ecommerce->contains(fn ($e) => str_contains($e['url'], '/product/widget')));
        $this->assertTrue($ecommerce->contains(fn ($e) => str_contains($e['url'], '/category/sticks')));
        $this->assertTrue($ecommerce->contains(fn ($e) => str_contains($e['url'], '/blog/old-post')));
        $this->assertTrue($ecommerce->contains(fn ($e) => str_contains($e['url'], '/blog/category/guides')));
        $this->assertTrue($ecommerce->contains(fn ($e) => rtrim($e['url'], '/') === rtrim(url('/'), '/')));

        $blogOnly = collect($planner->buildLinkCatalog('blog_only'));
        $this->assertFalse($blogOnly->contains(fn ($e) => str_contains($e['url'], '/product/')));
        $this->assertFalse($blogOnly->contains(fn ($e) => rtrim($e['url'], '/') === rtrim(url('/'), '/')));
        $this->assertTrue($blogOnly->contains(fn ($e) => str_contains($e['url'], '/blog/old-post')));
        $this->assertTrue($blogOnly->contains(fn ($e) => str_contains($e['url'], '/blog/category/guides')));
    }

    public function test_draft_mode_saves_unpublished_posts_and_skips_duplicates(): void
    {
        Post::create(['author_id' => User::factory()->create()->id, 'title' => 'Existing guide', 'slug' => 'existing-guide', 'status' => 'published']);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => $this->articleJson('A brand new topic')]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);

        $batch = $this->makeBatch([
            'publish_mode' => 'draft',
            'topic_ideas' => "Existing guide\nA brand new topic", // first line duplicates an existing post
        ]);

        $this->artisan('ai:run-batch', ['batch' => $batch->id])->assertExitCode(0);

        $batch->refresh();
        $this->assertSame(1, $batch->total_items); // duplicate title was skipped at planning
        $post = Post::where('title', 'A brand new topic')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);
    }
}
