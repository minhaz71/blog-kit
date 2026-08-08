<?php

namespace App\Console\Commands;

use App\Models\KeywordResearchRun;
use App\Services\Research\KeywordResearchRunner;
use Illuminate\Console\Command;

/**
 * Runs one keyword-research session (discover → intent/funnel → cluster → score)
 * start to finish. Launched detached by the Keyword Research create page (same
 * pattern as ai:run-batch / ai:funnel-research), so autocomplete/SERP calls
 * never block the web request.
 */
class RunKeywordResearch extends Command
{
    protected $signature = 'keyword:research {run}';

    protected $description = 'Run a keyword-research session and populate its terms/clusters';

    public function handle(KeywordResearchRunner $runner): int
    {
        $run = KeywordResearchRun::find($this->argument('run'));

        if (! $run) {
            $this->error('No keyword research run with that id.');

            return self::FAILURE;
        }

        @set_time_limit(0);
        $run = $runner->run($run);
        $this->info($run->notes ?: 'Done.');

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
