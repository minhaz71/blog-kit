<?php

namespace App\Filament\Resources\ConnectedSiteResource\Pages;

use App\Filament\Resources\ConnectedSiteResource;
use App\Models\ConnectedSite;
use App\Services\Network\NetworkClient;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListConnectedSites extends ListRecords
{
    protected static string $resource = ConnectedSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add site'),
            Action::make('testAll')
                ->label('Test all')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (): void {
                    $client = new NetworkClient;
                    $online = 0;
                    $sites = ConnectedSite::query()->where('is_active', true)->get();

                    foreach ($sites as $site) {
                        [$ok] = $client->refreshHealth($site);
                        $online += $ok ? 1 : 0;
                    }

                    Notification::make()
                        ->title("Checked {$sites->count()} site(s)")
                        ->body("{$online} online.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
