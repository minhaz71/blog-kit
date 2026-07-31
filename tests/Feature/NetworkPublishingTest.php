<?php

namespace Tests\Feature;

use App\Jobs\PushPostToSite;
use App\Models\ConnectedSite;
use App\Models\NetworkPostLink;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\Network\NetworkIdentity;
use App\Services\Network\NetworkPostPayload;
use App\Services\Network\NetworkSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetworkPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function sourcePost(): Post
    {
        $author = User::create([
            'name' => 'Writer', 'email' => 'writer@example.com',
            'password' => bcrypt('secret'), 'is_active' => true,
        ]);
        $cat = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);

        $post = Post::create([
            'author_id' => $author->id,
            'post_category_id' => $cat->id,
            'title' => 'How to Compost at Home',
            'slug' => 'how-to-compost-at-home',
            'excerpt' => 'A quick start guide.',
            'content' => '<h2>Getting started</h2><p>Composting is easy.</p>',
            'status' => 'published',
            'published_at' => now(),
            'reading_time' => 3,
        ]);
        $post->seoMeta()->create([
            'title' => 'Compost at Home', 'description' => 'Start composting today.', 'focus_keyword' => 'home composting',
        ]);
        $post->allFaqs()->create(['question' => 'Is composting hard?', 'answer' => 'No, it is simple.', 'sort_order' => 0, 'is_active' => true]);
        $post->tags()->sync([\App\Models\Tag::firstOrCreate(['name' => 'Gardening'])->id]);

        return $post->fresh();
    }

    /** Raw signed POST so the body byte-string matches the signature. */
    protected function pushToSelf(array $payload): \Illuminate\Testing\TestResponse
    {
        [$key, $secret] = NetworkIdentity::ensure();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = NetworkSignature::headers($key, $secret, 'POST', 'api/v1/network/posts', $body, 'n'.bin2hex(random_bytes(8)), time());

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $this->call('POST', '/api/v1/network/posts', [], [], [], $server, $body);
    }

    public function test_spoke_ingests_a_pushed_post(): void
    {
        $payload = NetworkPostPayload::for($this->sourcePost());

        $this->pushToSelf($payload)->assertOk()->assertJson(['ok' => true]);

        $ingested = Post::where('network_origin', 'like', '%:'.$payload['network_post_id'])->first();
        $this->assertNotNull($ingested);
        $this->assertSame('How to Compost at Home', $ingested->title);
        $this->assertSame('home composting', $ingested->seoMeta->focus_keyword);
        $this->assertCount(1, $ingested->allFaqs);
        $this->assertSame('Guides', $ingested->category->name);
        $this->assertTrue($ingested->tags->pluck('name')->contains('Gardening'));
    }

    public function test_ingest_is_idempotent(): void
    {
        $payload = NetworkPostPayload::for($this->sourcePost());

        $this->pushToSelf($payload)->assertOk();
        $this->pushToSelf($payload)->assertOk();

        // Exactly one network-managed copy (plus the original source post).
        $this->assertSame(1, Post::whereNotNull('network_origin')->count());
    }

    public function test_hub_push_records_a_synced_link(): void
    {
        Http::fake([
            '*/api/v1/network/posts' => Http::response([
                'ok' => true, 'remote_post_id' => 4242, 'slug' => 'how-to-compost-at-home',
                'url' => 'https://spoke.example.com/blog/how-to-compost-at-home', 'status' => 'published',
            ], 200),
        ]);

        $post = $this->sourcePost();
        $site = ConnectedSite::create([
            'name' => 'Spoke A', 'base_url' => 'https://spoke.example.com',
            'api_key' => 'bk_test', 'api_secret' => 'sk_test', 'is_active' => true,
        ]);

        PushPostToSite::dispatchSync($post->id, $site->id);

        $link = NetworkPostLink::where('post_id', $post->id)->where('site_id', $site->id)->first();
        $this->assertNotNull($link);
        $this->assertSame('synced', $link->status);
        $this->assertSame(4242, (int) $link->remote_post_id);
        $this->assertNotEmpty($link->content_hash);
    }
}
