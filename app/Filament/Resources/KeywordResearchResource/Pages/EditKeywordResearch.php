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
                    \Filament\Forms\Components\Toggle::make('ai_brief')
                        ->label('Let AI write full briefs (title, pain point, angle, outline)')
                        ->default(fn () => \App\Services\Research\IdeaBriefWriter::provider() !== null)
                        ->disabled(fn () => \App\Services\Research\IdeaBriefWriter::provider() === null)
                        ->helperText(fn () => \App\Services\Research\IdeaBriefWriter::provider() !== null
                            ? 'One AI call per cluster fills a sharp title + pain point, angle and outline for every idea. Turn OFF to add plain keyword-based titles with empty briefs you fill yourself (zero AI cost — edit them in the Blog Ideas table or via CSV).'
                            : 'No AI provider key set — add one in Settings → AI settings to enable. Ideas will be added with plain titles and empty briefs.'),
                ])
                ->action(function (array $data): void {
                    $result = app(PlanBuilder::class)->build($this->record->fresh(), [
                        'limit' => (int) $data['limit'],
                        'ai_brief' => (bool) ($data['ai_brief'] ?? false),
                    ]);

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
