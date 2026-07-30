<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\AiImportItem;
use App\Models\BlogTopicIdea;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\BlogPlanner;
use App\Services\Ai\BlogWriter;
use App\Services\Ai\ContentReviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAgentUpgradesTest extends TestCase
{
    use RefreshDatabase;

    // ── #1 Ranking-conflict guard in the regular planner ──────────────

    public function test_planner_drops_titles_competing_with_posts_products_and_categories(): void
    {
        Post::create([
            'title' => 'How to clean an IQOS ILUMA properly', 'slug' => 'clean-iluma', 'content' => 'x',
            'status' => 'published', 'author_id' => User::factory()->create()->id,
        ]);
        Product::create([
            'name' => 'IQOS TEREA Amber Carton UAE', 'slug' => 'terea-amber-carton', 'type' => 'simple',
            'price' => 220, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
        Category::create(['name' => 'Terea Japan Edition', 'slug' => 'terea-japan', 'is_active' => true]);

        $batch = AiImportBatch::create([
            'name' => 'Guard run', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'brief',
            'provider' => 'anthropic', 'user_id' => User::factory()->create()->id,
            // 1: reworded near-dup of the post; 2: clone of a product page;
            // 3: clone of a category page; 4: genuinely fresh.
            'topic_ideas' => implode("\n", [
                'Cleaning your IQOS ILUMA properly: how to',
                'IQOS TEREA Amber carton in UAE',
                'Terea Japan edition',
                'Why heated tobacco devices need regular maintenance schedules',
            ]),
        ]);

        (new BlogPlanner)->plan($batch);

        $titles = $batch->items()->pluck('row')->pluck('name')->all();

        $this->assertSame(['Why heated tobacco devices need regular maintenance schedules'], $titles);
        $this->assertDatabaseHas('ai_activity_logs', ['batch_id' => $batch->id, 'level' => 'warning']);
    }

    // ── #3 Reviewer ranges + direct-or-indirect keyword ───────────────

    public function test_meta_lengths_never_block_and_clamp_to_the_owner_ranges(): void
    {
        $base = [
            'description_html' => '<h2>Topic</h2><p>'.str_repeat('Specific useful sentence. ', 30).'</p>',
            'faqs' => array_fill(0, 6, ['question' => 'Q?', 'answer' => 'A direct answer naming the topic.']),
        ];

        // Even wildly over-length meta fields produce ZERO lint violations —
        // meta lengths never block content; the clamp fixes them instead.
        $violations = ContentReviewer::lint($base + [
            'meta_title' => str_repeat('a', 90),
            'meta_description' => str_repeat('b', 220),
        ], [], null, [], 'Page');
        $this->assertEmpty(array_filter($violations, fn ($v) => str_contains($v, 'meta_')));

        // Clamp ceilings: title ≤63, description ≤164 (owner range 150-164).
        $clamped = ContentReviewer::clampMetaLengths([
            'meta_title' => 'Word '.str_repeat('word ', 20).'end',
            'meta_description' => 'Desc '.str_repeat('sentence words here ', 12).'end',
        ]);
        $this->assertLessThanOrEqual(63, mb_strlen($clamped['meta_title']));
        $this->assertLessThanOrEqual(164, mb_strlen($clamped['meta_description']));
        // A 162-char description survives untouched (inside the window).
        $keep = str_repeat('c', 162);
        $this->assertSame($keep, ContentReviewer::clampMetaLengths(['meta_description' => $keep])['meta_description']);
    }

    public function test_keyword_counts_when_implemented_indirectly(): void
    {
        // "terea stick pack size how many" — meaningful words: terea, stick,
        // pack, size. Copy uses three of four (75%), no exact phrase.
        $this->assertTrue(ContentReviewer::keywordCoveredIndirectly(
            'terea stick pack size how many',
            'each terea carton holds ten packs and the sticks stay sealed'
        ));

        // Stemming: "cleaning" in the keyword matches "clean" in the copy.
        $this->assertTrue(ContentReviewer::keywordCoveredIndirectly(
            'iluma cleaning guide',
            'how to clean your iluma device, a practical guide'
        ));

        // Genuinely absent topic still fails.
        $this->assertFalse(ContentReviewer::keywordCoveredIndirectly(
            'menthol cooling capsules',
            'each terea carton holds ten packs and the sticks stay sealed'
        ));
    }

    // ── #5 Role/topic-adaptive length ──────────────────────────────────

    public function test_length_directive_scales_by_role_and_reaches_the_prompt(): void
    {
        $this->assertStringContainsString('1800-2500', BlogWriter::lengthDirective(['role' => 'pillar']));
        $this->assertStringContainsString('900-1500', BlogWriter::lengthDirective(['role' => 'spoke']));
        $this->assertStringContainsString('700-1800', BlogWriter::lengthDirective(['role' => 'article']));
        $this->assertStringContainsString('sized to the topic', BlogWriter::lengthDirective([]));

        $batch = AiImportBatch::create([
            'name' => 'Len', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'brief',
            'provider' => 'anthropic', 'user_id' => User::factory()->create()->id,
        ]);
        $item = $batch->items()->create([
            'row' => ['name' => 'Pillar article', 'role' => 'pillar'],
            'status' => 'pending',
        ]);

        $prompt = BlogWriter::userPromptFor(AiImportItem::find($item->id));

        $this->assertStringContainsString('TARGET LENGTH: 1800-2500', $prompt);
        $this->assertStringContainsString('cluster PILLAR', $prompt);
    }
}
