<?php

namespace App\Filament\Resources\CustomSchemaResource\Pages;

use App\Filament\Resources\CustomSchemaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomSchema extends EditRecord
{
    protected static string $resource = CustomSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
