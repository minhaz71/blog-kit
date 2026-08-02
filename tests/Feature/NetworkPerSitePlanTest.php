<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\ConnectedSite;
use App\Models\NetworkRemotePost;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\Ai\BlogPlanner;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetworkPerSitePlanTest extends TestCase
{
    use RefreshDatabase;

    /** Anthropic-shaped completion carrying a planner topics payload. */
    private function planResponse(array $titles): array
    {
        $topics = array_map(fn (string $t) => [
            'title' => $t, 'role' => 'spoke',
            'primary_keyword' => strtolower($t), 'secondary_keywords' => [],
            'angle' => 'Answer the intent.', 'outline' => ['A', 'B'],
        ], $titles);

        return ['content' => [['text' => json_encode(['topics' => $topics])]]];
    }

    public function test_niche_mode_plans_a_distinct_cluster_per_site(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $author = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $cat = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);

        // Local already has this article → must be deduped out of the local cluster.
        Post::create([
            'title' => 'Local Existing Guide', 'slug' => 'local-existing', 'content' => 'x',
            'status' => 'published', 'author_id' => $author->id, 'post_category_id' => $cat->id,
        ]);

        // A connected spoke, with one mirrored post → deduped out of its cluster.
        $spoke = ConnectedSite::create([
            'name' => 'Spoke One', 'base_url' => 'https://spoke.example',
            'api_key' => 'k1', 'api_secret' => 's1', 'is_active' => true,
        ]);
        NetworkRemotePost::create([
            'site_id' => $spoke->id, 'remote_post_id' => 7,
            'title' => 'Spoke Existing Guide', 'status' => 'published',
        ]);

        // Targets resolve in order: local first, then the spoke.
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->planResponse(['Local Existing Guide', 'Local Fresh A', 'Local Fresh B']))
                ->push($this->planResponse(['Spoke Existing Guide', 'Spoke Fresh A', 'Spoke Fresh B'])),
        ]);

        $batch = AiImportBatch::create([
            'name' => 'Cluster', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'brief',
            'provider' => 'anthropic', 'user_id' => $author->id, 'blog_category_id' => $cat->id,
            'niche' => 'home composting', 'topic_count' => 3,
            'network_site_ids' => ['local', (string) $spoke->id],
        ]);

        (new BlogPlanner)->plan($batch);

        $rows = $batch->items()->get()->pluck('row');
        $byTitle = $rows->keyBy('name');

        // Fresh topics kept and stamped with the right site; existing ones dropped.
        $this->assertTrue($byTitle->has('Local Fresh A'));
        $this->assertSame('local', $byTitle['Local Fresh A']['site_ids']);
        $this->assertSame((string) $spoke->id, $byTitle['Spoke Fresh A']['site_ids']);

        $this->assertFalse($byTitle->has('Local Existing Guide'));  // deduped vs local posts
        $this->assertFalse($byTitle->has('Spoke Existing Guide'));  // deduped vs spoke mirror

        // 2 fresh per site = 4 items total.
        $this->assertSame(4, $batch->items()->count());

        // Per-site cost attribution key is stamped on every item.
        $local = $batch->items()->where('site_key', 'local')->count();
        $spokeItems = $batch->items()->where('site_key', (string) $spoke->id)->count();
        $this->assertSame(2, $local);
        $this->assertSame(2, $spokeItems);
    }

    public function test_local_only_niche_still_stamps_local(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $author = User::create(['name' => 'B', 'email' => 'b@example.com', 'password' => bcrypt('x'), 'is_active' => true]);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->planResponse(['Only Local A', 'Only Local B'])),
        ]);

        $batch = AiImportBatch::create([
            'name' => 'Solo', 'kind' => 'blog', 'csv_path' => '', 'prompt' => 'brief',
            'provider' => 'anthropic', 'user_id' => $author->id,
            'niche' => 'apartment gardening', 'topic_count' => 2,
            'network_site_ids' => ['local'],
        ]);

        (new BlogPlanner)->plan($batch);

        $this->assertSame(2, $batch->items()->count());
        $this->assertTrue($batch->items()->get()->pluck('row')->every(fn ($r) => ($r['site_ids'] ?? null) === 'local'));
    }
}
