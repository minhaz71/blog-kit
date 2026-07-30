<?php

namespace App\Filament\Resources\AiCallQueueResource\Pages;

use App\Filament\Resources\AiCallQueueResource;
use App\Filament\Resources\AiImportBatchResource;
use App\Filament\Pages\AiUsageDashboard;
use App\Models\QueuedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAiCallQueue extends ListRecords
{
    protected static string $resource = AiCallQueueResource::class;

    public function mount(): void
    {
        parent::mount();

        // Drop rows for already-published/linked or deleted items so the queue
        // only ever shows genuinely-pending calls.
        QueuedJob::pruneSpent();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('killAll')
                ->label('Kill all pending')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => QueuedJob::count() > 0)
                ->requiresConfirmation()
                ->modalHeading('Kill every pending AI call')
                ->modalDescription('Removes all queued AI writer calls so none of them can spend credit. Items stay untouched; re-run them later from their batch. This does not delete any products, posts or batches.')
                ->action(function (): void {
                    $count = QueuedJob::count();
                    QueuedJob::query()->delete();

                    Notification::make()
                        ->title("Cleared {$count} pending AI call(s)")
                        ->body('No API credit was spent.')
                        ->success()
                        ->send();
                }),
            Action::make('runAll')
                ->label('Run all pending')
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->visible(fn (): bool => QueuedJob::count() > 0)
                ->requiresConfirmation()
                ->modalHeading('Run every pending AI call')
                ->modalDescription('Runs all queued AI writer calls in the background. Calls whose batch is still active will spend API credit. This can be a large bill if many are queued.')
                ->action(fn () => AiCallQueueResource::run(QueuedJob::all())),
            Action::make('batches')
                ->label('AI batches')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->url(AiImportBatchResource::getUrl()),
            Action::make('usage')
                ->label('AI usage & cost')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->url(AiUsageDashboard::getUrl()),
        ];
    }
}
