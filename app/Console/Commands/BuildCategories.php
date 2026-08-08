<?php

namespace App\Console\Commands;

use App\Services\Ai\CategoryPlanner;
use Illuminate\Console\Command;

/**
 * Build the two-level blog category tree (mother → sub) from the existing
 * content clusters, capped at blog.max_categories. Idempotent — safe to run on
 * every deploy; a no-op once everything is categorized.
 */
class BuildCategories extends Command
{
    protected $signature = 'blogkit:build-categories';

    protected $description = 'Auto-build the blog category tree (mother + sub) from content clusters, capped and idempotent.';

    public function handle(CategoryPlanner $planner): int
    {
        $result = $planner->run();

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
