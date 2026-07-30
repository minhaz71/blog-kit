<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiCallQueueResource\Pages\ListAiCallQueue;
use App\Models\QueuedJob;
use App\Support\BackgroundProcess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * "AI call queue" — every pending LLM writer call in the database queue, with
 * its source (item + batch) and one-click Kill (stop the spend) or Run (do it
 * now). This is the safety valve for the owner: nothing fires a paid call
 * without a worker, and here they can see and control exactly what's waiting.
 */
class AiCallQueueResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static ?string $model = QueuedJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'AI call queue';

    protected static ?string $modelLabel = 'pending AI call';

    protected static ?string $pluralModelLabel = 'AI call queue';

    public static function getNavigationBadge(): ?string
    {
        $count = QueuedJob::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return QueuedJob::count() > 0 ? 'warning' : null;
    }

    public static function canCreate(): bool
    {
        return false; // read-only queue — jobs arrive from batches, never created here
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No pending AI calls')
            ->emptyStateDescription('Nothing is waiting to spend API credit. New calls appear here when you run an AI import batch.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->columns([
                TextColumn::make('job_kind')
                    ->label('Writer')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'blog' ? 'Blog post' : 'Product')
                    ->color(fn (string $state): string => $state === 'blog' ? 'info' : 'primary'),
                TextColumn::make('source_name')
                    ->label('Source')
                    ->wrap()
                    ->description(fn (QueuedJob $record): ?string => $record->sourceItem()?->batch?->name
                        ? 'Batch: '.$record->sourceItem()->batch->name
                        : null),
                TextColumn::make('item_status')
                    ->label('Item status')
                    ->badge()
                    ->state(fn (QueuedJob $record): string => $record->sourceItem()?->status ?? 'orphaned')
                    ->color(fn (string $state): string => match ($state) {
                        'published', 'linked' => 'success',
                        'failed' => 'danger',
                        'needs_review' => 'warning',
                        'orphaned' => 'gray',
                        default => 'info',
                    }),
                IconColumn::make('will_spend')
                    ->label('Spends credit')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedBanknotes)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (QueuedJob $record): string => $record->will_spend
                        ? 'Running this will make a paid LLM call.'
                        : 'Its batch is finished — running it drains harmlessly (no credit).'),
                TextColumn::make('queued_at')
                    ->label('Queued')
                    ->since()
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('available_at', $direction)),
                TextColumn::make('attempts')
                    ->label('Tries')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('run')
                    ->label('Run')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Run this AI call now')
                    ->modalDescription(fn (QueuedJob $record): string => $record->will_spend
                        ? "This makes a paid LLM call to write \"{$record->source_name}\" and then removes it from the queue."
                        : "\"{$record->source_name}\" belongs to a finished batch; running re-activates its item and writes it now.")
                    ->action(fn (QueuedJob $record) => static::run([$record])),
                Action::make('kill')
                    ->label('Kill')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Kill this pending call')
                    ->modalDescription(fn (QueuedJob $record): string => "Removes the queued call for \"{$record->source_name}\". No credit is spent. Its item stays where it is — re-run it later from the batch if you want.")
                    ->action(fn (QueuedJob $record) => static::kill([$record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('runSelected')
                        ->label('Run selected')
                        ->icon(Heroicon::OutlinedPlay)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Runs each selected AI call in the background. Calls whose batch is still active will spend credit.')
                        ->action(fn (Collection $records) => static::run($records))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('killSelected')
                        ->label('Kill selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Removes the selected pending calls from the queue. No credit is spent.')
                        ->action(fn (Collection $records) => static::kill($records))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('available_at', 'asc')
            ->poll('15s');
    }

    /** Launch each job detached so the LLM calls run off the request. */
    public static function run(iterable $records): void
    {
        $launched = 0;

        foreach ($records as $record) {
            if (BackgroundProcess::artisan(['ai:run-queued-job', (string) $record->getKey()])) {
                $launched++;
            }
        }

        Notification::make()
            ->title($launched > 0 ? "Running {$launched} AI call(s) in the background" : 'Could not start the runner')
            ->body($launched > 0 ? 'Watch progress on the batch Live monitor.' : 'No background worker available in this environment.')
            ->{$launched > 0 ? 'success' : 'warning'}()
            ->send();
    }

    /** Delete the queued rows — stops the spend, touches nothing else. */
    public static function kill(iterable $records): void
    {
        $killed = 0;

        foreach ($records as $record) {
            $record->delete();
            $killed++;
        }

        Notification::make()
            ->title("Killed {$killed} pending AI call(s)")
            ->body('No API credit was spent.')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiCallQueue::route('/'),
        ];
    }
}
