<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(fn (): string => $this->record->status === 'published' ? 'View' : 'Preview draft')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (): string => route('page.show', $this->record->slug).($this->record->status === 'published' ? '' : '?preview=1'))
                ->openUrlInNewTab(),
            DeleteAction::make()->label('Move to trash'),
            RestoreAction::make(),
            ForceDeleteAction::make()->label('Delete permanently'),
        ];
    }
}
