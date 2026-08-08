<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\Network\NetworkPostPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A hub-only article (written for connected sites) is a hidden DRAFT on the hub,
 * but the pushed copy must carry its real publish status so it goes LIVE on the
 * spoke — including a future date for scheduled cadence.
 */
class NetworkPublishStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_hidden_draft_pushes_as_published(): void
    {
        $author = User::factory()->create();
        $post = Post::create([
            'title' => 'Vaping guide', 'slug' => 'vaping-guide', 'content' => '<p>x</p>',
            'status' => 'draft', 'published_at' => null,               // hidden on the hub
            'push_status' => 'published', 'push_published_at' => now(), // real intent for spokes
            'author_id' => $author->id, 'reading_time' => 1,
        ]);

        // Local: still a hidden draft (won't show on the hub blog).
        $this->assertSame('draft', $post->status);
        // Network: the effective status is the publish intent.
        $this->assertSame('published', $post->networkStatus());

        $payload = NetworkPostPayload::for($post);
        $this->assertSame('published', $payload['status']);
        $this->assertNotNull($payload['published_at']);
    }

    public function test_scheduled_cadence_travels_to_the_spoke(): void
    {
        $author = User::factory()->create();
        $future = now()->addWeeks(2);
        $post = Post::create([
            'title' => 'Later', 'slug' => 'later', 'content' => '<p>x</p>',
            'status' => 'draft', 'published_at' => null,
            'push_status' => 'scheduled', 'push_published_at' => $future,
            'author_id' => $author->id, 'reading_time' => 1,
        ]);

        $payload = NetworkPostPayload::for($post);
        $this->assertSame('scheduled', $payload['status']);
        $this->assertSame($future->clone()->toIso8601String(), $payload['published_at']);
    }

    public function test_normal_local_post_is_unaffected(): void
    {
        $author = User::factory()->create();
        $post = Post::create([
            'title' => 'Local', 'slug' => 'local', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => now(),
            'author_id' => $author->id, 'reading_time' => 1,
        ]);

        $this->assertSame('published', $post->networkStatus());
        $this->assertSame('published', NetworkPostPayload::for($post)['status']);
    }
}
