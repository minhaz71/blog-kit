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

            $post = (new BlogPublisher)->publish($item, $output);

            $batch->increment('done_items');

            $item->update(['preview_url' => route('blog.show', $post->slug)]);
            AiActivityLog::write($batch->id, $item->id, 'publish',
                ($batch->publish_mode === 'publish' ? '🚀 Published' : 'Saved as draft').": \"{$post->title}\" → ".route('blog.show', $post->slug),
                'success');

            // AI thumbnail BEFORE the network fan-out, so the generated image
            // ships with the post to every connected site.
            $this->maybeGenerateThumbnail($item, $post, $batch);

            $this->fanOutToNetwork($item, $post, $batch);
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
     * Multisite fan-out: after a real (non-held) publish, also push the
     * article to the connected sites chosen for this article. Per-row
     * `site_ids` (CSV) overrides the batch-level default; "all" targets every
     * active site. Only runs on a hub with the network module on. Failures
     * here never affect the local publish (already done above).
     */
    protected function fanOutToNetwork(AiImportItem $item, \App\Models\Post $post, \App\Models\AiImportBatch $batch): void
    {
        if (! network_enabled() || ! is_network_hub()) {
            return;
        }

        $value = $item->row['site_ids'] ?? ($batch->network_site_ids ?: null);
        $siteIds = \App\Services\Network\NetworkPublisher::resolveTargets($value);

        if ($siteIds === []) {
            return;
        }

        $result = (new \App\Services\Network\NetworkPublisher)->publish($post, $siteIds);

        AiActivityLog::write($batch->id, $item->id, 'publish',
            "🌐 Queued \"{$post->title}\" to {$result['queued']} connected site(s)"
            .($result['skipped'] !== [] ? ' ('.count($result['skipped']).' inactive skipped)' : '').'.',
            'success');
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
