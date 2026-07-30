<?php

namespace App\Filament\Resources\BlockedIpResource\Pages;

use App\Filament\Resources\BlockedIpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlockedIps extends ListRecords
{
    protected static string $resource = BlockedIpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
