<?php

namespace App\Filament\Resources\ShippingZoneResource\Pages;

use App\Filament\Resources\ShippingZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingZone extends EditRecord
{
    protected static string $resource = ShippingZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
