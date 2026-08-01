<?php

namespace App\Jobs;

use App\Models\ConnectedSite;
use App\Models\NetworkPostLink;
use App\Models\Post;
use App\Services\Network\NetworkClient;
use App\Services\Network\NetworkPostPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Pushes one hub Post to one connected site and records the result in
 * network_post_links. Retries a few times with backoff; a permanent failure
 * is recorded (status=failed + last_error) rather than lost.
 */
class PushPostToSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $postId, public int $siteId) {}

    public function handle(): void
    {
        $post = Post::find($this->postId);
        $site = ConnectedSite::find($this->siteId);

        if (! $post || ! $site || ! $site->is_active) {
            return;
        }

        $payload = NetworkPostPayload::for($post);
        // Store the canonical content hash (same fn the spoke reports on pull)
        // so conflict detection compares apples to apples.
        $hash = NetworkPostPayload::contentHash($post);

        try {
            $result = (new NetworkClient)->request($site, 'POST', 'posts', $payload);

            NetworkPostLink::updateOrCreate(
                ['post_id' => $post->id, 'site_id' => $site->id],
                [
                    'remote_post_id' => $result['remote_post_id'] ?? null,
                    'remote_slug' => $result['slug'] ?? null,
                    'remote_url' => $result['url'] ?? null,
                    'content_hash' => $hash,
                    'status' => 'synced',
                    'last_pushed_at' => now(),
                    'last_error' => null,
                ],
            );
        } catch (\Throwable $e) {
            NetworkPostLink::updateOrCreate(
                ['post_id' => $post->id, 'site_id' => $site->id],
                ['status' => 'failed', 'last_error' => Str::limit($e->getMessage(), 480)],
            );

            throw $e; // let the queue retry per $backoff
        }
    }

    public function failed(\Throwable $e): void
    {
        NetworkPostLink::updateOrCreate(
            ['post_id' => $this->postId, 'site_id' => $this->siteId],
            ['status' => 'failed', 'last_error' => Str::limit($e->getMessage(), 480)],
        );
    }
}
