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

                    // Trigger SYNCHRONOUSLY — the POST to each spoke is quick (the
                    // spoke does its own work in the background). A queued dispatch
                    // would silently never run on a hub with no queue worker, which
                    // is exactly why "update all" appeared to do nothing.
                    $ok = 0;
                    $failed = [];
                    foreach ($sites as $site) {
                        try {
                            UpdateSite::dispatchSync($site->id);
                            $site->refresh();
                            $site->last_error ? $failed[] = $site->name : $ok++;
                        } catch (\Throwable $e) {
                            $failed[] = $site->name;
                        }
                    }

                    Notification::make()
                        ->title("Update triggered on {$ok} site(s)".($failed ? ', '.count($failed).' failed' : ''))
                        ->body($failed
                            ? 'Could not reach: '.implode(', ', $failed).'. Use "Check update status" on each site to see details.'
                            : 'Each site is backing up, then updating. Use "Check update status" to watch progress.')
                        ->color($failed ? 'warning' : 'success')
                        ->send();
                }),
            Action::make('checkUpdateStatus')
                ->label('Check update status')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->modalHeading('Update status per connected site')
                ->modalContent(function () {
                    $rows = ConnectedSite::query()->active()->get()->map(function ($site) {
                        try {
                            $res = (new NetworkClient(timeout: 15))->request($site, 'GET', 'update-status');
                            $s = $res['status'] ?? [];
                            $state = $s['state'] ?? 'idle';
                            $msg = $s['message'] ?? ($s['step'] ?? '—');

                            return "<strong>{$site->name}</strong> (v".($res['version'] ?? '?')."): <em>{$state}</em> — ".e($msg);
                        } catch (\Throwable $e) {
                            return "<strong>{$site->name}</strong>: unreachable — ".e(mb_substr($e->getMessage(), 0, 120));
                        }
                    })->implode('<br><br>');

                    return new \Illuminate\Support\HtmlString($rows ?: 'No active sites.');
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }
}
