<?php

namespace App\Jobs;

use App\Models\KeywordResearchRun;
use App\Services\Research\KeywordResearchRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one keyword-research session in the background: discovery → intent →
 * clustering → scoring. Autocomplete/SERP calls make this slow, so it never
 * blocks the request.
 */
class RunKeywordResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(KeywordResearchRunner $runner): void
    {
        $run = KeywordResearchRun::find($this->runId);

        if ($run) {
            $runner->run($run);
        }
    }
}
