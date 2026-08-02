<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\AiImportItem;
use App\Models\ConnectedSite;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\Ai\BlogPublisher;
use App\Services\Network\NetworkTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkTargetsTest extends TestCase
{
    use RefreshDatabase;

    private function sites(): array
    {
        return [
            ConnectedSite::create(['name' => 'A', 'base_url' => 'https://a.example', 'api_key' => 'k1', 'api_secret' => 's1', 'is_active' => true]),
            ConnectedSite::create(['name' => 'B', 'base_url' => 'https://b.example', 'api_key' => 'k2', 'api_secret' => 's2', 'is_active' => true]),
            ConnectedSite::create(['name' => 'C', 'base_url' => 'https://c.example', 'api_key' => 'k3', 'api_secret' => 's3', 'is_active' => false]),
        ];
    }

    public function test_empty_selection_publishes_here_only(): void
    {
        foreach ([null, '', []] as $v) {
            $this->assertSame(['local' => true, 'sites' => []], NetworkTargets::resolve($v));
        }
    }

    public function test_local_token_publishes_here(): void
    {
        [$a] = $this->sites();

        $this->assertSame(['local' => true, 'sites' => []], NetworkTargets::resolve(['local']));
        $this->assertSame(['local' => true, 'sites' => []], NetworkTargets::resolve('self'));

        $r = NetworkTargets::resolve(['local', (string) $a->id]);
        $this->assertTrue($r['local']);
        $this->assertSame([$a->id], $r['sites']);
    }

    public function test_spokes_without_local_skip_this_site(): void
    {
        [$a, $b] = $this->sites();

        $r = NetworkTargets::resolve([(string) $a->id, (string) $b->id]);
        $this->assertFalse($r['local']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $r['sites']);
    }

    public function test_all_means_here_plus_every_active_spoke(): void
    {
        [$a, $b] = $this->sites();

        $r = NetworkTargets::resolve('all');
        $this->assertTrue($r['local']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $r['sites']); // inactive C excluded
    }

    public function test_inactive_or_unknown_only_falls_back_to_local(): void
    {
        [, , $c] = $this->sites();

        // Picking only an inactive/invalid spoke must never resolve to nowhere.
        $this->assertSame(['local' => true, 'sites' => []], NetworkTargets::resolve([(string) $c->id]));
        $this->assertSame(['local' => true, 'sites' => []], NetworkTargets::resolve('999'));
    }

    public function test_none_token_keeps_it_local_only(): void
    {
        $this->assertSame(['local' => true, 'sites' => []], NetworkTargets::resolve('none'));
    }

    public function test_site_key_attributes_cost_to_one_bucket(): void
    {
        [$a, $b] = $this->sites();

        $this->assertSame('local', NetworkTargets::siteKey('local'));
        $this->assertSame('local', NetworkTargets::siteKey(null));
        $this->assertSame((string) $a->id, NetworkTargets::siteKey((string) $a->id));
        $this->assertSame('shared', NetworkTargets::siteKey(['local', (string) $a->id]));
        $this->assertSame('shared', NetworkTargets::siteKey([(string) $a->id, (string) $b->id]));
        $this->assertSame('shared', NetworkTargets::siteKey('all'));
    }

    public function test_publisher_keeps_post_hidden_when_local_not_selected(): void
    {
        $author = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $cat = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);

        $batch = AiImportBatch::create([
            'name' => 'B', 'kind' => 'blog', 'prompt' => 'brief', 'csv_path' => '',
            'provider' => 'anthropic', 'user_id' => $author->id,
            'blog_category_id' => $cat->id, 'publish_mode' => 'publish',
        ]);
        $item = AiImportItem::create(['batch_id' => $batch->id, 'row' => ['name' => 'Spoke Only Post'], 'status' => 'writing']);

        $output = [
            'title' => 'Spoke Only Post',
            'description_html' => '<h2>Hi</h2><p>Body text here for the article.</p>',
            'short_description_html' => '<p>Summary.</p>',
        ];

        // localVisible=false → persisted but kept an unpublished draft.
        $post = (new BlogPublisher)->publish($item, $output, localVisible: false);
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);

        // localVisible=true (publish mode) → live.
        $item2 = AiImportItem::create(['batch_id' => $batch->id, 'row' => ['name' => 'Local Post'], 'status' => 'writing']);
        $post2 = (new BlogPublisher)->publish($item2, ['title' => 'Local Post', 'description_html' => '<p>Body text here.</p>'], localVisible: true);
        $this->assertSame('published', $post2->status);
        $this->assertNotNull($post2->published_at);
    }
}
