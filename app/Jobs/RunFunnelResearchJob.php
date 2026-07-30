<?php

namespace App\Jobs;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Services\Ai\FunnelPlanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue fallback for funnel research when background process spawning is
 * unavailable (mirrors how blog batches fall back to queued jobs).
 */
class RunFunnelResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public AiImportBatch $batch) {}

    public function handle(): void
    {
        $this->batch->update(['status' => 'processing', 'error' => null]);

        try {
            (new FunnelPlanner)->run($this->batch);
        } catch (\Throwable $e) {
            $this->batch->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            AiActivityLog::write($this->batch->id, null, 'plan', '❌ Funnel research failed: '.mb_substr($e->getMessage(), 0, 300), 'error');

            throw $e;
        }
    }
}
