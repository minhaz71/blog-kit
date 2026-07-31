<?php

namespace App\Services\Network;

use App\Jobs\DeletePostFromSite;
use App\Jobs\PushPostToSite;
use App\Models\NetworkPostLink;
use App\Models\Post;

/**
 * Hub-side two-way operations on an already-syndicated post:
 *  - resync: re-push the current hub version to every linked site (also the
 *    "hub wins" conflict resolution);
 *  - removeFromNetwork: delete the post's copy from every linked site and
 *    drop the links (the hub post itself is left untouched).
 */
class NetworkSyncService
{
    /** Re-push a post to all its linked (active) sites. Returns sites queued. */
    public function resync(Post $post): int
    {
        $links = NetworkPostLink::where('post_id', $post->id)
            ->whereHas('site', fn ($q) => $q->where('is_active', true))
            ->get();

        foreach ($links as $link) {
            PushPostToSite::dispatch($post->id, $link->site_id);
        }

        return $links->count();
    }

    /** Resolve one conflicting link by re-pushing the hub version (hub wins). */
    public function resolveHubWins(NetworkPostLink $link): void
    {
        PushPostToSite::dispatch($link->post_id, $link->site_id);
    }

    /** Delete a post's copy from every linked site and remove the links. */
    public function removeFromNetwork(Post $post): int
    {
        $links = NetworkPostLink::where('post_id', $post->id)->get();

        foreach ($links as $link) {
            DeletePostFromSite::dispatch($post->id, $link->site_id, $link->id);
        }

        return $links->count();
    }

    /** Remove one specific link's remote copy. */
    public function removeLink(NetworkPostLink $link): void
    {
        DeletePostFromSite::dispatch($link->post_id, $link->site_id, $link->id);
    }
}
