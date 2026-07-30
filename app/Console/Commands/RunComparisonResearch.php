<?php

namespace App\Console\Commands;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Services\Ai\ComparisonPlanner;
use Illuminate\Console\Command;

/**
 * Runs a comparison-content research batch: deterministically pairs
 * products that share a category but differ on a structured attribute
 * (flavor family, cooling level, tobacco strength), writes one title/angle
 * per pair, and parks verified survivors in the blog ideas waiting area.
 * Launched in the background by the Blog Ideas page (same pattern as
 * ai:funnel-research).
 */
class RunComparisonResearch extends Command
{
    protected $signature = 'ai:comparison-research {batch}';

    protected $description = 'Run a comparison-content research batch and fill the blog ideas waiting area';

    public function handle(): int
    {
        $batch = AiImportBatch::query()->where('kind', 'comparison_ideas')->find($this->argument('batch'));

        if (! $batch) {
            $this->error('No comparison_ideas batch with that id.');

            return self::FAILURE;
        }

        $batch->update(['status' => 'processing', 'error' => null]);

        try {
            $saved = (new ComparisonPlanner)->run($batch);
            $this->info("{$saved} verified comparison idea(s) parked in the waiting area.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            AiActivityLog::write($batch->id, null, 'plan', '❌ Comparison research failed: '.mb_substr($e->getMessage(), 0, 300), 'error');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
