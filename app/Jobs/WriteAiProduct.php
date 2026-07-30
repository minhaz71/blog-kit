<?php

namespace App\Jobs;

use App\Models\AiActivityLog;
use App\Models\AiImportItem;
use App\Services\Ai\CrossReviewer;
use App\Services\Ai\InternalLinker;
use App\Services\Ai\LlmClient;
use App\Services\Ai\ProductPublisher;
use App\Services\Ai\ProductWriter;
use App\Services\Ai\ReviewCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Per-product pipeline: claim → write → cross-review loop → gate →
 * publish (transactional) → post-publish extras (preview link, link audit,
 * image) → maybe finalize the batch.
 *
 * Concurrency safety: items are CLAIMED with an atomic compare-and-swap
 * UPDATE, so a queue worker and the monitor's "process now" button can
 * never double-process the same item (which would double API spend and
 * duplicate the product).
 *
 * Images are fetched ONLY after the copy passed review and the product
 * exists — held (needs_review) and failed items never download images.
 */
class WriteAiProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Worst case is many LLM calls (write + up to 4 review/rewrite cycles),
     * each with its own retries — 30 minutes covers it with margin.
     */
    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * An item stuck in writing/reviewing longer than this is considered
     * abandoned (killed worker) and may be reclaimed. MUST exceed $timeout
     * so a legitimately-running job is never processed twice.
     */
    public const RECLAIM_MINUTES = 45;

    public function __construct(public int $itemId) {}

    public function handle(): void
    {
        $item = AiImportItem::with('batch')->find($this->itemId);

        if (! $item || ! $this->isClaimable($item)) {
            return;
        }

        $batch = $item->batch;

        // Paused or stopped: drain the job harmlessly; the item stays
        // pending and is re-dispatched on resume.
        if (! in_array($batch->status, ['processing', 'linking'])) {
            return;
        }

        $previousStatus = $item->status;

        // Atomic claim (compare-and-swap): only ONE runner may take this
        // item. The WHERE repeats the claimable conditions so a concurrent
        // worker that got here first makes this update affect 0 rows.
        $claimed = AiImportItem::whereKey($item->id)
            ->where('status', $previousStatus)
            ->where('updated_at', $item->updated_at)
            ->update(['status' => 'writing', 'error' => null, 'updated_at' => now()]);

        if ($claimed === 0) {
            return; // someone else claimed it between read and write
        }

        $item->refresh();

        if (in_array($previousStatus, ['writing', 'reviewing'], true)) {
            AiActivityLog::write($batch->id, $item->id, 'write',
                'Item was stuck mid-write (interrupted run) — reclaiming and restarting.', 'warning');
        }

        // A retry of a previously failed item gets its failure count back.
        if ($previousStatus === 'failed' && $batch->failed_items > 0) {
            $batch->decrement('failed_items');
        }

        $name = (string) ($item->row['name'] ?? "item #{$item->id}");

        try {
            AiActivityLog::write($batch->id, $item->id, 'write', "✍️ Writing copy for \"{$name}\" via {$batch->provider}…");

            // WRITER — the batch provider drafts the copy.
            $writerLlm = LlmClient::for($batch->provider, $batch->model)
                ->withContext('write', $batch->id, $item->id);
            $writer = new ProductWriter($writerLlm);

            $output = $writer->write($item);
            AiActivityLog::write($batch->id, $item->id, 'write', "Copy drafted for \"{$name}\" — sending to {$batch->reviewer_provider} for review.", 'success');

            // REVIEWER — a separate, cheap model critiques; the writer then
            // rewrites. Loop until approved or passes run out.
            $reviewerLlm = LlmClient::for($batch->reviewer_provider, $batch->reviewer_model)
                ->withContext('review', $batch->id, $item->id);

            $result = (new ReviewCycle($writer, new CrossReviewer($reviewerLlm)))->run($item, $output);
            $output = $result['output'];

            // GATE: do not publish unresolved copy when approval is required.
            // Held items download NO image and create NO product — they wait
            // for a human (or a re-run) in the editor.
            if (! $result['approved'] && $batch->require_approval) {
                $item->update(['status' => 'needs_review']);
                AiActivityLog::write($batch->id, $item->id, 'review',
                    "⚠️ \"{$name}\" held for review — {$result['passes']} pass(es), ".count($result['issues'])
                    ." unresolved issue(s). Not published. Use the item's Re-run action after fixing, or edit the copy manually.", 'warning');

                $this->maybeFinalize($batch);

                return;
            }

            $verdict = $result['approved'] ? 'approved' : 'passes exhausted (approval not required)';
            AiActivityLog::write($batch->id, $item->id, 'review', "QA finished after {$result['passes']} pass(es) — {$verdict}.", 'success');

            // PUBLISH — transactional + idempotent. This is the point of no
            // return: anything after it must be non-fatal.
            $publisher = new ProductPublisher;
            $product = $publisher->publish($item, $output);

            $batch->increment('done_items');
        } catch (\Throwable $e) {
            $this->markFailed($item, $e->getMessage());

            $this->maybeFinalize($batch);

            return;
        }

        // ── Post-publish extras — never fail a published product ────────
        try {
            // Final content link for review before going public. Draft
            // products require an admin login to view.
            $previewUrl = $product->url();
            $item->update(['preview_url' => $previewUrl]);

            AiActivityLog::write($batch->id, $item->id, 'publish',
                ($batch->publish_mode === 'publish' ? '🚀 Published' : 'Saved as draft').": \"{$product->name}\" (#{$product->id}) → {$previewUrl}"
                .($batch->publish_mode === 'publish' ? '' : ' (drafts are visible to logged-in admins only)'),
                'success');

            // The AI already placed contextual links while writing (catalog
            // sent once per batch, cached). Deterministically verify them:
            // self-links and invented URLs are unwrapped to plain text.
            $catalog = (array) $batch->link_catalog;
            $catalogUrls = array_column($catalog, 'url');

            if ($catalog !== []) {
                $linker = new InternalLinker;

                // 1. Verify the AI's own contextual links (drop self/invented).
                $stats = $linker->audit($product, $catalogUrls);

                // 2. Guarantee links: deterministically link sibling product
                //    names the AI mentioned but didn't link.
                $added = $linker->ensureLinks($product->refresh(), $catalog);

                // 3. Make internal links root-relative so the copy survives
                //    a domain change (dev URL → production domain).
                InternalLinker::relativize($product->refresh());

                AiActivityLog::write($batch->id, $item->id, 'link',
                    "🔗 Internal links for \"{$product->name}\": {$stats['kept']} AI-placed kept, {$added} auto-linked"
                    .($stats['unwrapped'] > 0 ? ", {$stats['unwrapped']} invalid removed" : '').'.',
                    'success');
            }

            // IMAGE — downloaded last, only for review-approved, published
            // products. Validated (must be a real image) inside the fetcher.
            $publisher->attachImage($item, $product, $output);
        } catch (\Throwable $e) {
            // The product is live and correct; extras are best-effort.
            AiActivityLog::write($batch->id, $item->id, 'publish',
                'Post-publish step failed (non-fatal): '.mb_substr($e->getMessage(), 0, 300), 'warning');
        }

        $this->maybeFinalize($batch);
    }

    /**
     * Laravel calls this when the job dies hard (timeout kill, fatal error
     * after retries). Without it the item stays stuck in "writing" and the
     * failed counter drifts.
     */
    public function failed(?\Throwable $e = null): void
    {
        $item = AiImportItem::with('batch')->find($this->itemId);

        if (! $item || ! in_array($item->status, ['writing', 'reviewing'], true)) {
            return;
        }

        $this->markFailed($item, 'Job was killed (timeout or fatal error): '.($e?->getMessage() ?? 'unknown'));
        $this->maybeFinalize($item->batch);
    }

    protected function markFailed(AiImportItem $item, string $message): void
    {
        $item->update(['status' => 'failed', 'error' => mb_substr($message, 0, 1000)]);
        $item->batch->increment('failed_items');

        AiActivityLog::write($item->batch_id, $item->id, 'write',
            '❌ "'.($item->row['name'] ?? "item #{$item->id}")."\" failed: {$message}", 'error');
    }

    /** True when this job is allowed to pick the item up. */
    protected function isClaimable(AiImportItem $item): bool
    {
        if (in_array($item->status, ['pending', 'failed'], true)) {
            return true;
        }

        // Abandoned by a killed worker — reclaim only well past the job
        // timeout so a slow-but-alive run is never duplicated.
        return in_array($item->status, ['writing', 'reviewing'], true)
            && $item->updated_at->lt(now()->subMinutes(self::RECLAIM_MINUTES));
    }

    /** Dispatch the completion pass exactly once, when nothing is left running. */
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
