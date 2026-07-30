<?php

namespace App\Filament\Pages;

use App\Services\Search\ProductSearch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * On-site search analytics — what customers search for, how often, and
 * which terms return nothing (content/stock gaps worth acting on).
 */
class SearchAnalytics extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Search analytics';

    protected string $view = 'filament.pages.search-analytics';

    public int $days = 30;

    public function setRange(int $days): void
    {
        $this->days = in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    public function getStats(): array
    {
        return ProductSearch::analytics($this->days);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Search settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(SearchSettings::getUrl()),
        ];
    }
}
