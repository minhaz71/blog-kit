<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Ai\ImageGenerator;
use App\Services\Ai\ThumbnailService;
use App\Services\Network\NetworkSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates an AI thumbnail for one post (one image request, no revision),
 * sets it as the featured image, and — if the post is syndicated — re-pushes
 * it to every linked site so the new image propagates everywhere.
 */
class GenerateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    /** @param array{custom?:?string,style?:?string} $opts */
    public function __construct(public int $postId, public array $opts = []) {}

    public function handle(): void
    {
        $post = Post::find($this->postId);

        if (! $post || ! ImageGenerator::isConfigured()) {
            return;
        }

        $path = (new ThumbnailService)->generateForPost($post, (string) $post->title, $this->opts);

        // Keep the network in sync: push the new image to every linked site.
        if ($path && network_enabled() && is_network_hub()) {
            (new NetworkSyncService)->resync($post->fresh());
        }
    }
}
