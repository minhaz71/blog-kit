<?php

namespace App\Filament\Resources\AiImportBatchResource\Pages;

use App\Filament\Resources\AiImportBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiImportBatches extends ListRecords
{
    protected static string $resource = AiImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('sampleCsv')
                ->label('Download sample CSV')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print(\App\Services\Ai\SampleCsv::content()),
                    \App\Services\Ai\SampleCsv::FILENAME,
                    ['Content-Type' => 'text/csv'],
                )),
            CreateAction::make()->label('New import'),
        ];
    }
}
