<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\Ai\BlogPublisher;
use App\Services\Network\NetworkPostPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkHashInvarianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_is_timezone_invariant(): void
    {
        $base = ['title' => 'T', 'content' => '<p>x</p>', 'status' => 'published', 'tags' => [], 'faqs' => []];

        // Same instant, two different offset representations (hub vs spoke tz).
        $utc = NetworkPostPayload::hash($base + ['published_at' => '2026-08-01T00:00:00+00:00']);
        $dxb = NetworkPostPayload::hash($base + ['published_at' => '2026-08-01T04:00:00+04:00']);

        $this->assertSame($utc, $dxb);
    }

    public function test_hash_ignores_tag_order(): void
    {
        $author = User::create(['name' => 'A', 'email' => 'h@x.example', 'password' => bcrypt('x'), 'is_active' => true]);

        $a = Post::create(['author_id' => $author->id, 'title' => 'Same', 'slug' => 'same-a', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now(), 'reading_time' => 1]);
        $b = Post::create(['author_id' => $author->id, 'title' => 'Same', 'slug' => 'same-b', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => $a->published_at, 'reading_time' => 1]);

        $x = Tag::firstOrCreate(['name' => 'Alpha'])->id;
        $y = Tag::firstOrCreate(['name' => 'Beta'])->id;
        $a->tags()->sync([$x, $y]);
        $b->tags()->sync([$y, $x]); // reverse order

        // Slug differs (excluded from the hash); only tag order differs → equal.
        $this->assertSame(NetworkPostPayload::contentHash($a), NetworkPostPayload::contentHash($b));
    }

    public function test_class_whitelist_is_idempotent(): void
    {
        $html = '<p class="bad" id="x" style="color:red">Hi <a class="bd-affiliate-btn" href="https://aff.link/a" rel="sponsored nofollow noopener" target="_blank">Buy</a></p>'
            .'<script>alert(1)</script><div class="bd-callout">note</div>';

        $once = (new BlogPublisher)->enforceClassWhitelist($html);
        $twice = (new BlogPublisher)->enforceClassWhitelist($once);

        // A second sanitize pass (the spoke re-running it) must not drift, or
        // conflict detection would false-positive on every pull.
        $this->assertSame($once, $twice);
    }
}
