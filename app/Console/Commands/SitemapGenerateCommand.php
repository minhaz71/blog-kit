<?php

namespace App\Console\Commands;

use App\Services\Seo\SitemapGenerator;
use Illuminate\Console\Command;

class SitemapGenerateCommand extends Command
{
    protected $signature = 'seo:sitemap-generate';

    protected $description = 'Bust + pre-warm the XML sitemap cache (sitemaps also auto-refresh whenever content changes)';

    public function handle(SitemapGenerator $generator): int
    {
        SitemapGenerator::flush();

        // Warm the index + page 1 of each enabled section so the first
        // crawler request never pays the build cost.
        $generator->index();

        foreach (array_keys(SitemapGenerator::SECTIONS) as $section) {
            if (SitemapGenerator::enabled($section)) {
                $generator->section($section);
            }
        }

        $this->info('Sitemap cache regenerated and warmed.');

        return self::SUCCESS;
    }
}
