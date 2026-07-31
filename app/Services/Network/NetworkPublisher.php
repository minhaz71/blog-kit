<?php

namespace App\Services\Network;

use App\Jobs\PushPostToSite;
use App\Models\ConnectedSite;
use App\Models\Post;

/**
 * Hub-side fan-out: queue a push of one or more posts to one or more connected
 * sites. Invalid/inactive site IDs are skipped and reported, never silently
 * dropped. Returns the list of (post, site) pairs actually queued.
 */
class NetworkPublisher
{
    /**
     * @param  array<int>  $siteIds  connected-site IDs ("all" resolved by the caller)
     * @return array{queued: int, sites: array<int>, skipped: array<int>}
     */
    public function publish(Post $post, array $siteIds): array
    {
        $active = ConnectedSite::query()->active()->whereIn('id', $siteIds)->pluck('id')->all();
        $skipped = array_values(array_diff(array_map('intval', $siteIds), $active));

        foreach ($active as $siteId) {
            PushPostToSite::dispatch($post->id, (int) $siteId);
        }

        return ['queued' => count($active), 'sites' => $active, 'skipped' => $skipped];
    }

    /**
     * Fan a batch of posts out to a set of sites (used by bulk actions + the
     * CSV site_ids workflow in the next phase).
     *
     * @param  iterable<Post>  $posts
     * @param  array<int>  $siteIds
     */
    public function publishMany(iterable $posts, array $siteIds): int
    {
        $count = 0;
        foreach ($posts as $post) {
            $count += $this->publish($post, $siteIds)['queued'];
        }

        return $count;
    }

    /** All active connected-site IDs (for an "all sites" selection). */
    public static function allSiteIds(): array
    {
        return ConnectedSite::query()->active()->pluck('id')->all();
    }
}
