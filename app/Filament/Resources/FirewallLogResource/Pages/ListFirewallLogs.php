<?php

namespace App\Filament\Resources\FirewallLogResource\Pages;

use App\Filament\Resources\FirewallLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFirewallLogs extends ListRecords
{
    protected static string $resource = FirewallLogResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
