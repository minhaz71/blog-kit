<?php

namespace App\Filament\Pages;

use App\Services\Seo\SearchConsoleService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Search Console + GA4 report: which pages get clicks, their ranking
 * position, organic sessions, and Google's real index status per URL.
 * Data lands via the daily seo:gsc-sync cron (or Sync now).
 */
class SearchPerformance extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Search performance';

    protected string $view = 'filament.pages.search-performance';

    public function syncNow(): void
    {
        if (! SearchConsoleService::configured()) {
            Notification::make()
                ->title('Not configured yet')
                ->body('Add the Google service account JSON and Search Console property in SEO settings → Integrations first.')
                ->warning()
                ->send();

            return;
        }

        if (! \App\Support\BackgroundProcess::artisan(['seo:gsc-sync'])) {
            @set_time_limit(300);
            \Illuminate\Support\Facades\Artisan::call('seo:gsc-sync');
        }

        Notification::make()
            ->title('Sync started — Search Console data appears here in a minute.')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $stats = DB::table('gsc_page_stats')->orderByDesc('clicks')->limit(200)->get();
        $statuses = DB::table('index_statuses')->get()->keyBy('url');

        return [
            'configured' => SearchConsoleService::configured(),
            'rows' => $stats,
            'statuses' => $statuses,
            'fetchedAt' => $stats->first()?->fetched_at,
            'periodDays' => $stats->first()?->period_days ?? 28,
            'totals' => (object) [
                'clicks' => $stats->sum('clicks'),
                'impressions' => $stats->sum('impressions'),
                'indexed' => $statuses->where('verdict', 'PASS')->count(),
                'notIndexed' => $statuses->where('verdict', '!=', 'PASS')->count(),
            ],
        ];
    }
}
