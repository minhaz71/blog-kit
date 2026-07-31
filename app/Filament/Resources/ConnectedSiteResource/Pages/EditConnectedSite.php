<?php

namespace App\Filament\Resources\ConnectedSiteResource\Pages;

use App\Filament\Resources\ConnectedSiteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedSite extends EditRecord
{
    protected static string $resource = ConnectedSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConnectedSiteResource::testAction(),
            DeleteAction::make(),
        ];
    }
}
