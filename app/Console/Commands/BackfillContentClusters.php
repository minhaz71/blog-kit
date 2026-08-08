<?php

namespace App\Console\Commands;

use App\Models\BlogTopicIdea;
use App\Models\ContentCluster;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * One-off (idempotent) backfill: stamp cluster / role / funnel_stage /
 * primary_keyword onto posts that were published BEFORE those columns existed,
 * reading the metadata back from the `blog_topic_ideas` waiting area via its
 * post_id pointer. Then stitch each cluster's pillar ↔ spokes.
 *
 * Safe to run repeatedly. Only fills columns that are still null, so it never
 * clobbers a value an admin set by hand.
 */
class BackfillContentClusters extends Command
{
    protected $signature = 'blogkit:backfill-clusters {--force : overwrite existing cluster columns too}';

    protected $description = 'Backfill cluster/funnel metadata onto posts from the topic-idea waiting area and stitch pillar↔spoke links.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $stamped = 0;

        BlogTopicIdea::query()
            ->whereNotNull('post_id')
            ->whereNotNull('cluster')
            ->with('post')
            ->chunkById(200, function ($ideas) use (&$stamped, $force) {
                foreach ($ideas as $idea) {
                    $post = $idea->post;
                    if (! $post || trim((string) $idea->cluster) === '') {
                        continue;
                    }

                    $cluster = ContentCluster::resolve((string) $idea->cluster);
                    $attrs = [];

                    $set = function (string $col, $value) use ($post, $force, &$attrs) {
                        if ($value !== null && $value !== '' && ($force || blank($post->{$col}))) {
                            $attrs[$col] = $value;
                        }
                    };

                    $set('cluster', $idea->cluster);
                    $set('content_cluster_id', $cluster->id);
                    $set('content_role', in_array($idea->role, ['pillar', 'spoke'], true) ? $idea->role : null);
                    $set('funnel_stage', in_array($idea->funnel_stage, ['top', 'middle', 'bottom'], true) ? $idea->funnel_stage : null);
                    $set('primary_keyword', $idea->primary_keyword);

                    if ($attrs !== []) {
                        // saveQuietly-style: avoid firing the revision trail for a metadata-only touch.
                        Post::withoutEvents(fn () => $post->forceFill($attrs)->save());
                        $stamped++;
                    }
                }
            });

        $this->info("Stamped cluster/funnel metadata on {$stamped} post(s).");

        $stitched = $this->stitch();
        $this->info("Stitched {$stitched} cluster(s) (pillar ↔ spokes).");

        return self::SUCCESS;
    }

    /** For every cluster, adopt its pillar post and point spokes at it. */
    protected function stitch(): int
    {
        $count = 0;

        ContentCluster::query()->chunkById(200, function ($clusters) use (&$count) {
            foreach ($clusters as $cluster) {
                $pillar = Post::query()
                    ->where('content_cluster_id', $cluster->id)
                    ->where('content_role', 'pillar')
                    ->orderBy('id')
                    ->first();

                if ($pillar) {
                    if ($cluster->pillar_post_id !== $pillar->id) {
                        $cluster->update(['pillar_post_id' => $pillar->id]);
                    }
                    Post::query()
                        ->where('content_cluster_id', $cluster->id)
                        ->where('content_role', 'spoke')
                        ->whereNull('pillar_post_id')
                        ->update(['pillar_post_id' => $pillar->id]);
                    Post::query()->whereKey($pillar->id)->update(['pillar_post_id' => null]);
                }

                $count++;
            }
        });

        return $count;
    }
}
