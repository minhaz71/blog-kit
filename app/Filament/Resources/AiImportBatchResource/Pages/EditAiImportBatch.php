<?php

namespace App\Filament\Resources\AiImportBatchResource\Pages;

use App\Filament\Resources\AiImportBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAiImportBatch extends EditRecord
{
    protected static string $resource = AiImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
