<?php

namespace App\Filament\Resources\AiImportBatchResource\Pages;

use App\Filament\Resources\AiImportBatchResource;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\AiUsageLog;
use Filament\Resources\Pages\Page;

class MonitorAiImportBatch extends Page
{
    protected static string $resource = AiImportBatchResource::class;

    protected string $view = 'filament.pages.ai-batch-monitor';

    public AiImportBatch $record;

    public function mount(AiImportBatch $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return 'Live monitor — '.$this->record->name;
    }

    public function pauseBatch(): void
    {
        if ($this->record->status === 'processing') {
            $this->record->update(['status' => 'paused']);
            AiActivityLog::write($this->record->id, null, 'control', '⏸ Batch paused by user — running item finishes, the rest wait.', 'warning');
        }
    }

    public function resumeBatch(): void
    {
        // paused/stopped = user halted it; processing = a background run
        // stalled (e.g. the process was killed) and needs re-kicking.
        if (in_array($this->record->status, ['paused', 'stopped', 'processing'])) {
            $this->record->update(['status' => 'processing', 'error' => null]);
            AiActivityLog::write($this->record->id, null, 'control', '▶ Batch resumed — finishing the remaining products in the background.', 'success');

            // Background runner finishes everything left; fall back to the
            // queue if the environment can't spawn a process.
            if (! \App\Support\BackgroundProcess::artisan(['ai:run-batch', (string) $this->record->id])) {
                foreach ($this->record->items()->whereIn('status', ['pending', 'failed'])->pluck('id') as $itemId) {
                    \App\Jobs\WriteAiProduct::dispatch($itemId);
                }
            }
        }
    }

    public function stopBatch(): void
    {
        if (in_array($this->record->status, ['processing', 'paused'])) {
            $waiting = $this->record->items()->whereIn('status', ['pending', 'failed'])->count();
            $this->record->update(['status' => 'stopped']);
            AiActivityLog::write($this->record->id, null, 'control', "⏹ Batch stopped by user — {$waiting} product(s) not processed. Resume anytime.", 'warning');
        }
    }

    /** Recover a batch whose parse job never ran (no worker at Start time). */
    public function parseNow(): void
    {
        if ($this->record->total_items === 0 && in_array($this->record->status, ['pending', 'processing'])) {
            \App\Jobs\StartAiImportBatch::dispatchSync($this->record);
        }
    }

    /** Run the next pending item in the foreground — no queue worker needed. */
    public function processNextItem(): void
    {
        $item = $this->record->items()
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                    ->orWhere(fn ($sq) => $sq->whereIn('status', ['writing', 'reviewing'])
                        ->where('updated_at', '<', now()->subMinutes(\App\Jobs\WriteAiProduct::RECLAIM_MINUTES)));
            })
            ->orderBy('id')
            ->first();

        if ($item === null) {
            return;
        }

        @set_time_limit(600);

        // The job's atomic claim makes this safe even if a queue worker
        // grabs the same item at the same moment — only one proceeds.
        \App\Jobs\WriteAiProduct::dispatchSync($item->id);
    }

    protected function getViewData(): array
    {
        $this->record->refresh();

        $pendingItems = $this->record->items()->whereIn('status', ['pending', 'failed'])->count();

        // "No worker" banner only when BOTH signals agree: this batch shows
        // no recent item activity AND queued jobs sit unclaimed — a worker
        // busy on one long AI item must not trigger a false alarm.
        $workerStalled = false;

        if ($pendingItems > 0 && config('queue.default') === 'database') {
            try {
                $batchActive = $this->record->items()
                    ->where('updated_at', '>=', now()->subSeconds(90))
                    ->whereIn('status', ['writing', 'reviewing'])
                    ->exists();

                $workerStalled = ! $batchActive && \Illuminate\Support\Facades\DB::table('jobs')
                    ->whereNull('reserved_at')
                    ->where('created_at', '<=', now()->subSeconds(60)->getTimestamp())
                    ->exists();
            } catch (\Throwable) {
                // jobs table missing — can't tell; stay quiet.
            }
        }

        // Prompt-cache effectiveness: how much of the input was served from
        // cache (10% of the normal token price) and what that saved.
        $usage = AiUsageLog::where('batch_id', $this->record->id)
            ->selectRaw('SUM(input_tokens) as input, SUM(cached_tokens) as cached, SUM(cost) as cost')
            ->first();

        $cacheHitRate = ($usage->input ?? 0) > 0 ? (int) round(100 * $usage->cached / $usage->input) : 0;

        // Each cached token would otherwise bill at the full input rate —
        // savings ≈ cached × (input price − cache price), summed per model.
        $cacheSaved = 0.0;
        foreach (AiUsageLog::where('batch_id', $this->record->id)->get(['model', 'cached_tokens']) as $row) {
            [$inPrice, , $cachePrice] = AiUsageLog::priceFor($row->model);
            $cacheSaved += $row->cached_tokens * ($inPrice - $cachePrice) / 1_000_000;
        }

        return [
            'batch' => $this->record,
            'items' => $this->record->items()->orderBy('id')->get(),
            'feed' => AiActivityLog::where('batch_id', $this->record->id)
                ->latest('id')
                ->limit(60)
                ->get(),
            'spend' => (float) ($usage->cost ?? 0),
            'cacheHitRate' => $cacheHitRate,
            'cacheSaved' => $cacheSaved,
            'workerStalled' => $workerStalled,
            'pendingItems' => $pendingItems,
        ];
    }
}
