<?php

namespace App\Console\Commands;

use App\Jobs\FinalizeAiImportBatch;
use App\Jobs\StartAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use Illuminate\Console\Command;

/**
 * Runs an ENTIRE batch to completion in one process — parse (if needed),
 * then write → review → publish every product sequentially, then finalize.
 *
 * This is the "click Start once and it finishes all products" engine. It is
 * launched detached in the background by the Start action (see
 * BackgroundProcess), so no queue worker is required. Safe to run by hand:
 *   php artisan ai:run-batch 5
 */
class AiRunBatch extends Command
{
    protected $signature = 'ai:run-batch {batch : Batch ID} {--retry-failed : reset failed items and re-run them}';

    protected $description = 'Process an AI product batch end to end (all products) in a single run — no queue worker needed';

    public function handle(): int
    {
        $batch = AiImportBatch::find((int) $this->argument('batch'));

        if (! $batch) {
            $this->error('Batch not found.');

            return self::FAILURE;
        }

        @set_time_limit(0);

        $isBlog = $batch->kind === 'blog';

        // First run: blog batches plan their topic cluster; product batches
        // parse the CSV into items.
        if ($batch->total_items === 0 && ! $batch->items()->exists()) {
            $isBlog
                ? (new \App\Jobs\PlanAiBlogBatch($batch))->handle()
                : (new StartAiImportBatch($batch))->handle();
            $batch->refresh();
        }

        if ($this->option('retry-failed')) {
            $batch->items()->where('status', 'failed')->update(['status' => 'pending', 'error' => null]);
            $batch->update(['failed_items' => 0]);
        }

        // Move a pending/paused/stopped batch into processing.
        if (in_array($batch->status, ['pending', 'paused', 'stopped', 'completed', 'failed'], true)) {
            $batch->update(['status' => 'processing', 'error' => null]);
        }

        AiActivityLog::write($batch->id, null, 'control', '▶ Batch run started — processing all products in one background run.', 'info');

        $processed = 0;
        $attempts = [];

        // Drive the pipeline one item at a time until nothing is left. Each
        // WriteAiProduct call runs the full write→review→publish for one item.
        while (true) {
            // Respect pause/stop issued from the monitor mid-run.
            $status = $batch->fresh()->status;
            if (! in_array($status, ['processing', 'linking'], true)) {
                AiActivityLog::write($batch->id, null, 'control', "⏹ Batch run halted — status is \"{$status}\".", 'warning');
                break;
            }

            // Failed items get ONE in-run retry (transient LLM faults recover
            // on a fresh sample); after that they stay failed instead of
            // looping forever and burning API spend.
            $exhausted = array_keys(array_filter($attempts, fn (int $n) => $n >= 2));

            $item = $batch->items()
                ->whereIn('status', ['pending', 'failed'])
                ->when($exhausted !== [], fn ($q) => $q->whereNotIn('id', $exhausted))
                ->orderBy('id')
                ->first();

            if (! $item) {
                break;
            }

            $attempts[$item->id] = ($attempts[$item->id] ?? 0) + 1;

            $isBlog
                ? (new \App\Jobs\WriteAiBlogPost($item->id))->handle()
                : (new WriteAiProduct($item->id))->handle();
            $processed++;
        }

        // WriteAiProduct queues Finalize via dispatch(); with no worker that
        // never runs, so finalize here synchronously when the batch is done.
        $remaining = $batch->items()->whereNotIn('status', ['published', 'linked', 'failed', 'needs_review'])->count();

        if ($remaining === 0 && $batch->fresh()->status !== 'completed') {
            (new FinalizeAiImportBatch($batch))->handle();
        }

        $this->info("Batch #{$batch->id} run finished — processed {$processed} item(s).");

        return self::SUCCESS;
    }
}
