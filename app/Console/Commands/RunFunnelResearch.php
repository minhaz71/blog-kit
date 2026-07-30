<?php

namespace App\Console\Commands;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Services\Ai\FunnelPlanner;
use Illuminate\Console\Command;

/**
 * Runs a Content Cluster & Funnel research batch start to finish —
 * research, cluster design, title generation, 3-5 verification rounds —
 * and parks the verified ideas in the waiting area. Launched in the
 * background by the Blog Ideas page (same pattern as ai:run-batch).
 */
class RunFunnelResearch extends Command
{
    protected $signature = 'ai:funnel-research {batch}';

    protected $description = 'Run a funnel/cluster title research batch and fill the blog ideas waiting area';

    public function handle(): int
    {
        $batch = AiImportBatch::query()->where('kind', 'blog_ideas')->find($this->argument('batch'));

        if (! $batch) {
            $this->error('No blog_ideas batch with that id.');

            return self::FAILURE;
        }

        $batch->update(['status' => 'processing', 'error' => null]);

        try {
            $saved = (new FunnelPlanner)->run($batch);
            $this->info("{$saved} verified title ideas parked in the waiting area.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            AiActivityLog::write($batch->id, null, 'plan', '❌ Funnel research failed: '.mb_substr($e->getMessage(), 0, 300), 'error');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
