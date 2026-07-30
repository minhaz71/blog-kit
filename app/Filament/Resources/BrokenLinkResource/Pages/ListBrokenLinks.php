<?php

namespace App\Filament\Resources\BrokenLinkResource\Pages;

use App\Filament\Resources\BrokenLinkResource;
use App\Models\BrokenLink;
use App\Services\Performance\LiteSpeedPurger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBrokenLinks extends ListRecords
{
    protected static string $resource = BrokenLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('unlinkAll')
                ->label(fn (): string => 'Unlink all open ('.BrokenLink::open()->count().')')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedScissors)
                ->color('warning')
                ->visible(fn (): bool => BrokenLink::open()->exists())
                ->requiresConfirmation()
                ->modalHeading('Remove every open dead link?')
                ->modalDescription('Every dead <a> tag is removed from its page (anchor text kept as plain text) and all open reports are resolved. Pages are re-saved; the cache is cleared once at the end.')
                ->action(function (): void {
                    LiteSpeedPurger::beginBatch();
                    $removed = 0;
                    $reports = 0;
                    try {
                        BrokenLink::open()->orderBy('id')->chunkById(100, function ($chunk) use (&$removed, &$reports): void {
                            foreach ($chunk as $report) {
                                $removed += $report->unlink();
                                $reports++;
                            }
                        });
                    } finally {
                        LiteSpeedPurger::endBatch();
                    }

                    Notification::make()
                        ->title("Unlinked {$removed} dead link(s) — {$reports} report(s) resolved")
                        ->success()->send();
                }),
        ];
    }
}
