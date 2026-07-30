<?php

namespace App\Filament\Resources\AiBlogBatchResource\Pages;

use App\Filament\Resources\AiBlogBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiBlogBatches extends ListRecords
{
    protected static string $resource = AiBlogBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('sampleCsv')
                ->label('Download sample CSV')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print(\App\Services\Ai\BlogSampleCsv::content()),
                    \App\Services\Ai\BlogSampleCsv::FILENAME,
                    ['Content-Type' => 'text/csv'],
                )),
            CreateAction::make()->label('New blog batch'),
        ];
    }
}
