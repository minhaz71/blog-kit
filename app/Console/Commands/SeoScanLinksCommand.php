<?php

namespace App\Console\Commands;

use App\Services\Seo\InternalLinkScanner;
use Illuminate\Console\Command;

class SeoScanLinksCommand extends Command
{
    protected $signature = 'seo:scan-links';

    protected $description = 'Rebuild the internal link index from product + post content (weekly cron; run manually after bulk content edits)';

    public function handle(InternalLinkScanner $scanner): int
    {
        $stats = $scanner->scanAll();

        $this->info("Scanned {$stats['sources']} live products/posts — indexed {$stats['links']} internal link(s) in {$stats['seconds']}s.");

        return self::SUCCESS;
    }
}
