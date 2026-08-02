<?php

namespace Tests\Feature;

use App\Jobs\PushPostToSite;
use App\Models\ConnectedSite;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\Network\NetworkPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NetworkScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $status, ?\DateTimeInterface $at): Post
    {
        $author = User::create(['name' => 'W', 'email' => 'w@example.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $cat = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);

        return Post::create([
            'author_id' => $author->id, 'post_category_id' => $cat->id,
            'title' => 'Scheduled Piece', 'slug' => 'scheduled-piece',
            'excerpt' => 'x', 'content' => '<p>Body.</p>',
            'status' => $status, 'published_at' => $at, 'reading_time' => 2,
        ]);
    }

    private function site(bool $canSchedule): ConnectedSite
    {
        return ConnectedSite::create([
            'name' => 'Spoke', 'base_url' => 'https://spoke.example',
            'api_key' => 'k', 'api_secret' => 's', 'is_active' => true,
            'capabilities' => ['posts.schedule' => $canSchedule],
        ]);
    }

    public function test_future_post_pushes_now_to_a_spoke_that_can_schedule(): void
    {
        Queue::fake();
        $post = $this->makePost('scheduled', now()->addDays(3));
        $site = $this->site(canSchedule: true);

        $result = (new NetworkPublisher)->publish($post, [$site->id]);

        $this->assertSame([], $result['deferred']);
        Queue::assertPushed(PushPostToSite::class, fn ($job) => $job->siteId === $site->id && $job->delay === null);
    }

    public function test_future_post_is_deferred_for_a_spoke_without_a_scheduler(): void
    {
        Queue::fake();
        $when = now()->addDays(3);
        $post = $this->makePost('scheduled', $when);
        $site = $this->site(canSchedule: false);

        $result = (new NetworkPublisher)->publish($post, [$site->id]);

        $this->assertSame([$site->id], $result['deferred']);
        Queue::assertPushed(PushPostToSite::class, fn ($job) => $job->siteId === $site->id
            && $job->delay instanceof \DateTimeInterface
            && $job->delay->getTimestamp() === $when->getTimestamp());
    }

    public function test_immediate_post_is_never_deferred(): void
    {
        Queue::fake();
        $post = $this->makePost('published', now());
        $site = $this->site(canSchedule: false);

        $result = (new NetworkPublisher)->publish($post, [$site->id]);

        $this->assertSame([], $result['deferred']);
        Queue::assertPushed(PushPostToSite::class, fn ($job) => $job->delay === null);
    }

    public function test_unknown_capability_defers_a_future_post(): void
    {
        Queue::fake();
        $post = $this->makePost('scheduled', now()->addDay());
        // Capabilities never populated (e.g. connected but never handshaked).
        $site = ConnectedSite::create([
            'name' => 'Bare', 'base_url' => 'https://bare.example',
            'api_key' => 'k2', 'api_secret' => 's2', 'is_active' => true,
        ]);

        $result = (new NetworkPublisher)->publish($post, [$site->id]);

        $this->assertSame([$site->id], $result['deferred']);
    }
}
