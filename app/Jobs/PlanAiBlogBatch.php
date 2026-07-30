<?php

namespace App\Jobs;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Services\Ai\BlogPlanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The blog twin of StartAiImportBatch: instead of parsing a CSV, it turns
 * the batch's niche (AI-planned topic cluster) or the owner's title list
 * into pending article items, and builds the internal-link catalog.
 */
class PlanAiBlogBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public AiImportBatch $batch) {}

    public function handle(): void
    {
        try {
            AiActivityLog::write($this->batch->id, null, 'plan',
                '🧠 Planning articles'.($this->batch->topic_ideas ? ' from your title list…' : " for the niche via {$this->batch->provider}…"));

            (new BlogPlanner)->plan($this->batch);
        } catch (\Throwable $e) {
            $this->batch->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            AiActivityLog::write($this->batch->id, null, 'plan', '❌ Planning failed: '.mb_substr($e->getMessage(), 0, 300), 'error');

            throw $e;
        }
    }
}
