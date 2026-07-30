<?php

namespace App\Filament\Resources\LoginAttemptResource\Pages;

use App\Filament\Resources\LoginAttemptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoginAttempts extends ListRecords
{
    protected static string $resource = LoginAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
