<?php

namespace App\Filament\Resources\FileScanResultResource\Pages;

use App\Filament\Resources\FileScanResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFileScanResults extends ListRecords
{
    protected static string $resource = FileScanResultResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
