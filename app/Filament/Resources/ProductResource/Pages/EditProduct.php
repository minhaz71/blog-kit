<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getFooterWidgets(): array
    {
        return [\App\Filament\Widgets\LinkSuggestionsWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(fn (): string => $this->record->status === 'published' ? 'View' : 'Preview draft')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (): string => \App\Support\Permalinks::product($this->record->slug).($this->record->status === 'published' ? '' : '?preview=1'))
                ->openUrlInNewTab(),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
