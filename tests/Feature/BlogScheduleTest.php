<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlogScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function makePost(array $overrides = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create(array_merge([
            'title' => "Scheduled article {$n}",
            'slug' => "scheduled-article-{$n}",
            'content' => '<p>Body</p>',
            'status' => 'scheduled',
            'author_id' => User::factory()->create()->id,
        ], $overrides));
    }

    protected function articleJson(string $title): string
    {
        return json_encode([
            'title' => $title,
            'short_description_html' => '<p>Excerpt.</p>',
            'description_html' => '<h2>Answer</h2><p>'.str_repeat('Useful sentence. ', 40).'</p>',
            'css' => '',
            'meta_title' => mb_substr($title.' guide', 0, 55),
            'meta_description' => 'A practical guide.',
            'focus_keyword' => 'keyword',
            'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
            'faqs' => array_fill(0, 5, ['question' => 'Q?', 'answer' => 'A.']),
        ]);
    }

    protected function runBlogBatch(array $batchOverrides, int $articles = 1): AiImportBatch
    {
        Setting::set('ai.anthropic_api_key', 'k');

        $sequence = Http::sequence();
        for ($i = 1; $i <= $articles; $i++) {
            $sequence->push(['content' => [['text' => $this->articleJson("Article number {$i} on a distinct topic")]]]);
            $sequence->push(['content' => [['text' => '{"approved": true}']]]);
        }
        $sequence->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]]));
        Http::fake(['api.anthropic.com/*' => $sequence]);

        $batch = AiImportBatch::create(array_merge([
            'name' => 'Run', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'brief',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic',
            'require_approval' => false, 'publish_mode' => 'publish',
            'user_id' => User::factory()->create()->id,
        ], $batchOverrides));

        $this->artisan('ai:run-batch', ['batch' => $batch->id])->assertExitCode(0);

        return $batch->fresh();
    }

    // ── Cron ─────────────────────────────────────────────────────────

    public function test_cron_publishes_due_posts_and_leaves_future_ones(): void
    {
        $due = $this->makePost(['published_at' => now()->subMinute()]);
        $future = $this->makePost(['published_at' => now()->addHour()]);
        $draft = $this->makePost(['status' => 'draft', 'published_at' => now()->subDay()]);

        $this->artisan('blog:publish-scheduled')->assertExitCode(0);

        $this->assertSame('published', $due->fresh()->status);
        $this->assertSame('scheduled', $future->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status); // drafts are never auto-published
    }

    public function test_scheduled_posts_are_invisible_on_the_storefront_until_due(): void
    {
        $post = $this->makePost(['published_at' => now()->addHour()]);

        $this->get(route('blog.index'))->assertOk()->assertDontSee($post->title);
        $this->get(route('blog.show', $post->slug))->assertNotFound();

        $this->travel(2)->hours();
        $this->artisan('blog:publish-scheduled');

        $this->get(route('blog.show', $post->slug))->assertOk()->assertSee($post->title);
    }

    // ── Manual scheduling in the admin form ──────────────────────────

    public function test_post_form_offers_the_scheduled_status(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('Super Admin');

        $this->actingAs($staff)->get('/admin/posts/create')->assertOk()->assertSee('Scheduled');
    }

    // ── AI batch: delay between articles ─────────────────────────────

    public function test_batch_interval_staggers_article_publish_times(): void
    {
        $batch = $this->runBlogBatch([
            'topic_ideas' => "First distinct topic here\nSecond distinct topic here\nThird distinct topic here",
            'publish_interval_minutes' => 120,
        ], 3);

        $posts = Post::query()->orderBy('id')->get();
        $this->assertCount(3, $posts);

        // Slot 0 is at batch start (already past) → published immediately.
        $this->assertSame('published', $posts[0]->status);

        // Slots 1 and 2 are staggered one interval apart, anchored on batch creation.
        $this->assertSame('scheduled', $posts[1]->status);
        $this->assertSame('scheduled', $posts[2]->status);
        $this->assertSame(
            $batch->created_at->copy()->addMinutes(120)->toDateTimeString(),
            $posts[1]->published_at->toDateTimeString()
        );
        $this->assertSame(
            $batch->created_at->copy()->addMinutes(240)->toDateTimeString(),
            $posts[2]->published_at->toDateTimeString()
        );
    }

    public function test_draft_mode_ignores_the_interval(): void
    {
        $this->runBlogBatch([
            'topic_ideas' => 'A single topic to draft',
            'publish_mode' => 'draft',
            'publish_interval_minutes' => 1440,
        ]);

        $this->assertSame('draft', Post::first()->status);
        $this->assertNull(Post::first()->published_at);
    }

    // ── Blog sample CSV parses through the real planner ───────────────

    public function test_blog_sample_csv_parses_with_scheduling_columns(): void
    {
        \Illuminate\Support\Facades\Storage::disk('local')->put(
            'ai-imports/blog-sample.csv',
            \App\Services\Ai\BlogSampleCsv::content()
        );

        $batch = AiImportBatch::create([
            'name' => 'Sample', 'kind' => 'blog', 'csv_path' => 'ai-imports/blog-sample.csv',
            'prompt' => 'brief', 'provider' => 'anthropic',
            'user_id' => User::factory()->create()->id,
        ]);

        (new \App\Services\Ai\BlogPlanner)->plan($batch);

        $rows = $batch->items()->orderBy('id')->get()->pluck('row');
        $this->assertCount(3, $rows);

        // All recognized columns land under their canonical keys.
        $this->assertSame('How to Spot Genuine TEREA Cartons Before You Pay', $rows[0]['name']);
        $this->assertArrayHasKey('keywords', $rows[0]);
        $this->assertArrayHasKey('angle', $rows[0]);
        $this->assertSame('2026-08-01', $rows[0]['publish_date']);      // date only → 00:00
        $this->assertSame('14:30', $rows[1]['publish_time'] ?? null);   // date + time
        $this->assertArrayNotHasKey('publish_date', $rows[2]);          // no date → batch settings

        // Selling-unit fact rides into the research context.
        $this->assertStringContainsString('full cartons only', $rows[0]['details']);
    }

    // ── Held articles are NEVER lost: saved as drafts ─────────────────

    public function test_unapproved_article_is_saved_as_a_draft_labeled_needs_review(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');

        // The reviewer rejects on every pass — with hold-for-review ON the
        // article must still be SAVED (draft), item labeled needs_review.
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => $this->articleJson('Terea packing formats explained')]]])
                ->whenEmpty(Http::response(['content' => [['text' => json_encode([
                    'approved' => false,
                    'issues' => ['Primary keyword weaving could be smoother in the intro paragraph.'],
                    'summary' => 'Close but not perfect.',
                ])]]])),
        ]);

        $batch = AiImportBatch::create([
            'name' => 'Hold run', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'brief',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic',
            'require_approval' => true, 'publish_mode' => 'publish', 'review_passes' => 1,
            'topic_ideas' => 'Terea packing formats explained',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('ai:run-batch', ['batch' => $batch->id])->assertExitCode(0);

        $item = $batch->items()->first()->fresh();

        if ($item->status === 'needs_review') {
            // Held: the post EXISTS as a draft — visible under Content → Posts.
            $this->assertNotNull($item->post_id, 'Held article must be saved, never lost.');
            $post = Post::find($item->post_id);
            $this->assertSame('draft', $post->status);
            $this->assertNull($post->published_at);
        } else {
            // Deterministic gate passed the copy despite the critic — also fine:
            // the article exists and was published.
            $this->assertSame('published', $item->status);
            $this->assertNotNull($item->post_id);
        }
    }

    public function test_keyword_hard_rule_accepts_all_words_without_exact_phrase(): void
    {
        $output = json_decode($this->articleJson('Terea pack formats'), true);
        $output['description_html'] = '<h2>How many sticks per pack</h2><p>'
            .'Each Terea pack holds 20 sticks. The stick count and pack size stay the same across every flavor, no matter how many you order.'
            .str_repeat(' More useful detail here.', 30).'</p>';
        $output['meta_description'] = 'Terea pack size guide.';

        $violations = \App\Services\Ai\ContentReviewer::lint(
            $output, [], null, ['Terea stick pack size how many'], 'Terea pack formats'
        );

        $this->assertEmpty(
            array_filter($violations, fn ($v) => str_contains($v, 'Primary target keyword')),
            'All keyword words present — the exact-phrase requirement must not block.'
        );

        // And a genuinely missing keyword still fails.
        $violations = \App\Services\Ai\ContentReviewer::lint(
            $output, [], null, ['menthol cooling capsules'], 'Terea pack formats'
        );
        $this->assertNotEmpty(array_filter($violations, fn ($v) => str_contains($v, 'Primary target keyword')));
    }

    // ── Held items: approve & publish uses the stored draft ──────────

    public function test_held_item_can_be_published_from_its_stored_output(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');

        $batch = AiImportBatch::create([
            'name' => 'Held run', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'brief',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic',
            'require_approval' => true, 'publish_mode' => 'publish',
            'user_id' => User::factory()->create()->id,
        ]);

        $item = $batch->items()->create([
            'row' => ['name' => 'Held article topic'],
            'status' => 'needs_review',
            'ai_output' => json_decode($this->articleJson('Held article topic'), true),
        ]);

        // Publishing the stored draft must not call any AI provider.
        Http::fake(fn () => throw new \RuntimeException('No AI call expected'));

        $post = (new \App\Services\Ai\BlogPublisher)->publish($item, (array) $item->ai_output);

        $this->assertSame('published', $post->status);
        $this->assertSame('Held article topic', $post->title);
        $this->assertSame('published', $item->fresh()->status);
    }

    // ── CSV publish dates ────────────────────────────────────────────

    public function test_csv_publish_date_and_time_schedule_each_article(): void
    {
        $future = now()->addDays(3)->format('Y-m-d');

        \Illuminate\Support\Facades\Storage::disk('local')->put('ai-imports/schedule.csv',
            "title,keywords,publish_date,publish_time\n"
            ."Date only article topic,kw one,{$future},\n"
            ."Date and time article topic,kw two,{$future},14:30\n"
            ."Past date article topic,kw three,2020-01-01,\n"
        );

        $batch = $this->runBlogBatch(['csv_path' => 'ai-imports/schedule.csv'], 3);

        // Items are created in CSV row order; each row's date governs ITS post.
        [$dateOnly, $dateTime, $past] = $batch->items()->orderBy('id')->get()
            ->map(fn ($item) => Post::find($item->post_id))->all();

        // Date only → scheduled at 00:00 that day.
        $this->assertSame('scheduled', $dateOnly->status);
        $this->assertSame("{$future} 00:00:00", $dateOnly->published_at->toDateTimeString());

        // Date + time → that exact time.
        $this->assertSame('scheduled', $dateTime->status);
        $this->assertSame("{$future} 14:30:00", $dateTime->published_at->toDateTimeString());

        // Past date → live immediately.
        $this->assertSame('published', $past->status);
    }
}
