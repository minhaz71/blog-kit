<?php

namespace App\Filament\Resources\NetworkPostResource\Pages;

use App\Filament\Resources\NetworkPostResource;
use App\Jobs\PullSitePosts;
use App\Models\ConnectedSite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListNetworkPosts extends ListRecords
{
    protected static string $resource = NetworkPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAll')
                ->label('Sync all sites')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->action(function (): void {
                    $sites = ConnectedSite::query()->active()->get();

                    foreach ($sites as $site) {
                        PullSitePosts::dispatch($site->id);
                    }

                    Notification::make()
                        ->title('Sync started')
                        ->body("Pulling posts from {$sites->count()} site(s) in the background. Refresh in a moment.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
