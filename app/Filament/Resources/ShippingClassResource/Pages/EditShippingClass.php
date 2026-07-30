<?php

namespace App\Filament\Resources\ShippingClassResource\Pages;

use App\Filament\Resources\ShippingClassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingClass extends EditRecord
{
    protected static string $resource = ShippingClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
