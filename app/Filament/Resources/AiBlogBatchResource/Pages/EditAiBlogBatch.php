<?php

namespace App\Filament\Resources\AiBlogBatchResource\Pages;

use App\Filament\Resources\AiBlogBatchResource;
use Filament\Resources\Pages\EditRecord;

class EditAiBlogBatch extends EditRecord
{
    protected static string $resource = AiBlogBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['csv_path'] = $data['csv_path'] ?? '';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
