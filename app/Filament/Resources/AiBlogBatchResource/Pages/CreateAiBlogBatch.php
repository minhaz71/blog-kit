<?php

namespace App\Filament\Resources\AiBlogBatchResource\Pages;

use App\Filament\Resources\AiBlogBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAiBlogBatch extends CreateRecord
{
    protected static string $resource = AiBlogBatchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['csv_path'] = $data['csv_path'] ?? ''; // column is NOT NULL; empty = no CSV mode

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
