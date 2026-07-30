<?php

namespace App\Filament\Resources\CustomSchemaResource\Pages;

use App\Filament\Resources\CustomSchemaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomSchemas extends ListRecords
{
    protected static string $resource = CustomSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
