<?php

namespace App\Jobs;

use App\Models\AiActivityLog;
use App\Models\AiImportItem;
use App\Services\Ai\BlogPublisher;
use App\Services\Ai\BlogWriter;
use App\Services\Ai\CrossReviewer;
use App\Services\Ai\LlmClient;
use App\Services\Ai\ReviewCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Per-article pipeline — the blog twin of WriteAiProduct with the same
 * guarantees: atomic claim (no double-processing), write → cross-review
 * loop → deterministic gate → transactional publish, learned fixes and
 * batch-memory uniqueness inherited from the shared machinery.
 */
class WriteAiBlogPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $itemId) {}

    public function handle(): void
    {
        $item = AiImportItem::with('batch')->find($this->itemId);

        if (! $item || ! $this->isClaimable($item)) {
            return;
        }

        $batch = $item->batch;

        if (! in_array($batch->status, ['processing', 'linking'])) {
            return;
        }

        $previousStatus = $item->status;

        $claimed = AiImportItem::whereKey($item->id)
            ->where('status', $previousStatus)
            ->where('updated_at', $item->updated_at)
            ->update(['status' => 'writing', 'error' => null, 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $item->refresh();

        if ($previousStatus === 'failed' && $batch->failed_items > 0) {
            $batch->decrement('failed_items');
        }

        $title = (string) ($item->row['name'] ?? "article #{$item->id}");

        // Resolve the internal-link catalog for the SITE this article is written
        // for — a connected spoke's own pages (real URLs) when it targets one,
        // else this install's pages — and compute the funnel-flow link plan.
        // Overriding batch->link_catalog in memory (not saved) points the writer,
        // reviewer, gate and publisher at the right site's links for this item.
        $this->applyLinkPlan($item, $batch);

        try {
            AiActivityLog::write($batch->id, $item->id, 'write', "✍️ Writing article \"{$title}\" via {$batch->provider}…");

            $writer = new BlogWriter(
                LlmClient::for($batch->provider, $batch->model)->withContext('write', $batch->id, $item->id)
            );

            $output = $writer->write($item);
            AiActivityLog::write($batch->id, $item->id, 'write', "Draft ready for \"{$title}\" — sending to {$batch->reviewer_provider} for review.", 'success');

            $reviewer = new CrossReviewer(
                LlmClient::for($batch->reviewer_provider, $batch->reviewer_model)->withContext('review', $batch->id, $item->id),
                system: BlogWriter::BLOG_CRITIC_SYSTEM,
            );

            $result = (new ReviewCycle($writer, $reviewer))->run($item, $output);
            $output = $result['output'];

            if (! $result['approved'] && $batch->require_approval) {
                // The article is NEVER lost: it is saved as a DRAFT post,
                // labeled needs-review — the admin finds it under Content →
                // Posts (draft) or publishes via "Approve & publish".
                $post = (new BlogPublisher)->publish($item, $output, held: true);
                $item->update(['preview_url' => route('blog.show', $post->slug)]);

                AiActivityLog::write($batch->id, $item->id, 'review',
                    "⚠️ \"{$title}\" saved as a DRAFT post (needs review) — ".count($result['issues'])
                    .' unresolved issue(s). Edit it under Content → Posts, or click "Approve & publish" on the item.', 'warning');

                $this->maybeFinalize($batch);

                return;
            }

            // Resolve which sites this article publishes to. On a hub the
            // selection may exclude THIS site (write for spokes only); off the
            // network, always publish here.
            $targets = (network_enabled() && is_network_hub())
                ? \App\Services\Network\NetworkTargets::resolve($item->row['site_ids'] ?? ($batch->network_site_ids ?: null))
                : ['local' => true, 'sites' => []];

            $post = (new BlogPublisher)->publish($item, $output, localVisible: $targets['local']);

            $batch->increment('done_items');

            $item->update(['preview_url' => route('blog.show', $post->slug)]);

            if (! $targets['local']) {
                AiActivityLog::write($batch->id, $item->id, 'publish',
                    "✍️ Wrote \"{$post->title}\" for connected sites only — kept as a hidden draft on this site.", 'success');
            } else {
                AiActivityLog::write($batch->id, $item->id, 'publish',
                    ($batch->publish_mode === 'publish' ? '🚀 Published' : 'Saved as draft').": \"{$post->title}\" → ".route('blog.show', $post->slug),
                    'success');
            }

            // AI thumbnail BEFORE the network fan-out, so the generated image
            // ships with the post to every connected site.
            $this->maybeGenerateThumbnail($item, $post, $batch);

            $this->fanOutToNetwork($item, $post, $batch, $targets['sites']);
        } catch (\Throwable $e) {
            $this->markFailed($item, $e->getMessage());
        }

        $this->maybeFinalize($batch);
    }

    public function failed(?\Throwable $e = null): void
    {
        $item = AiImportItem::with('batch')->find($this->itemId);

        if (! $item || ! in_array($item->status, ['writing', 'reviewing'], true)) {
            return;
        }

        $this->markFailed($item, 'Job was killed (timeout or fatal error): '.($e?->getMessage() ?? 'unknown'));
        $this->maybeFinalize($item->batch);
    }

    /**
     * Point this item's internal linking at the correct site and wire it to the
     * funnel rules:
     *   1. Resolve the target site from the item's `site_ids` (a spoke id, or
     *      local). Build that site's own link catalog — a spoke's real URLs come
     *      over the signed network API, never the hub's localhost URLs.
     *   2. Override the batch's link_catalog IN MEMORY so the writer, reviewer,
     *      quality gate and publisher all validate against THIS site's pages.
     *   3. Run the InternalLinkPlanner over the article's identity (role/stage/
     *      cluster) to pick the exact link targets and a per-article brief the
     *      writer follows (spoke→pillar, top/middle→money, etc.).
     * Never fatal — on any hiccup the write proceeds with whatever catalog it has.
     */
    protected function applyLinkPlan(AiImportItem $item, \App\Models\AiImportBatch $batch): void
    {
        try {
            $siteToken = $item->row['site_ids'] ?? 'local';
            $site = null;
            if ($siteToken !== 'local' && $siteToken !== '' && (int) $siteToken > 0) {
                $site = \App\Models\ConnectedSite::find((int) $siteToken);
            }

            $scope = $batch->link_scope ?: (ecommerce_enabled() ? 'ecommerce' : 'blog_only');

            // Local batches already have a baked catalog; only rebuild it when a
            // spoke is targeted (its pages, its URLs).
            $catalog = $site
                ? (new \App\Services\Ai\BlogPlanner)->buildLinkCatalog($scope, $site)
                : (array) $batch->link_catalog;

            if ($catalog === []) {
                return;
            }

            // In-memory override — NOT persisted (a batch may mix sites).
            $batch->link_catalog = $catalog;

            $plan = (new \App\Services\Ai\InternalLinkPlanner)->plan([
                'role' => $item->row['role'] ?? 'spoke',
                'stage' => $item->row['funnel_stage'] ?? 'top',
                'cluster' => $item->row['cluster'] ?? null,
                'primary_keyword' => $item->row['primary_keyword'] ?? null,
                'url' => null, // brand-new article — nothing to exclude yet
            ], $catalog);

            if ($plan['targets'] !== []) {
                $row = $item->row;
                $row['required_links'] = implode(', ', array_column($plan['targets'], 'url'));
                $row['link_plan'] = $plan['brief'];
                $item->row = $row; // in-memory; the writer reads $item->row
            }
        } catch (\Throwable $e) {
            AiActivityLog::write($batch->id, $item->id, 'write',
                '🔗 Link planning skipped ('.mb_substr($e->getMessage(), 0, 140).') — writing with the default catalog.', 'warning');
        }
    }

    /**
     * Generate an AI thumbnail for the just-published article when requested
     * (per-row "generate_image" wins; else the batch default), unless the post
     * already has a featured image. ONE image request, no revision. Never
     * fatal — the article is already published if this fails.
     */
    protected function maybeGenerateThumbnail(AiImportItem $item, \App\Models\Post $post, \App\Models\AiImportBatch $batch): void
    {
        // Per-row override → batch default.
        $rowWants = array_key_exists('generate_image', $item->row)
            ? filter_var($item->row['generate_image'], FILTER_VALIDATE_BOOLEAN)
            : null;
        $wanted = $rowWants ?? (bool) $batch->generate_images;

        if (! $wanted || $post->featured_image) {
            return;
        }

        if (! \App\Services\Ai\ImageGenerator::isConfigured()) {
            AiActivityLog::write($batch->id, $item->id, 'publish',
                '🖼️ Thumbnail requested but no image provider key is set (Settings → AI settings) — skipped.', 'warning');

            return;
        }

        try {
            $path = (new \App\Services\Ai\ThumbnailService)->generateForPost($post, (string) $post->title, [
                'custom' => $item->row['image_prompt'] ?? null,
                'style' => $item->row['image_style'] ?? ($batch->image_style ?: null),
                // Attribute the image cost to this batch/item in AI cost reports.
                'batch_id' => $batch->id,
                'item_id' => $item->id,
            ]);

            if ($path) {
                $provider = \App\Services\Ai\ImageGenerator::provider();
                AiActivityLog::write($batch->id, $item->id, 'publish',
                    "🖼️ Generated thumbnail for \"{$post->title}\" via {$provider}/".\App\Services\Ai\ImageGenerator::model($provider).'.', 'success');
            }
        } catch (\Throwable $e) {
            AiActivityLog::write($batch->id, $item->id, 'publish',
                '🖼️ Thumbnail generation failed ('.mb_substr($e->getMessage(), 0, 160).') — article published without one.', 'warning');
        }
    }

    /**
     * Multisite fan-out: push the article to the connected sites resolved for
     * it (from the per-row `site_ids` or the batch checkbox selection). Only
     * runs on a hub with the network module on. Failures here never affect the
     * local publish (already done above).
     *
     * @param  array<int>  $siteIds  active connected-site IDs (already resolved)
     */
    protected function fanOutToNetwork(AiImportItem $item, \App\Models\Post $post, \App\Models\AiImportBatch $batch, array $siteIds): void
    {
        if (! network_enabled() || ! is_network_hub() || $siteIds === []) {
            return;
        }

        $result = (new \App\Services\Network\NetworkPublisher)->publish($post, $siteIds);

        $deferred = count($result['deferred'] ?? []);

        AiActivityLog::write($batch->id, $item->id, 'publish',
            "🌐 Queued \"{$post->title}\" to {$result['queued']} connected site(s)"
            .($deferred > 0 ? " — {$deferred} will receive it at its scheduled publish time (spoke has no scheduler)" : '')
            .($result['skipped'] !== [] ? ' ('.count($result['skipped']).' inactive skipped)' : '')
            .'. Live links appear in Network → Sync status once delivered.',
            'success');

        // Version safety: warn when any target spoke is behind this hub — newer
        // fields (schema, taxonomy, new columns) may not transfer until it's updated.
        if (($result['outdated'] ?? []) !== []) {
            $names = \App\Models\ConnectedSite::whereIn('id', $result['outdated'])->pluck('name')->implode(', ');
            AiActivityLog::write($batch->id, $item->id, 'publish',
                "⚠️ Version mismatch: {$names} is on an older BlogKit version than this hub. Update it (Network → Connected sites → Update) so all content transfers.",
                'warning');
        }
    }

    protected function markFailed(AiImportItem $item, string $message): void
    {
        $item->update(['status' => 'failed', 'error' => mb_substr($message, 0, 1000)]);
        $item->batch->increment('failed_items');

        AiActivityLog::write($item->batch_id, $item->id, 'write',
            '❌ "'.($item->row['name'] ?? "item #{$item->id}")."\" failed: {$message}", 'error');
    }

    protected function isClaimable(AiImportItem $item): bool
    {
        if (in_array($item->status, ['pending', 'failed'], true)) {
            return true;
        }

        return in_array($item->status, ['writing', 'reviewing'], true)
            && $item->updated_at->lt(now()->subMinutes(WriteAiProduct::RECLAIM_MINUTES));
    }

    protected function maybeFinalize(\App\Models\AiImportBatch $batch): void
    {
        $remaining = $batch->items()
            ->whereNotIn('status', ['published', 'linked', 'failed', 'needs_review'])
            ->count();

        if ($remaining === 0) {
            FinalizeAiImportBatch::dispatch($batch);
        }
    }
}
