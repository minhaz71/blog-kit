<?php

namespace App\Console\Commands;

use App\Services\Seo\LinkDictionary;
use App\Services\Seo\LinkSuggestionEngine;
use Illuminate\Console\Command;

class SeoSuggestLinksCommand extends Command
{
    protected $signature = 'seo:suggest-links {--dictionary-only : rebuild the phrase dictionary without scanning}';

    protected $description = 'Rebuild the link-agent phrase dictionary and regenerate pending link suggestions (suggest-only; nothing is applied)';

    public function handle(LinkDictionary $dictionary, LinkSuggestionEngine $engine): int
    {
        $startedAt = microtime(true);

        $dict = $dictionary->rebuild();
        $this->info("Dictionary: {$dict['phrases']} phrase(s)/set(s) across {$dict['targets']} target(s).");

        if (! $this->option('dictionary-only')) {
            $scan = $engine->scanAll();
            $this->info("Suggestions: {$scan['suggestions']} pending across {$scan['sources']} source page(s).");
        }

        $this->info('Done in '.round(microtime(true) - $startedAt, 1).'s.');

        return self::SUCCESS;
    }
}
