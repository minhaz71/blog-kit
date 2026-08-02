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
     * @return array{queued: int, sites: array<int>, skipped: array<int>, deferred: array<int>}
     */
    public function publish(Post $post, array $siteIds): array
    {
        $active = ConnectedSite::query()->active()->whereIn('id', $siteIds)->get();
        $skipped = array_values(array_diff(array_map('intval', $siteIds), $active->pluck('id')->all()));

        // A future-dated post: capable spokes (they run the publish-scheduled
        // cron) receive it now AS scheduled and publish themselves at the right
        // time — resilient even if the hub is offline then. Spokes that don't
        // advertise the capability can't be trusted to flip it, so we DEFER the
        // push until publish time and send it already-published.
        $scheduled = $post->status === 'scheduled' && $post->published_at?->isFuture();
        $deferred = [];

        foreach ($active as $site) {
            if ($scheduled && ! self::spokeHonorsSchedule($site)) {
                PushPostToSite::dispatch($post->id, $site->id)->delay($post->published_at);
                $deferred[] = $site->id;

                continue;
            }

            PushPostToSite::dispatch($post->id, $site->id);
        }

        return [
            'queued' => $active->count(),
            'sites' => $active->pluck('id')->all(),
            'skipped' => $skipped,
            'deferred' => $deferred,
        ];
    }

    /** Does this spoke run its own scheduled-publish cron (per its last handshake)? */
    public static function spokeHonorsSchedule(ConnectedSite $site): bool
    {
        return ($site->capabilities['posts.schedule'] ?? false) === true;
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

        if (is_string($value)) {
            $lower = strtolower(trim($value));

            // Explicit "local only" tokens — a row can opt OUT of a batch
            // default so the article stays on this site.
            if (in_array($lower, ['none', 'local', 'off', 'no', 'here'], true)) {
                return [];
            }

            if ($lower === 'all') {
                return self::allSiteIds();
            }
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
