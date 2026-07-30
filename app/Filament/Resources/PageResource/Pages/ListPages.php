<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** WordPress-style status tabs, including a visible Trash bin with a count. */
    public function getTabs(): array
    {
        // The resource query drops the soft-delete scope (so trashed rows are
        // resolvable for restore/force-delete), so each non-trash tab must
        // exclude trashed rows explicitly; the Trash tab shows only them.
        return [
            'all' => Tab::make('All')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at'))
                ->badge(Page::count()),
            'published' => Tab::make('Published')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'published')->whereNull('deleted_at'))
                ->badge(Page::where('status', 'published')->count()),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')->whereNull('deleted_at'))
                ->badge(Page::where('status', 'draft')->count()),
            'trash' => Tab::make('Trash')
                ->icon('heroicon-o-trash')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('deleted_at'))
                ->badge(Page::onlyTrashed()->count())
                ->badgeColor('danger'),
        ];
    }
}
