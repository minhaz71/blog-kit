<?php

namespace App\Console\Commands;

use App\Jobs\WriteAiBlogPost;
use App\Jobs\WriteAiProduct;
use App\Models\AiImportItem;
use App\Models\QueuedJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs ONE pending AI-writer job now (from the AI call queue dashboard's "Run"
 * button), then removes it from the queue. Meant to be launched detached so
 * the multi-minute LLM call happens off the web request.
 *
 * It re-arms the source so the write actually happens: a job whose batch has
 * finished would otherwise drain harmlessly, so we flip the item back to
 * pending and its batch to processing — the same path as the per-item "Re-run"
 * action. The writer's atomic claim still prevents any double-processing.
 */
class RunQueuedAiJob extends Command
{
    protected $signature = 'ai:run-queued-job {job : jobs.id of the queued AI call}';

    protected $description = 'Process one pending AI writer job, then remove it from the queue';

    public function handle(): int
    {
        $jobId = (int) $this->argument('job');

        // Read raw (no global scope) so we can always clean the row up.
        $row = DB::table('jobs')->where('id', $jobId)->first();

        if (! $row) {
            $this->warn("Job #{$jobId} is no longer queued.");

            return self::SUCCESS;
        }

        $job = new QueuedJob((array) $row);
        $job->exists = true;
        $kind = $job->job_kind;
        $itemId = $job->item_id;

        $item = $itemId ? AiImportItem::with('batch')->find($itemId) : null;

        if (! $item || ! $item->batch) {
            // Orphaned call — the item/batch was deleted. Nothing to run.
            DB::table('jobs')->where('id', $jobId)->delete();
            $this->warn('Source item/batch is gone — removed the orphaned job.');

            return self::SUCCESS;
        }

        // Re-arm so the writer does the work instead of draining.
        if (in_array($item->status, ['published', 'linked'], true)) {
            $this->warn("\"{$job->source_name}\" is already published — skipping to avoid duplicate spend.");
            DB::table('jobs')->where('id', $jobId)->delete();

            return self::SUCCESS;
        }

        $item->update(['status' => 'pending', 'error' => null]);
        if (! in_array($item->batch->status, ['processing', 'linking'], true)) {
            $item->batch->update(['status' => 'processing']);
        }

        // Remove the queued row first so a concurrent worker can't also grab
        // it; the writer's compare-and-swap claim is the real double-run guard.
        DB::table('jobs')->where('id', $jobId)->delete();

        $this->info("Running {$kind} writer for \"{$job->source_name}\"…");

        try {
            $kind === 'blog'
                ? (new WriteAiBlogPost($item->id))->handle()
                : (new WriteAiProduct($item->id))->handle();
        } catch (\Throwable $e) {
            $this->error('Job failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
