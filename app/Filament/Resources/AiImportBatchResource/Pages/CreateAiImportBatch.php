<?php

namespace App\Filament\Resources\AiImportBatchResource\Pages;

use App\Filament\Resources\AiImportBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAiImportBatch extends CreateRecord
{
    protected static string $resource = AiImportBatchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
