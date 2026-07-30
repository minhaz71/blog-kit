<?php

namespace App\Console\Commands;

use App\Services\Seo\PageSpeedService;
use Illuminate\Console\Command;

class SeoPageSpeedCommand extends Command
{
    protected $signature = 'seo:pagespeed {--limit=15 : max URLs per run} {--strategy=mobile : mobile, desktop, or both}';

    protected $description = 'Snapshot PageSpeed scores for the key pages (home, categories, top products) — weekly cron, quota-aware';

    public function handle(PageSpeedService $service): int
    {
        $urls = array_slice($service->keyUrls(), 0, max(1, (int) $this->option('limit')));
        $strategies = $this->option('strategy') === 'both' ? ['mobile', 'desktop'] : [(string) $this->option('strategy')];

        $done = 0;

        foreach ($urls as $url) {
            foreach ($strategies as $strategy) {
                $snapshot = $service->snapshot($url, $strategy);

                if ($snapshot) {
                    $done++;
                    $this->line("{$strategy} {$snapshot->performance} — {$url}");
                } else {
                    $this->warn("failed — {$url} ({$strategy})");
                }

                if (! app()->runningUnitTests()) {
                    sleep(1); // stay well under the PSI rate limit
                }
            }
        }

        // Keep only the last 12 snapshots per URL/strategy — bounded table.
        \Illuminate\Support\Facades\DB::statement(
            'DELETE FROM page_speed_snapshots WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY url, strategy ORDER BY fetched_at DESC) rn
                    FROM page_speed_snapshots
                ) ranked WHERE rn <= 12
            )'
        );

        $this->info("Captured {$done} snapshot(s).");

        return self::SUCCESS;
    }
}
