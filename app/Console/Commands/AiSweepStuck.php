<?php

namespace App\Console\Commands;

use App\Jobs\FinalizeAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use Illuminate\Console\Command;

/**
 * Safety net for killed workers: items abandoned mid-write are re-queued,
 * and batches whose last item finished without triggering the completion
 * pass are finalized. Scheduled every ten minutes; also safe to run by hand.
 */
class AiSweepStuck extends Command
{
    protected $signature = 'ai:sweep-stuck';

    protected $description = 'Re-queue AI items stuck mid-write and finalize batches that never completed';

    public function handle(): int
    {
        $requeued = 0;
        $finalized = 0;

        foreach (AiImportBatch::whereIn('status', ['processing', 'linking'])->get() as $batch) {
            // Items abandoned by a killed worker — the job's atomic claim
            // accepts them once they are older than the reclaim window.
            $stuck = $batch->items()
                ->whereIn('status', ['writing', 'reviewing'])
                ->where('updated_at', '<', now()->subMinutes(WriteAiProduct::RECLAIM_MINUTES))
                ->pluck('id');

            foreach ($stuck as $itemId) {
                WriteAiProduct::dispatch($itemId);
                $requeued++;
            }

            if ($stuck->isNotEmpty()) {
                AiActivityLog::write($batch->id, null, 'control',
                    "Sweeper re-queued {$stuck->count()} item(s) abandoned mid-write.", 'warning');
            }

            // Everything settled but the batch never completed → finalize.
            $remaining = $batch->items()
                ->whereNotIn('status', ['published', 'linked', 'failed', 'needs_review'])
                ->count();

            if ($remaining === 0 && $batch->items()->exists()) {
                FinalizeAiImportBatch::dispatch($batch);
                $finalized++;
            }
        }

        $this->info("Sweep done — {$requeued} item(s) re-queued, {$finalized} batch(es) sent to finalize.");

        return self::SUCCESS;
    }
}
