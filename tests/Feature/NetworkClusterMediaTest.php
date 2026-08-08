<?php

namespace Tests\Feature;

use App\Models\ContentCluster;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\Network\NetworkPostIngestor;
use App\Services\Network\NetworkPostPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The push payload must carry EVERYTHING a spoke needs: cluster/funnel metadata,
 * the category mother→sub chain, and in-body images. This drives a hub post
 * through for() → apply() and asserts the spoke copy is complete.
 */
class NetworkClusterMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_carries_cluster_category_tree_and_inline_images(): void
    {
        Storage::fake('public');
        $author = User::factory()->create();

        // A 1×1 gif on the hub's disk, referenced inline in the body.
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        Storage::disk('public')->put('uploads/pic.gif', $gif);

        // Category tree: Devices → Pods.
        $mother = PostCategory::create(['name' => 'Devices', 'slug' => 'devices']);
        $sub = PostCategory::create(['name' => 'Pods', 'slug' => 'pods', 'parent_id' => $mother->id]);
        $cluster = ContentCluster::create(['name' => 'Pod Systems', 'slug' => 'pod-systems']);

        $post = Post::create([
            'title' => 'Best pods', 'slug' => 'best-pods',
            'content' => '<p>See <img src="/storage/uploads/pic.gif" alt="pic"> here.</p>',
            'status' => 'published', 'published_at' => now(), 'author_id' => $author->id,
            'post_category_id' => $sub->id, 'reading_time' => 1,
            'cluster' => 'Pod Systems', 'content_cluster_id' => $cluster->id,
            'content_role' => 'spoke', 'funnel_stage' => 'middle', 'primary_keyword' => 'best pods',
        ]);

        $payload = NetworkPostPayload::for($post);

        // Payload assertions.
        $this->assertSame('Pod Systems', $payload['content_meta']['cluster']);
        $this->assertSame('spoke', $payload['content_meta']['content_role']);
        $this->assertSame(['devices', 'pods'], collect($payload['category_path'])->pluck('slug')->all());
        $this->assertCount(1, $payload['inline_images']);
        $this->assertSame('/storage/uploads/pic.gif', $payload['inline_images'][0]['src']);

        // Simulate the spoke: fresh state, then ingest the payload.
        Post::query()->forceDelete();
        PostCategory::query()->delete();
        ContentCluster::query()->delete();
        Storage::disk('public')->delete('uploads/pic.gif'); // spoke doesn't have the hub file

        $spokePost = (new NetworkPostIngestor)->apply('hubkey', $payload);

        // Cluster/funnel landed.
        $this->assertSame('Pod Systems', $spokePost->cluster);
        $this->assertSame('spoke', $spokePost->content_role);
        $this->assertSame('middle', $spokePost->funnel_stage);
        $this->assertNotNull($spokePost->content_cluster_id);

        // Category tree rebuilt (Devices → Pods) on the spoke.
        $leaf = PostCategory::find($spokePost->post_category_id);
        $this->assertSame('pods', $leaf->slug);
        $this->assertNotNull($leaf->parent_id);
        $this->assertSame('devices', $leaf->parent->slug);

        // Inline image stored locally + URL rewritten off the hub path.
        $this->assertStringNotContainsString('/storage/uploads/pic.gif', $spokePost->content);
        $this->assertMatchesRegularExpression('#/storage/network/hubkey/inline/pic-[0-9a-f]{12}\.gif#', $spokePost->content);
        $stored = collect(Storage::disk('public')->allFiles('network/hubkey/inline'));
        $this->assertCount(1, $stored);
    }
}
