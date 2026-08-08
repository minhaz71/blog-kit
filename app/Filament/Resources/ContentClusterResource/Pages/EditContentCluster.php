<?php

namespace App\Filament\Resources\ContentClusterResource\Pages;

use App\Filament\Resources\ContentClusterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentCluster extends EditRecord
{
    protected static string $resource = ContentClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
