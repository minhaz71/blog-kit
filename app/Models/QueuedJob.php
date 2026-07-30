<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view over Laravel's `jobs` (database queue) table, scoped to the
 * AI writer jobs only — the calls that actually spend API credit. Powers the
 * "AI call queue" dashboard: see every pending LLM call, its source item and
 * batch, and kill or run it.
 *
 * Nothing writes to this model except deletes (killing a pending call). The
 * payload is the standard Laravel serialized job; we parse out the job class
 * and the AiImportItem id it targets.
 */
class QueuedJob extends Model
{
    protected $table = 'jobs';

    public $timestamps = false;

    protected $guarded = [];

    /** Job classes that make paid LLM calls. */
    public const AI_JOBS = ['WriteAiProduct', 'WriteAiBlogPost'];

    /** Memoized source item so a table row resolves it at most once. */
    protected ?AiImportItem $resolvedItem = null;

    protected bool $itemResolved = false;

    protected static function booted(): void
    {
        // The dashboard only ever concerns paid AI calls — emails and batch
        // bookkeeping jobs are deliberately excluded.
        static::addGlobalScope('ai', function (Builder $query): void {
            $query->where(function (Builder $q): void {
                foreach (self::AI_JOBS as $class) {
                    $q->orWhere('payload', 'like', '%'.$class.'%');
                }
            });
        });
    }

    /**
     * Delete queued AI-call rows that can never do anything: their item is
     * already published/linked (a no-op — the writer refuses to re-process
     * it) or the item/batch is gone. Keeps the dashboard honest and the queue
     * clean without touching genuinely-pending calls (pending/failed/needs_review).
     */
    public static function pruneSpent(): int
    {
        $rows = static::all();

        if ($rows->isEmpty()) {
            return 0;
        }

        $jobsByItem = [];
        $orphans = [];

        foreach ($rows as $row) {
            if ($itemId = $row->item_id) {
                $jobsByItem[$itemId][] = $row->getKey();
            } else {
                $orphans[] = $row->getKey();
            }
        }

        $statuses = AiImportItem::whereIn('id', array_keys($jobsByItem))->pluck('status', 'id');

        $kill = $orphans;
        foreach ($jobsByItem as $itemId => $jobIds) {
            $status = $statuses[$itemId] ?? null; // null = item deleted
            if ($status === null || in_array($status, ['published', 'linked'], true)) {
                $kill = array_merge($kill, $jobIds);
            }
        }

        return $kill === [] ? 0 : static::whereIn('id', $kill)->delete();
    }

    /** @return array<string,mixed> */
    protected function payloadArray(): array
    {
        return json_decode((string) $this->payload, true) ?: [];
    }

    /** "product" | "blog" — which writer this job runs. */
    public function getJobKindAttribute(): string
    {
        $name = (string) ($this->payloadArray()['displayName'] ?? '');

        return str_contains($name, 'WriteAiBlogPost') ? 'blog' : 'product';
    }

    /** The AiImportItem id this call was queued for. */
    public function getItemIdAttribute(): ?int
    {
        $command = (string) ($this->payloadArray()['data']['command'] ?? '');

        return preg_match('/itemId";i:(\d+)/', $command, $m) ? (int) $m[1] : null;
    }

    /** The source item (with its batch) — resolved once per instance. */
    public function sourceItem(): ?AiImportItem
    {
        if (! $this->itemResolved) {
            $this->itemResolved = true;
            $this->resolvedItem = $this->item_id
                ? AiImportItem::with('batch')->find($this->item_id)
                : null;
        }

        return $this->resolvedItem;
    }

    /** Best-effort human name of the product/article being written. */
    public function getSourceNameAttribute(): string
    {
        $item = $this->sourceItem();

        if (! $item) {
            return $this->item_id ? "item #{$this->item_id} (removed)" : 'unknown source';
        }

        return (string) ($item->row['name'] ?? "item #{$item->id}");
    }

    public function getQueuedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->available_at ? \Illuminate\Support\Carbon::createFromTimestamp($this->available_at) : null;
    }

    /**
     * Whether running this job would actually spend credit. A job whose batch
     * is not processing/linking drains harmlessly (the writer early-returns),
     * so it is safe/free — worth surfacing so the owner can bulk-kill dead ones.
     */
    public function getWillSpendAttribute(): bool
    {
        $batch = $this->sourceItem()?->batch;

        return $batch !== null && in_array($batch->status, ['processing', 'linking'], true);
    }
}
