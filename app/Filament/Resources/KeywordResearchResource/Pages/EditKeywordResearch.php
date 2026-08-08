<?php

namespace App\Filament\Resources\KeywordResearchResource\Pages;

use App\Filament\Resources\BlogTopicIdeaResource;
use App\Filament\Resources\KeywordResearchResource;
use App\Services\Research\KeywordResearchRunner;
use App\Services\Research\PlanBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditKeywordResearch extends EditRecord
{
    protected static string $resource = KeywordResearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')
                ->label('Run research')
                ->icon('heroicon-o-magnifying-glass')
                ->requiresConfirmation()
                ->modalDescription('Discovers keywords for the seeds, clusters them, and stages them by funnel. Replaces any previous terms for this run.')
                ->action(function (): void {
                    @set_time_limit(600);
                    app(KeywordResearchRunner::class)->run($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title($this->record->status === 'failed' ? 'Research failed' : 'Research complete')
                        ->body($this->record->notes)
                        ->color($this->record->status === 'failed' ? 'danger' : 'success')
                        ->send();
                }),

            Action::make('plan')
                ->label('Create content plan')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['clustered', 'planned'], true))
                ->form([
                    TextInput::make('limit')->label('Max ideas to add')->numeric()->default(60)->minValue(1)->maxValue(300),
                ])
                ->action(function (array $data): void {
                    $result = app(PlanBuilder::class)->build($this->record->fresh(), ['limit' => (int) $data['limit']]);

                    Notification::make()->title($result['message'])->success()->send();
                }),

            Action::make('ideas')
                ->label('Open Blog Ideas')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->url(BlogTopicIdeaResource::getUrl()),
        ];
    }
}
