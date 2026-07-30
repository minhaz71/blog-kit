<?php

namespace App\Filament\Resources\CustomLinkTargetResource\Pages;

use App\Filament\Resources\CustomLinkTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomLinkTargets extends ManageRecords
{
    protected static string $resource = CustomLinkTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New target')];
    }
}
