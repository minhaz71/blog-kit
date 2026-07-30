<?php

namespace App\Console\Commands;

use App\Services\Seo\SearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SeoGscSyncCommand extends Command
{
    protected $signature = 'seo:gsc-sync {--days=28 : reporting window} {--inspect=25 : top pages to index-check (API quota: 2000/day)}';

    protected $description = 'Import Search Console page performance + GA4 organic sessions, and index-check the top pages (daily cron when configured)';

    public function handle(SearchConsoleService $gsc): int
    {
        if (! SearchConsoleService::configured()) {
            $this->warn('Skipped — add the Google service account JSON and GSC property in SEO settings → Integrations.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));

        // ── Search performance + GA4 organic sessions ───────────────
        try {
            $rows = $gsc->searchAnalytics($days);
        } catch (\Throwable $e) {
            $this->error('Search Console query failed: '.mb_substr($e->getMessage(), 0, 300));

            return self::FAILURE;
        }

        try {
            $sessions = $gsc->organicSessions($days);
        } catch (\Throwable $e) {
            $this->warn('GA4 query failed (continuing without sessions): '.mb_substr($e->getMessage(), 0, 200));
            $sessions = [];
        }

        DB::table('gsc_page_stats')->truncate();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('gsc_page_stats')->insert(array_map(fn (array $row) => [
                'url' => $row['url'],
                'clicks' => $row['clicks'],
                'impressions' => $row['impressions'],
                'ctr' => $row['ctr'],
                'position' => $row['position'],
                'organic_sessions' => $sessions[parse_url($row['url'], PHP_URL_PATH) ?? ''] ?? null,
                'period_days' => $days,
                'fetched_at' => now(),
            ], $chunk));
        }

        $this->info(count($rows).' page(s) imported from Search Console'.($sessions !== [] ? ' (+ GA4 sessions)' : '').'.');

        // ── Index status for the top pages (quota-aware) ─────────────
        $inspectLimit = max(0, (int) $this->option('inspect'));
        $inspected = 0;

        $targets = collect($rows)->sortByDesc('impressions')->take($inspectLimit)->pluck('url');

        foreach ($targets as $url) {
            try {
                $status = $gsc->inspectUrl($url);
            } catch (\Throwable $e) {
                $this->warn("Inspection failed for {$url}: ".mb_substr($e->getMessage(), 0, 150));

                break; // quota or auth issue — stop, don't hammer
            }

            DB::table('index_statuses')->updateOrInsert(['url' => $url], [
                'verdict' => $status['verdict'],
                'coverage' => $status['coverage'],
                'last_crawl_at' => $status['last_crawl'] ? Carbon::parse($status['last_crawl']) : null,
                'fetched_at' => now(),
            ]);
            $inspected++;

            if (! app()->runningUnitTests()) {
                usleep(300_000); // stay polite on the inspection API
            }
        }

        $this->info("Index status checked for {$inspected} page(s).");

        return self::SUCCESS;
    }
}
