<?php

namespace Tests\Feature;

use App\Jobs\DeletePostFromSite;
use App\Jobs\PushPostToSite;
use App\Models\ConnectedSite;
use App\Models\NetworkPostLink;
use App\Models\Post;
use App\Models\User;
use App\Services\Network\NetworkIdentity;
use App\Services\Network\NetworkPostPayload;
use App\Services\Network\NetworkPuller;
use App\Services\Network\NetworkSignature;
use App\Services\Network\NetworkSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NetworkTwoWayTest extends TestCase
{
    use RefreshDatabase;

    protected function hubKey(): string
    {
        return NetworkIdentity::ensure()[0];
    }

    protected function signedDelete(string $path): \Illuminate\Testing\TestResponse
    {
        [$key, $secret] = NetworkIdentity::ensure();
        $headers = NetworkSignature::headers($key, $secret, 'DELETE', ltrim($path, '/'), '', 'n'.bin2hex(random_bytes(8)), time());
        $server = [];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $this->call('DELETE', '/'.ltrim($path, '/'), [], [], [], $server);
    }

    protected function makePost(string $networkOrigin): Post
    {
        $author = User::firstOrCreate(['email' => 'a@example.com'], ['name' => 'A', 'password' => bcrypt('x'), 'is_active' => true]);

        return Post::create([
            'author_id' => $author->id, 'title' => 'Networked', 'slug' => 'networked-'.uniqid(),
            'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now(), 'reading_time' => 1,
            'network_origin' => $networkOrigin,
        ]);
    }

    public function test_delete_removes_a_post_this_hub_manages(): void
    {
        $p = $this->makePost($this->hubKey().':5');

        $this->signedDelete('api/v1/network/posts/5')->assertOk()->assertJson(['ok' => true, 'deleted' => true]);
        $this->assertSoftDeleted('posts', ['id' => $p->id]);

        // Idempotent: deleting again reports nothing to delete.
        $this->signedDelete('api/v1/network/posts/5')->assertOk()->assertJson(['deleted' => false]);
    }

    public function test_delete_is_scoped_to_the_calling_hub(): void
    {
        $p = $this->makePost('someotherhub:5'); // managed by a different hub

        $this->signedDelete('api/v1/network/posts/5')->assertOk()->assertJson(['deleted' => false]);
        $this->assertDatabaseHas('posts', ['id' => $p->id, 'deleted_at' => null]);
    }

    public function test_pull_flags_conflict_when_spoke_diverges(): void
    {
        $author = User::create(['name' => 'A', 'email' => 'w@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $hubPost = Post::create(['author_id' => $author->id, 'title' => 'Hub Post', 'slug' => 'hub-post', 'content' => '<p>hi</p>', 'status' => 'published', 'published_at' => now(), 'reading_time' => 1]);
        $pushedHash = NetworkPostPayload::hash(NetworkPostPayload::for($hubPost));

        $site = ConnectedSite::create(['name' => 'S', 'base_url' => 'https://s.example', 'api_key' => 'k', 'api_secret' => 's', 'is_active' => true]);
        $link = NetworkPostLink::create([
            'post_id' => $hubPost->id, 'site_id' => $site->id, 'remote_post_id' => 100,
            'content_hash' => $pushedHash, 'status' => 'synced',
        ]);

        // The spoke reports a DIFFERENT hash → someone edited it on the spoke.
        Http::fake(['*/api/v1/network/posts*' => Http::response([
            'ok' => true, 'current_page' => 1, 'last_page' => 1, 'total' => 1,
            'data' => [['remote_post_id' => 100, 'title' => 'Hub Post (edited on spoke)', 'status' => 'published', 'updated_at' => now()->toIso8601String(), 'content_hash' => 'DIVERGED_HASH']],
        ], 200)]);

        (new NetworkPuller)->pull($site);

        $this->assertSame('conflict', $link->fresh()->status);
        $this->assertNotNull($link->fresh()->conflict_detected_at);
    }

    public function test_pull_keeps_synced_when_hashes_match(): void
    {
        $author = User::create(['name' => 'A', 'email' => 'w2@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $hubPost = Post::create(['author_id' => $author->id, 'title' => 'Hub Post', 'slug' => 'hub-post-2', 'content' => '<p>hi</p>', 'status' => 'published', 'published_at' => now(), 'reading_time' => 1]);
        $hash = NetworkPostPayload::hash(NetworkPostPayload::for($hubPost));

        $site = ConnectedSite::create(['name' => 'S', 'base_url' => 'https://s2.example', 'api_key' => 'k', 'api_secret' => 's', 'is_active' => true]);
        $link = NetworkPostLink::create(['post_id' => $hubPost->id, 'site_id' => $site->id, 'remote_post_id' => 101, 'content_hash' => $hash, 'status' => 'conflict']);

        Http::fake(['*/api/v1/network/posts*' => Http::response([
            'ok' => true, 'current_page' => 1, 'last_page' => 1, 'total' => 1,
            'data' => [['remote_post_id' => 101, 'title' => 'Hub Post', 'status' => 'published', 'updated_at' => now()->toIso8601String(), 'content_hash' => $hash]],
        ], 200)]);

        (new NetworkPuller)->pull($site);

        $this->assertSame('synced', $link->fresh()->status);
    }

    public function test_resync_and_remove_dispatch_jobs(): void
    {
        Queue::fake();

        $author = User::create(['name' => 'A', 'email' => 'w3@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $post = Post::create(['author_id' => $author->id, 'title' => 'P', 'slug' => 'p-3', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now(), 'reading_time' => 1]);
        $site = ConnectedSite::create(['name' => 'S', 'base_url' => 'https://s3.example', 'api_key' => 'k', 'api_secret' => 's', 'is_active' => true]);
        NetworkPostLink::create(['post_id' => $post->id, 'site_id' => $site->id, 'remote_post_id' => 7, 'status' => 'synced']);

        $this->assertSame(1, (new NetworkSyncService)->resync($post));
        Queue::assertPushed(PushPostToSite::class);

        $this->assertSame(1, (new NetworkSyncService)->removeFromNetwork($post));
        Queue::assertPushed(DeletePostFromSite::class);
    }
}
