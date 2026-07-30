<?php

namespace App\Filament\Resources\BlockedIpResource\Pages;

use App\Filament\Resources\BlockedIpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlockedIp extends EditRecord
{
    protected static string $resource = BlockedIpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
