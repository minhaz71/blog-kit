<?php

namespace App\Console\Commands;

use App\Services\Performance\LiteSpeedPurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class LiteSpeedPurgeCommand extends Command
{
    protected $signature = 'litespeed:purge {--tags=* : Optional list of specific cache tags to purge}';

    protected $description = 'Purge the LiteSpeed cache (all, or by tag).';

    public function handle(LiteSpeedPurger $purger): int
    {
        $tags = $this->option('tags');

        if ($tags) {
            $purger->purgeTags($tags);
            $this->info('Purged tags: '.implode(', ', $tags));
        } else {
            $purger->purgeAll();
            Cache::flush();
            $this->info('Full cache purge queued.');
        }

        return self::SUCCESS;
    }
}
