<?php

namespace App\Filament\Widgets;

use App\Models\AiImportBatch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Live visibility for the Blog Ideas page's "Generate ideas" and
 * "Generate comparisons" runs. Without this the page only ever shows
 * finished BlogTopicIdea rows — so a run that failed, stalled, or
 * produced nothing looked identical to "nothing happened" while still
 * burning tokens. This surfaces every research batch with its status,
 * progress, ideas produced, the exact failure reason, and the full
 * activity log, plus a Retry for stalled/failed runs.
 */
class FunnelResearchRunsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Research runs';

    protected static ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AiImportBatch::query()
                    ->whereIn('kind', ['blog_ideas', 'comparison_ideas'])
                    ->withCount('activityLogs')
                    ->latest('id')
                    ->limit(15)
            )
            ->emptyStateHeading('No research runs yet')
            ->emptyStateDescription('Use “Generate ideas” or “Generate comparisons” above. Each run appears here with live progress, and any failure is reported in full.')
            ->columns([
                TextColumn::make('name')->label('Run')->wrap()->weight('semibold')
                    ->description(fn (AiImportBatch $r) => $r->kind === 'comparison_ideas' ? 'Comparison research' : 'Funnel / cluster research'),
                TextColumn::make('status')->badge()
                    ->state(fn (AiImportBatch $r) => $r->isStalled() ? 'stalled' : $r->status)
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'stalled' => 'Stalled (process died)',
                        'processing' => 'Running…',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'processing' => 'info',
                        'pending' => 'gray',
                        'stalled', 'failed' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('done_items')->label('Ideas')->alignCenter()
                    ->formatStateUsing(fn (AiImportBatch $r) => $r->status === 'completed'
                        ? (int) $r->total_items
                        : (int) $r->done_items),
                TextColumn::make('provider')->badge()->color('gray')
                    ->formatStateUsing(fn (AiImportBatch $r) => trim($r->provider.' '.($r->model ?? ''))),
                TextColumn::make('error')->label('Result')->wrap()->color('danger')
                    ->placeholder('—')
                    ->limit(120)
                    ->tooltip(fn (AiImportBatch $r) => $r->error),
                TextColumn::make('created_at')->since()->label('Started'),
            ])
            ->recordActions([
                Action::make('log')
                    ->label('Activity log')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalHeading(fn (AiImportBatch $r) => 'Activity log — '.$r->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (AiImportBatch $r) => view('filament.funnel.activity-log', [
                        'feed' => $r->activityLogs()->latest('id')->limit(200)->get(),
                    ])),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (AiImportBatch $r) => $r->status === 'failed' || $r->isStalled())
                    ->requiresConfirmation()
                    ->modalDescription('Re-run this research from the start. It reuses the same settings and skips any ideas already parked.')
                    ->action(function (AiImportBatch $r): void {
                        $r->update(['status' => 'pending', 'error' => null]);

                        $command = $r->kind === 'comparison_ideas' ? 'ai:comparison-research' : 'ai:funnel-research';
                        $launched = \App\Support\BackgroundProcess::artisan([$command, (string) $r->id]);

                        if (! $launched) {
                            $r->kind === 'comparison_ideas'
                                ? \App\Jobs\RunComparisonResearchJob::dispatch($r)
                                : \App\Jobs\RunFunnelResearchJob::dispatch($r);
                        }

                        Notification::make()->title('Research restarted')
                            ->body($launched ? 'Running in the background — watch the status here.' : 'Queued for a worker (run "php artisan queue:work").')
                            ->success()->send();
                    }),
                Action::make('delete')
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->tooltip('Remove this run from the list')
                    ->visible(fn (AiImportBatch $r) => in_array($r->status, ['completed', 'failed'], true) || $r->isStalled())
                    ->requiresConfirmation()
                    ->action(fn (AiImportBatch $r) => $r->delete()),
            ])
            ->paginated(false);
    }
}
