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

    /**
     * Resolve a target-sites value into active connected-site IDs. Accepts:
     *  - "all" (any case) → every active site;
     *  - a CSV string "2, 5, 34" → those IDs (that are active);
     *  - an array of IDs → those IDs (that are active).
     * Returns only IDs that correspond to an ACTIVE connected site.
     *
     * @return array<int>
     */
    public static function resolveTargets(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_string($value) && strtolower(trim($value)) === 'all') {
            return self::allSiteIds();
        }

        $ids = is_array($value)
            ? $value
            : preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

        $ids = array_values(array_unique(array_map('intval', array_filter((array) $ids, fn ($v) => (int) $v > 0))));

        if ($ids === []) {
            return [];
        }

        return ConnectedSite::query()->active()->whereIn('id', $ids)->pluck('id')->all();
    }
}
