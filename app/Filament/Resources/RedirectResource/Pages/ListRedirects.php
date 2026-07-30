<?php

namespace App\Filament\Resources\RedirectResource\Pages;

use App\Filament\Resources\RedirectResource;
use App\Models\Redirect;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(function () {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['source', 'target', 'status_code', 'is_regex', 'is_active', 'hits']);

                    Redirect::query()->orderBy('id')->chunk(500, function ($redirects) use ($out) {
                        foreach ($redirects as $r) {
                            fputcsv($out, [$r->source, $r->target, $r->status_code, (int) $r->is_regex, (int) $r->is_active, $r->hits]);
                        }
                    });

                    fclose($out);
                }, 'redirects-'.now()->format('Y-m-d').'.csv')),
            Action::make('import')
                ->label('Import CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV with columns: source, target, status_code (optional), is_regex (optional)')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('seo-imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $handle = fopen(Storage::disk('local')->path($data['file']), 'r');
                    $headers = null;
                    $imported = 0;

                    while (($row = fgetcsv($handle)) !== false) {
                        if ($headers === null) {
                            $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $row);

                            continue;
                        }

                        $line = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), null));

                        if (blank($line['source'] ?? null)) {
                            continue;
                        }

                        Redirect::updateOrCreate(
                            ['source' => trim((string) $line['source'])],
                            [
                                'target' => trim((string) ($line['target'] ?? '')) ?: null,
                                'status_code' => (int) ($line['status_code'] ?? 301) ?: 301,
                                'is_regex' => (bool) ($line['is_regex'] ?? false),
                                'is_active' => true,
                            ],
                        );
                        $imported++;
                    }

                    fclose($handle);

                    Notification::make()->title("{$imported} redirect(s) imported.")->success()->send();
                }),
        ];
    }
}
