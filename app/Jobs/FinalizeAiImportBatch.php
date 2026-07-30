<?php

namespace App\Jobs;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Services\Ai\InternalLinker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs once all items finish. Linking already happened contextually while
 * the AI wrote each product — this pass is pure verification: any link
 * pointing at a batch URL that never went live (failed item, or the slug
 * changed at publish time) is unwrapped so no page ships a dead link.
 *
 * Held (needs_review) items are NOT treated as dead: their reserved URL
 * can still go live after a human approves them, so sibling links to
 * them are kept intact.
 */
class FinalizeAiImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AiImportBatch $batch) {}

    public function handle(): void
    {
        // Atomic single-entry guard: two items finishing at the same moment
        // both dispatch finalize — only the first transition wins.
        $claimed = AiImportBatch::whereKey($this->batch->id)
            ->where('status', '!=', 'linking')
            ->update(['status' => 'linking']);

        if ($claimed === 0) {
            return; // another finalize run is already in progress
        }

        AiActivityLog::write($this->batch->id, null, 'finalize', 'All items processed — verifying internal links across the batch…');

        $items = $this->batch->items()->with('product')->get();

        // URLs promised to the AI in the catalog that never went live.
        // needs_review items are excluded — they may still be approved.
        $deadUrls = $items
            ->filter(fn ($item) => $item->reserved_slug
                && $item->status !== 'needs_review'
                && (! $item->product || $item->product->slug !== $item->reserved_slug))
            ->map(fn ($item) => \App\Support\Permalinks::product($item->reserved_slug))
            ->values()
            ->all();

        $cleaned = 0;

        if ($deadUrls !== []) {
            foreach ($items as $item) {
                if ($item->product && ($removed = InternalLinker::unwrapUrls($item->product, $deadUrls)) > 0) {
                    $cleaned += $removed;
                    AiActivityLog::write($this->batch->id, $item->id, 'link',
                        "Removed {$removed} link(s) in \"{$item->product->name}\" that pointed to product(s) that never went live.", 'warning');
                }
            }
        }

        AiActivityLog::write($this->batch->id, null, 'finalize',
            'Link verification finished — '.count($deadUrls).' dead URL(s) checked, '.$cleaned.' link(s) cleaned.',
            $cleaned > 0 ? 'warning' : 'info');

        $held = $items->where('status', 'needs_review')->count();
        $failed = $this->batch->failed_items;

        $problems = array_filter([
            $failed > 0 ? "{$failed} failed (retry from the batch)" : null,
            $held > 0 ? ($this->batch->kind === 'blog'
                ? "{$held} saved as DRAFT needing review (see Content → Posts, or Approve & publish on the item)"
                : "{$held} held for review (fix and re-run from the items list)") : null,
        ]);

        AiActivityLog::write($this->batch->id, null, 'finalize',
            $problems === []
                ? "🏁 Batch completed — {$this->batch->done_items} products written, reviewed, contextually linked, and published."
                : "🏁 Batch finished — {$this->batch->done_items} published; ".implode(', ', $problems).'.',
            $problems === [] ? 'success' : 'warning');

        $this->batch->update([
            'status' => 'completed',
            'error' => $problems === [] ? null : implode(', ', $problems).'.',
        ]);
    }
}
