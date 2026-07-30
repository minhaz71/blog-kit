<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogTopicIdeaResource;
use App\Models\AiImportBatch;
use App\Models\BlogTopicIdea;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\BlogPlanner;
use App\Services\Ai\BlogPublisher;
use App\Services\Ai\BlogWriter;
use App\Services\Ai\FunnelPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FunnelBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $name = 'TEREA Amber'): Product
    {
        return Product::create([
            'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
    }

    protected function idea(array $overrides = []): BlogTopicIdea
    {
        $title = $overrides['title'] ?? 'How heated tobacco actually works';

        return BlogTopicIdea::create(array_merge([
            'title' => $title,
            'fingerprint' => BlogTopicIdea::fingerprint($title),
            'cluster' => 'Heated tobacco basics',
            'role' => 'spoke',
            'funnel_stage' => 'top',
            'primary_keyword' => 'how heated tobacco works',
            'secondary_keywords' => ['heated tobacco explained'],
            'pain_point' => 'Confusion about what heating instead of burning means',
            'search_query' => 'how does heated tobacco work',
            'angle' => 'Explain the technology honestly without selling.',
            'outline' => ['What heating means', 'Device basics', 'Common myths', 'Honest limitations'],
            'link_targets' => [route('product.show', 'terea-amber')],
            'verified_rounds' => 3,
            'status' => 'waiting',
        ], $overrides));
    }

    // ── Canonical guard (deterministic) ──────────────────────────────

    public function test_similarity_catches_reworded_titles(): void
    {
        $this->assertGreaterThanOrEqual(
            FunnelPlanner::SIMILARITY_LIMIT,
            BlogTopicIdea::similarity(
                'How to clean an IQOS ILUMA properly',
                'Cleaning your IQOS ILUMA: how to do it properly'
            )
        );

        $this->assertLessThan(
            FunnelPlanner::SIMILARITY_LIMIT,
            BlogTopicIdea::similarity(
                'How to clean an IQOS ILUMA properly',
                'TEREA flavor guide for first-time buyers'
            )
        );
    }

    public function test_deterministic_gate_drops_canonical_risks_and_broken_ideas(): void
    {
        $this->product();
        Post::create([
            'title' => 'How to clean an IQOS ILUMA properly', 'slug' => 'clean-iluma', 'content' => 'x',
            'status' => 'published', 'author_id' => User::factory()->create()->id,
        ]);

        $valid = [
            'title' => 'Why your TEREA sticks taste burnt',
            'funnel_stage' => 'top',
            'primary_keyword' => 'terea tastes burnt',
            'outline' => ['Cause 1', 'Cause 2', 'Fixes'],
            'link_targets' => [route('product.show', 'terea-amber')],
        ];

        [$passed, $rejected] = (new FunnelPlanner)->deterministicGate([
            $valid,
            // Near-duplicate of the existing post — canonical risk.
            $valid2 = ['title' => 'Properly cleaning an IQOS ILUMA: how to', 'funnel_stage' => 'top', 'primary_keyword' => 'clean iluma', 'outline' => ['a', 'b', 'c'], 'link_targets' => [route('product.show', 'terea-amber')]],
            // Invented link target — no longer fatal: the idea survives with
            // its invalid target dropped (the link agent links it at write time).
            ['title' => 'Choosing your first heated tobacco device', 'funnel_stage' => 'middle', 'primary_keyword' => 'first device', 'outline' => ['a', 'b', 'c'], 'link_targets' => ['https://example.com/not-ours']],
            // Wrong funnel stage.
            ['title' => 'A totally different topic entirely here', 'funnel_stage' => 'bottom', 'primary_keyword' => 'k', 'outline' => ['a', 'b', 'c'], 'link_targets' => [route('product.show', 'terea-amber')]],
        ], ['How to clean an IQOS ILUMA properly']);

        $this->assertCount(2, $passed);
        $titles = array_column($passed, 'title');
        $this->assertContains('Why your TEREA sticks taste burnt', $titles);
        // The invented-target idea passes but its bad URL is stripped.
        $survivor = collect($passed)->firstWhere('title', 'Choosing your first heated tobacco device');
        $this->assertNotNull($survivor);
        $this->assertSame([], $survivor['link_targets']);

        // Only the canonical-risk title and the wrong-funnel-stage one are dropped.
        $this->assertCount(2, $rejected);
        $this->assertStringContainsString('would compete with it in search', $rejected[0]['reject_reason']);
    }

    public function test_gate_also_checks_the_waiting_area_not_just_posts(): void
    {
        $this->product();
        $this->idea(['title' => 'Why your TEREA sticks taste burnt sometimes']);

        [$passed, $rejected] = (new FunnelPlanner)->deterministicGate([[
            'title' => 'Why TEREA sticks sometimes taste burnt',
            'funnel_stage' => 'top',
            'primary_keyword' => 'terea tastes burnt',
            'outline' => ['a', 'b', 'c'],
            'link_targets' => [route('product.show', 'terea-amber')],
        ]], []);

        $this->assertCount(0, $passed);
        $this->assertCount(1, $rejected);
    }

    // ── Cached link catalog ──────────────────────────────────────────

    public function test_link_catalog_loads_from_cache_and_refreshes_on_content_change(): void
    {
        $this->product();

        $first = (new BlogPlanner)->buildLinkCatalog('ecommerce');
        $this->assertNotEmpty($first);

        // Second call must be served from cache — no queries needed.
        \Illuminate\Support\Facades\DB::enableQueryLog();
        (new BlogPlanner)->buildLinkCatalog('ecommerce');
        $this->assertSame([], \Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Content change bumps the version → fresh catalog with the new product.
        $this->product('TEREA Sienna');
        $fresh = (new BlogPlanner)->buildLinkCatalog('ecommerce');
        $this->assertContains(route('product.show', 'terea-sienna'), array_column($fresh, 'url'));
    }

    // ── Send to writer ───────────────────────────────────────────────

    public function test_sending_ideas_creates_a_funnel_batch_with_prebuilt_items(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $this->product();
        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);
        $staff = User::factory()->create(['is_active' => true]);
        $this->actingAs($staff);

        $ideaA = $this->idea();
        $ideaB = $this->idea(['title' => 'TEREA flavor strength compared by line', 'funnel_stage' => 'middle', 'fingerprint' => BlogTopicIdea::fingerprint('TEREA flavor strength compared by line')]);

        $batch = BlogTopicIdeaResource::sendToWriter(
            BlogTopicIdea::query()->get(),
            ['provider' => 'anthropic', 'blog_category_id' => $category->id, 'publish_mode' => 'publish']
        );

        $this->assertSame('blog', $batch->kind);
        $this->assertNotNull($batch->funnel_rounds); // marks the funnel toolkit on
        $this->assertSame(2, $batch->total_items);
        $this->assertNotEmpty($batch->link_catalog);

        $row = $batch->items()->first()->row;
        $this->assertSame($ideaA->title, $row['name']);
        $this->assertSame('top', $row['funnel_stage']);
        $this->assertStringContainsString('how heated tobacco works', $row['keywords']);
        $this->assertStringContainsString('What heating means', $row['outline']);
        $this->assertSame((string) $ideaA->id, $row['idea_id']);
        $this->assertStringContainsString(route('product.show', 'terea-amber'), $row['required_links']);

        $this->assertSame('queued', $ideaA->fresh()->status);
        $this->assertSame($batch->id, $ideaB->fresh()->writer_batch_id);
    }

    public function test_single_idea_send_accepts_a_plain_collection(): void
    {
        // Regression: the row action wraps one record in collect([...])
        // (Support collection) — sendToWriter must accept it, not just the
        // Eloquent collection the bulk action passes.
        Setting::set('ai.anthropic_api_key', 'k');
        $this->product();
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $idea = $this->idea();

        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => '{"approved": true}']]])]);

        $batch = BlogTopicIdeaResource::sendToWriter(collect([$idea]), ['provider' => 'anthropic']);

        $this->assertSame(1, $batch->total_items);
        $this->assertSame('queued', $idea->fresh()->status);
    }

    public function test_funnel_batches_get_funnel_rules_and_every_blog_batch_gets_the_toolkit(): void
    {
        $normal = new AiImportBatch(['prompt' => 'brief', 'kind' => 'blog']);
        $funnel = new AiImportBatch(['prompt' => 'brief', 'kind' => 'blog', 'funnel_rounds' => 3]);

        // Funnel research brief: funnel batches only.
        $this->assertStringNotContainsString('FUNNEL ARTICLE RULES', BlogWriter::systemFor($normal));
        $this->assertStringContainsString('FUNNEL ARTICLE RULES', BlogWriter::systemFor($funnel));

        // Design toolkit (bd-* classes): every blog batch.
        $this->assertStringContainsString('bd-callout', BlogWriter::systemFor($normal));
        $this->assertStringContainsString('bd-callout', BlogWriter::systemFor($funnel));

        // Semantic SEO rules: every blog batch, right after the article rulebook.
        $this->assertStringContainsString('SEMANTIC SEO (mandatory', BlogWriter::systemFor($normal));
    }

    public function test_blog_page_wraps_content_in_the_tag_design_layer(): void
    {
        $post = Post::create([
            'title' => 'Design layer check', 'slug' => 'design-layer-check',
            'content' => '<h2>Section</h2><p>Body</p>', 'status' => 'published',
            'published_at' => now(), 'author_id' => User::factory()->create()->id,
        ]);

        $this->get(route('blog.show', $post->slug))->assertOk()->assertSee('bd-article');
    }

    // ── Class whitelist enforcement ──────────────────────────────────

    public function test_publisher_strips_classes_outside_the_vocabulary(): void
    {
        $html = '<div class="bd-callout">keep</div>'
            .'<div class="hacked-class bd-tip">mixed</div>'
            .'<p class="text-red-500" id="x" style="color:red">strip all</p>'
            .'<ol class="bd-steps"><li>ok</li></ol>';

        $clean = (new BlogPublisher)->enforceClassWhitelist($html);

        $this->assertStringContainsString('<div class="bd-callout">', $clean);
        $this->assertStringContainsString('<div class="bd-tip">', $clean);
        $this->assertStringContainsString('<ol class="bd-steps">', $clean);
        $this->assertStringNotContainsString('hacked-class', $clean);
        $this->assertStringNotContainsString('text-red-500', $clean);
        $this->assertStringNotContainsString('id="x"', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringContainsString('<p>strip all</p>', $clean);
    }

    // ── End-to-end: idea → written post, waiting area closed out ─────

    public function test_written_funnel_article_marks_the_idea_written(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $this->product();
        $staff = User::factory()->create(['is_active' => true]);
        $this->actingAs($staff);
        $idea = $this->idea();

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode([
                    'title' => $idea->title,
                    'short_description_html' => '<p>Excerpt.</p>',
                    'description_html' => '<h2>The direct answer</h2><div class="bd-callout junk">Insight</div><p>'.str_repeat('Specific useful sentence. ', 40).'</p>',
                    'css' => '',
                    'meta_title' => 'How heated tobacco works, explained',
                    'meta_description' => 'A practical explanation.',
                    'focus_keyword' => 'how heated tobacco works',
                    'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
                    'faqs' => array_fill(0, 5, ['question' => 'Q?', 'answer' => 'A.']),
                ])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);

        $batch = BlogTopicIdeaResource::sendToWriter(
            BlogTopicIdea::query()->get(),
            ['provider' => 'anthropic', 'publish_mode' => 'publish']
        );

        // Jobs were dispatched synchronously in tests (sync queue).
        $idea->refresh();
        $this->assertSame('written', $idea->status);
        $this->assertNotNull($idea->post_id);

        $post = Post::find($idea->post_id);
        $this->assertSame('published', $post->status);
        $this->assertStringContainsString('class="bd-callout"', $post->content);
        $this->assertStringNotContainsString('junk', $post->content);
    }

    // ── Comparison content: role=comparison item → post carries the pair ─

    public function test_comparison_item_persists_compared_product_ids_on_the_post(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $a = $this->product('TEREA Yellow');
        $b = $this->product('TEREA Bronze');
        $staff = User::factory()->create(['is_active' => true]);
        $this->actingAs($staff);

        $idea = $this->idea([
            'title' => 'TEREA Yellow vs Bronze: Which Should You Buy',
            'fingerprint' => BlogTopicIdea::fingerprint('TEREA Yellow vs Bronze: Which Should You Buy'),
            'role' => 'comparison',
            'funnel_stage' => 'middle',
            'primary_keyword' => 'terea yellow vs bronze',
            'secondary_keywords' => [],
            'link_targets' => [route('product.show', $a->slug)],
            'compared_product_ids' => [$a->id, $b->id],
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode([
                    'title' => $idea->title,
                    'short_description_html' => '<p>Excerpt.</p>',
                    'description_html' => '<h2>Flavor comparison</h2><p>'.str_repeat('Specific useful sentence. ', 40).'</p>',
                    'css' => '',
                    'meta_title' => 'TEREA Yellow vs Bronze compared',
                    'meta_description' => 'A practical comparison.',
                    'focus_keyword' => 'terea yellow vs bronze',
                    'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
                    'faqs' => array_fill(0, 5, ['question' => 'Q?', 'answer' => 'A.']),
                ])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);

        BlogTopicIdeaResource::sendToWriter(
            BlogTopicIdea::query()->get(),
            ['provider' => 'anthropic', 'publish_mode' => 'publish']
        );

        $idea->refresh();
        $post = Post::find($idea->post_id);

        $this->assertNotNull($post);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $post->compared_product_ids);
    }

    public function test_comparison_batch_gets_comparison_rules_in_the_system_prompt(): void
    {
        $a = $this->product('TEREA Yellow');
        $b = $this->product('TEREA Bronze');
        $batch = AiImportBatch::create(['name' => 'B1', 'prompt' => 'brief', 'kind' => 'blog', 'csv_path' => '']);
        $batch->items()->create(['row' => ['name' => 'x', 'role' => 'comparison', 'compared_product_ids' => [$a->id, $b->id]], 'status' => 'pending']);

        $this->assertStringContainsString('COMPARISON ARTICLE RULES', BlogWriter::systemFor($batch->fresh()));

        $normalBatch = AiImportBatch::create(['name' => 'B2', 'prompt' => 'brief', 'kind' => 'blog', 'csv_path' => '']);
        $normalBatch->items()->create(['row' => ['name' => 'x'], 'status' => 'pending']);

        $this->assertStringNotContainsString('COMPARISON ARTICLE RULES', BlogWriter::systemFor($normalBatch->fresh()));
    }

    public function test_cluster_design_receives_the_facet_taxonomy(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $this->product();
        \App\Models\Attribute::create(['name' => 'Cooling Level', 'slug' => 'cooling-level', 'type' => 'select'])
            ->values()->create(['value' => 'Strong', 'slug' => 'strong']);

        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode([
                'clusters' => [['name' => 'Cooling Guide', 'theme' => 't', 'pillar_focus' => 'p', 'bofu_targets' => []]],
            ])]]]),
        ]);

        $batch = AiImportBatch::create(['name' => 'B', 'prompt' => 'brief', 'kind' => 'blog', 'csv_path' => '']);

        $planner = new \App\Services\Ai\FunnelPlanner;
        $clusters = (new \ReflectionMethod($planner, 'designClusters'))->invoke(
            $planner, \App\Services\Ai\LlmClient::for('anthropic'), $batch, ['pain_points' => []], 24,
        );

        $this->assertNotEmpty($clusters);

        // The cluster designer sees the real facet axes, not just names.
        Http::assertSent(fn ($request) => str_contains($request->body(), 'PRODUCT FACET TAXONOMY')
            && str_contains($request->body(), 'cooling_level'));
    }

    // ── Full research run: command → waiting area ────────────────────

    public function test_funnel_research_run_fills_the_waiting_area(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $this->product();
        Post::create([
            'title' => 'How heated tobacco devices work', 'slug' => 'existing', 'content' => 'x',
            'status' => 'published', 'author_id' => User::factory()->create()->id,
        ]);

        $url = route('product.show', 'terea-amber');
        $ideas = collect(range(1, 12))->map(fn ($i) => [
            'title' => "Unique funnel topic number {$i} about flavor {$i}",
            'cluster' => $i % 2 ? 'Flavors' : 'Devices',
            'role' => $i === 1 ? 'pillar' : 'spoke',
            'funnel_stage' => $i % 2 ? 'top' : 'middle',
            'primary_keyword' => "keyword {$i}",
            'secondary_keywords' => ["variant {$i}"],
            'pain_point' => "Pain {$i}",
            'search_query' => "query {$i}",
            'audience_need' => "Need {$i}",
            'angle' => "Angle {$i}",
            'outline' => ['Section A', 'Section B', 'Section C', 'Section D'],
            'link_targets' => [$url],
        ])->all();

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode(['store_summary' => 'UAE TEREA store', 'audiences' => ['adult IQOS users'], 'pain_points' => [['pain' => 'burnt taste', 'affects' => 'new users', 'product_area' => 'TEREA']], 'queries' => ['why terea tastes burnt'], 'needs' => ['confidence choosing flavors']])]]])
                ->push(['content' => [['text' => json_encode(['clusters' => [['name' => 'Flavors', 'theme' => 'flavor guidance', 'pillar_focus' => 'choosing flavors', 'bofu_targets' => [$url]], ['name' => 'Devices', 'theme' => 'device help', 'pillar_focus' => 'device care', 'bofu_targets' => [$url]]]])]]])
                ->push(['content' => [['text' => json_encode(['ideas' => $ideas])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"verdicts": []}']]])),
        ]);

        $batch = AiImportBatch::create([
            'kind' => 'blog_ideas', 'csv_path' => '', 'name' => 'Research run',
            'prompt' => 'UAE TEREA store brief', 'provider' => 'anthropic',
            'topic_count' => 10, 'funnel_rounds' => 3, 'status' => 'pending',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('ai:funnel-research', ['batch' => $batch->id])->assertExitCode(0);

        $this->assertSame(10, BlogTopicIdea::query()->where('status', 'waiting')->count()); // capped at target
        $this->assertSame('completed', $batch->fresh()->status);

        $idea = BlogTopicIdea::query()->first();
        $this->assertContains($idea->funnel_stage, ['top', 'middle']);
        $this->assertNotEmpty($idea->outline);
        $this->assertSame([$url], $idea->link_targets);
        // 12 valid candidates cover the target of 10 in round 1, so the run
        // stops early (1 verified round) instead of burning rounds 2-3.
        $this->assertSame(1, $idea->verified_rounds);
    }

    public function test_research_stops_early_once_the_target_is_reached(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $this->product();

        $url = route('product.show', 'terea-amber');
        // 14 valid candidates comfortably cover the target of 10 in round 1.
        $ideas = collect(range(1, 14))->map(fn ($i) => [
            'title' => "Distinct funnel idea number {$i} on flavor {$i}",
            'cluster' => 'Flavors', 'role' => 'spoke', 'funnel_stage' => 'top',
            'primary_keyword' => "kw {$i}", 'secondary_keywords' => [], 'outline' => ['A', 'B', 'C'],
            'link_targets' => [$url],
        ])->all();

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode(['store_summary' => 's', 'audiences' => [], 'pain_points' => [], 'queries' => [], 'needs' => []])]]])
                ->push(['content' => [['text' => json_encode(['clusters' => [['name' => 'Flavors', 'theme' => 't', 'pillar_focus' => 'p', 'bofu_targets' => [$url]]]])]]])
                ->push(['content' => [['text' => json_encode(['ideas' => $ideas])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"verdicts": []}']]])),
        ]);

        $batch = AiImportBatch::create([
            'kind' => 'blog_ideas', 'csv_path' => '', 'name' => 'Early-stop run',
            'prompt' => 'brief', 'provider' => 'anthropic',
            'topic_count' => 10, 'funnel_rounds' => 5, 'status' => 'pending',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('ai:funnel-research', ['batch' => $batch->id])->assertExitCode(0);

        // Target (10) reached in round 1 with 14 candidates → stopped early.
        $this->assertSame(10, BlogTopicIdea::query()->where('status', 'waiting')->count());
        $this->assertSame(1, BlogTopicIdea::query()->max('verified_rounds'));
        $this->assertTrue(
            $batch->activityLogs()->where('message', 'like', '%stopping early%')->exists()
        );
    }

    // ── Waiting area page ────────────────────────────────────────────

    public function test_waiting_area_page_renders_with_generate_action(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('Super Admin');
        $this->idea();

        $this->actingAs($staff)->get('/admin/blog-topic-ideas')
            ->assertOk()
            ->assertSee('Generate ideas')
            ->assertSee('How heated tobacco actually works');
    }

    public function test_research_runs_panel_surfaces_a_failed_run_and_its_reason(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('Super Admin');
        $this->actingAs($staff);

        $failed = AiImportBatch::create([
            'kind' => 'blog_ideas', 'csv_path' => '', 'name' => 'Failed research run',
            'prompt' => 'brief', 'provider' => 'anthropic', 'status' => 'failed',
            'error' => 'Research produced no titles that survived verification.',
            'user_id' => $staff->id,
        ]);

        // A normal blog batch must never appear in the funnel runs panel.
        AiImportBatch::create([
            'kind' => 'blog', 'csv_path' => '', 'name' => 'Ordinary article batch',
            'prompt' => 'brief', 'provider' => 'anthropic', 'status' => 'completed',
            'user_id' => $staff->id,
        ]);

        \Livewire\Livewire::test(\App\Filament\Widgets\FunnelResearchRunsWidget::class)
            ->assertCanSeeTableRecords([$failed])
            ->assertSee('Failed research run')
            ->assertSee('survived verification')
            ->assertDontSee('Ordinary article batch');
    }

    // ── Tolerant link-target matching + stall detection ──────────────

    public function test_gate_recovers_link_targets_given_in_a_different_url_shape(): void
    {
        $this->product(); // seeds the catalog with /product/terea-amber

        // The model returned the target as a bare path with a trailing slash
        // instead of the exact absolute catalog URL — it must still map.
        $path = parse_url(route('product.show', 'terea-amber'), PHP_URL_PATH).'/';

        [$passed] = (new FunnelPlanner)->deterministicGate([[
            'title' => 'An honest guide to choosing your TEREA flavor',
            'funnel_stage' => 'top',
            'primary_keyword' => 'choosing terea flavor',
            'outline' => ['a', 'b', 'c'],
            'link_targets' => [$path],
        ]], []);

        $this->assertCount(1, $passed);
        $this->assertSame([route('product.show', 'terea-amber')], $passed[0]['link_targets']);
    }

    public function test_a_processing_run_with_no_recent_activity_reads_as_stalled(): void
    {
        $fresh = new AiImportBatch(['status' => 'processing']);
        $fresh->updated_at = now()->subMinute();
        $this->assertFalse($fresh->isStalled());

        $dead = new AiImportBatch(['status' => 'processing']);
        $dead->updated_at = now()->subMinutes(30);
        $this->assertTrue($dead->isStalled());

        $done = new AiImportBatch(['status' => 'completed']);
        $done->updated_at = now()->subHours(2);
        $this->assertFalse($done->isStalled());
    }
}
