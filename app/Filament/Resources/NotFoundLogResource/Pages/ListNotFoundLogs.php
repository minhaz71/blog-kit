<?php

namespace App\Filament\Resources\NotFoundLogResource\Pages;

use App\Filament\Resources\NotFoundLogResource;
use App\Models\NotFoundLog;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListNotFoundLogs extends ListRecords
{
    protected static string $resource = NotFoundLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export 404 log (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(function () {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['url', 'hits', 'referrer', 'last_hit_at', 'redirected']);

                    NotFoundLog::query()->orderByDesc('hits')->chunk(500, function ($logs) use ($out) {
                        foreach ($logs as $log) {
                            fputcsv($out, [
                                $log->url, $log->hits, $log->referrer,
                                $log->last_hit_at?->toDateTimeString(),
                                $log->redirect_id ? 'yes' : 'no',
                            ]);
                        }
                    });

                    fclose($out);
                }, '404-log-'.now()->format('Y-m-d').'.csv')),
        ];
    }
}
