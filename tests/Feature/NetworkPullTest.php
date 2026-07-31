<?php

namespace Tests\Feature;

use App\Models\ConnectedSite;
use App\Models\NetworkRemotePost;
use App\Models\Post;
use App\Models\User;
use App\Services\Network\NetworkIdentity;
use App\Services\Network\NetworkPuller;
use App\Services\Network\NetworkSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetworkPullTest extends TestCase
{
    use RefreshDatabase;

    protected function signed(string $method, string $path): array
    {
        [$key, $secret] = NetworkIdentity::ensure();

        return NetworkSignature::headers($key, $secret, $method, ltrim($path, '/'), '', 'n'.bin2hex(random_bytes(8)), time());
    }

    public function test_list_posts_endpoint_returns_this_sites_posts(): void
    {
        $author = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        Post::create([
            'author_id' => $author->id, 'title' => 'Hello World', 'slug' => 'hello-world',
            'content' => '<p>Hi</p>', 'status' => 'published', 'published_at' => now(), 'reading_time' => 1,
        ]);

        $this->withHeaders($this->signed('GET', 'api/v1/network/posts'))
            ->get('/api/v1/network/posts')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.0.title', 'Hello World')
            ->assertJsonPath('data.0.status', 'published');
    }

    public function test_puller_mirrors_a_sites_posts(): void
    {
        $site = ConnectedSite::create([
            'name' => 'Spoke', 'base_url' => 'https://spoke.example', 'api_key' => 'k', 'api_secret' => 's', 'is_active' => true,
        ]);

        Http::fake([
            '*/api/v1/network/posts*' => Http::response([
                'ok' => true, 'current_page' => 1, 'last_page' => 1, 'total' => 2,
                'data' => [
                    ['remote_post_id' => 10, 'title' => 'Post Ten', 'slug' => 'post-ten', 'url' => 'https://spoke.example/blog/post-ten', 'status' => 'published', 'updated_at' => now()->toIso8601String(), 'category' => 'News', 'author' => 'Sam'],
                    ['remote_post_id' => 11, 'title' => 'Post Eleven', 'slug' => 'post-eleven', 'url' => 'https://spoke.example/blog/post-eleven', 'status' => 'draft', 'updated_at' => now()->toIso8601String()],
                ],
            ], 200),
        ]);

        [$ok, , $count] = (new NetworkPuller)->pull($site);

        $this->assertTrue($ok);
        $this->assertSame(2, $count);
        $this->assertSame(2, NetworkRemotePost::where('site_id', $site->id)->count());
        $this->assertSame('News', NetworkRemotePost::where('remote_post_id', 10)->value('category_name'));
        $this->assertNotNull($site->fresh()->posts_synced_at);
    }

    public function test_puller_prunes_posts_the_site_no_longer_has(): void
    {
        $site = ConnectedSite::create([
            'name' => 'Spoke', 'base_url' => 'https://spoke.example', 'api_key' => 'k', 'api_secret' => 's', 'is_active' => true,
        ]);

        // A stale mirror row for a post the site will no longer return.
        NetworkRemotePost::create([
            'site_id' => $site->id, 'remote_post_id' => 99, 'title' => 'Deleted upstream', 'status' => 'published',
        ]);

        Http::fake([
            '*/api/v1/network/posts*' => Http::response([
                'ok' => true, 'current_page' => 1, 'last_page' => 1, 'total' => 1,
                'data' => [
                    ['remote_post_id' => 10, 'title' => 'Still Here', 'slug' => 'still-here', 'url' => 'https://spoke.example/blog/still-here', 'status' => 'published', 'updated_at' => now()->toIso8601String()],
                ],
            ], 200),
        ]);

        (new NetworkPuller)->pull($site);

        $this->assertSame(1, NetworkRemotePost::where('site_id', $site->id)->count());
        $this->assertFalse(NetworkRemotePost::where('site_id', $site->id)->where('remote_post_id', 99)->exists());
        $this->assertTrue(NetworkRemotePost::where('site_id', $site->id)->where('remote_post_id', 10)->exists());
    }
}
