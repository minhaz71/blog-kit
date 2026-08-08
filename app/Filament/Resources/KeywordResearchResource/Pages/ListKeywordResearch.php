<?php

namespace App\Filament\Resources\KeywordResearchResource\Pages;

use App\Filament\Resources\KeywordResearchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeywordResearch extends ListRecords
{
    protected static string $resource = KeywordResearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New research')->icon('heroicon-o-plus'),
        ];
    }
}
