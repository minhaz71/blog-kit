<?php

namespace App\Filament\Resources\ConnectedSiteResource\Pages;

use App\Filament\Resources\ConnectedSiteResource;
use App\Jobs\UpdateSite;
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
            Action::make('updateAll')
                ->label('Update all sites')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Update every connected site')
                ->modalDescription('Trigger blogkit:update on all active sites. Each takes a backup first, then pulls and migrates, staying online throughout.')
                ->action(function (): void {
                    $sites = ConnectedSite::query()->active()->get();

                    foreach ($sites as $site) {
                        UpdateSite::dispatch($site->id);
                    }

                    Notification::make()
                        ->title('Update triggered on '.$sites->count().' site(s)')
                        ->body('Each site is backing up, then updating in the background.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
