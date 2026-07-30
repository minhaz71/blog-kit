<?php

namespace App\Filament\Resources\BlogTopicIdeaResource\Pages;

use App\Filament\Resources\BlogTopicIdeaResource;
use App\Models\AiImportBatch;
use App\Services\Ai\LlmClient;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ListBlogTopicIdeas extends ListRecords
{
    protected static string $resource = BlogTopicIdeaResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\FunnelResearchRunsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate ideas')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->modalHeading('Content Cluster & Funnel research')
                ->modalDescription('Researches your products, mines customer pain points and real search queries, designs topic clusters, and parks verified top+middle funnel title ideas here. Products are the bottom funnel; every idea checks against existing articles so nothing similar/canonical-risky is ever suggested.')
                ->schema([
                    TextInput::make('topic_count')
                        ->label('How many title ideas')
                        ->numeric()
                        ->default(100)
                        ->minValue(10)
                        ->maxValue(200)
                        ->required(),
                    Select::make('funnel_rounds')
                        ->label('Verification rounds')
                        ->options([3 => '3 (recommended)', 4 => '4', 5 => '5 (strictest)'])
                        ->default(3)
                        ->native(false)
                        ->helperText('Upper bound. Each round: deterministic duplicate/canonical checks, then an AI editor pass, then regeneration of anything dropped. Stops early (saving tokens) as soon as enough ideas are verified.'),
                    Select::make('provider')
                        ->label('Research provider')
                        ->options(AiImportBatch::PROVIDERS)
                        ->default('anthropic')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('model', null)),
                    Select::make('model')
                        ->label('Research model')
                        ->options(fn (Get $get): array => AiImportBatch::modelOptions($get('provider') ?: 'anthropic'))
                        ->native(false)
                        ->searchable()
                        ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('provider') ?: 'anthropic')),
                    Textarea::make('niche')
                        ->label('Focus niche (optional)')
                        ->rows(2)
                        ->helperText('Leave empty to research the whole catalog.'),
                    Textarea::make('prompt')
                        ->label('Store / brand brief')
                        ->rows(3)
                        ->required()
                        ->default(AiImportBatch::DEFAULT_STORE_BRIEF),
                ])
                ->action(function (array $data): void {
                    $batch = AiImportBatch::create([
                        'kind' => 'blog_ideas',
                        'csv_path' => '',
                        'name' => 'Funnel research — '.now()->format('M j, H:i'),
                        'user_id' => auth()->id(),
                        'prompt' => $data['prompt'],
                        'niche' => $data['niche'] ?? null,
                        'provider' => $data['provider'],
                        'model' => $data['model'] ?? null,
                        'topic_count' => (int) $data['topic_count'],
                        'funnel_rounds' => (int) $data['funnel_rounds'],
                        'link_scope' => 'ecommerce',
                        'status' => 'pending',
                    ]);

                    $launched = \App\Support\BackgroundProcess::artisan(['ai:funnel-research', (string) $batch->id]);

                    if (! $launched) {
                        \App\Jobs\RunFunnelResearchJob::dispatch($batch);
                    }

                    Notification::make()
                        ->title('Funnel research started')
                        ->body($launched
                            ? 'Researching in the background — verified ideas appear here as the run completes. Progress is logged in the AI activity log.'
                            : 'Queued for a worker. Run "php artisan queue:work" (or "composer dev") to process it.')
                        ->success()
                        ->send();
                }),
            Action::make('generateComparisons')
                ->label('Generate comparisons')
                ->icon(Heroicon::OutlinedScale)
                ->color('gray')
                ->modalHeading('Comparison content ("X vs Y")')
                ->modalDescription('Pairs published products that share a category but differ on a real attribute (flavor family, cooling level, tobacco strength) — the pairing is deterministic, never AI-guessed. The AI only writes a title, angle, and outline for each pair, checked against existing articles before it is parked here.')
                ->schema([
                    Select::make('provider')
                        ->label('Research provider')
                        ->options(AiImportBatch::PROVIDERS)
                        ->default('anthropic')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('model', null)),
                    Select::make('model')
                        ->label('Research model')
                        ->options(fn (Get $get): array => AiImportBatch::modelOptions($get('provider') ?: 'anthropic'))
                        ->native(false)
                        ->searchable()
                        ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('provider') ?: 'anthropic')),
                ])
                ->action(function (array $data): void {
                    $batch = AiImportBatch::create([
                        'kind' => 'comparison_ideas',
                        'csv_path' => '',
                        'name' => 'Comparison research — '.now()->format('M j, H:i'),
                        'user_id' => auth()->id(),
                        'prompt' => AiImportBatch::DEFAULT_STORE_BRIEF,
                        'provider' => $data['provider'],
                        'model' => $data['model'] ?? null,
                        'link_scope' => 'ecommerce',
                        'status' => 'pending',
                    ]);

                    $launched = \App\Support\BackgroundProcess::artisan(['ai:comparison-research', (string) $batch->id]);

                    if (! $launched) {
                        \App\Jobs\RunComparisonResearchJob::dispatch($batch);
                    }

                    Notification::make()
                        ->title('Comparison research started')
                        ->body($launched
                            ? 'Pairing products and writing angles in the background — verified comparison ideas appear here as the run completes.'
                            : 'Queued for a worker. Run "php artisan queue:work" (or "composer dev") to process it.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
