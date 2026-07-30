<?php

namespace App\Filament\Pages;

use App\Models\PageSpeedSnapshot;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Latest PageSpeed snapshot per URL (mobile + desktop), refreshed by the
 * weekly cron; "Refresh now" runs the same quota-capped command on demand.
 */
class PageSpeedReport extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 50;

    protected static ?string $title = 'PageSpeed';

    protected string $view = 'filament.pages.page-speed-report';

    public function refreshNow(): void
    {
        if (! \App\Support\BackgroundProcess::artisan(['seo:pagespeed', '--strategy=both'])) {
            @set_time_limit(600);
            \Illuminate\Support\Facades\Artisan::call('seo:pagespeed', ['--strategy' => 'both']);
        }

        Notification::make()
            ->title('PageSpeed refresh started — results appear here as Google returns them (a minute or two).')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        // Latest snapshot per url+strategy in ONE query pass.
        $latest = PageSpeedSnapshot::query()
            ->orderByDesc('fetched_at')
            ->get()
            ->unique(fn ($s) => $s->url.'|'.$s->strategy)
            ->groupBy('url');

        return [
            'rows' => $latest->map(fn ($snapshots, $url) => (object) [
                'url' => $url,
                'mobile' => $snapshots->firstWhere('strategy', 'mobile'),
                'desktop' => $snapshots->firstWhere('strategy', 'desktop'),
            ])->values(),
            'lastRun' => PageSpeedSnapshot::max('fetched_at'),
        ];
    }
}
