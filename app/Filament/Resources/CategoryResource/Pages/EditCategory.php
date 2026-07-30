<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\AiImportBatch;
use App\Models\AiUsageLog;
use App\Models\Category;
use App\Services\Ai\CategoryWriter;
use App\Services\Ai\LlmClient;
use App\Support\BackgroundProcess;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Artisan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('writeWithAi')
                ->label('Write with AI')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('info')
                ->visible(fn (): bool => CategoryWriter::available())
                ->schema([
                    Select::make('provider')
                        ->options(collect(CategoryWriter::PROVIDERS)
                            ->filter(fn ($label, $key) => filled(setting("ai.{$key}_api_key")))
                            ->all())
                        ->default(fn () => filled(setting('ai.anthropic_api_key')) ? 'anthropic' : null)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('model', null)),
                    Select::make('model')
                        ->options(fn (Get $get): array => AiImportBatch::modelOptions($get('provider') ?: 'anthropic'))
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('provider') ?: 'anthropic'))
                        ->helperText(function (Get $get): string {
                            $provider = $get('provider') ?: 'anthropic';
                            $model = $get('model') ?: LlmClient::defaultModel($provider);
                            [$inPrice, $outPrice, $cachePrice] = AiUsageLog::priceFor($model);

                            return $inPrice <= 0 && $outPrice <= 0
                                ? 'Pricing not listed for this model — usage is still tracked and logged.'
                                : "\${$inPrice} / 1M input tokens · \${$outPrice} / 1M output tokens · \${$cachePrice} / 1M cached input.";
                        }),
                    Select::make('reviewer_provider')
                        ->label('Reviewer')
                        ->options(['' => 'Automated checks only (no AI reviewer)'] + collect(CategoryWriter::PROVIDERS)
                            ->filter(fn ($label, $key) => filled(setting("ai.{$key}_api_key")))
                            ->all())
                        // Cross-check by default: a DIFFERENT provider than the
                        // writer when one is configured, else the same one.
                        ->default(function (): string {
                            foreach (['openai', 'gemini', 'anthropic'] as $provider) {
                                if (filled(setting("ai.{$provider}_api_key"))) {
                                    return $provider;
                                }
                            }

                            return '';
                        })
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('reviewer_model', null))
                        ->helperText('An independent model critiques the draft (grounding, E-E-A-T, SEO, style) and the writer fixes — like the product batches. The automated hard-rule check always runs either way.'),
                    Select::make('reviewer_model')
                        ->options(fn (Get $get): array => $get('reviewer_provider')
                            ? AiImportBatch::modelOptions((string) $get('reviewer_provider'))
                            : [])
                        ->native(false)
                        ->searchable()
                        ->visible(fn (Get $get): bool => filled($get('reviewer_provider')))
                        ->placeholder(fn (Get $get): string => 'Provider default — '
                            .LlmClient::defaultModel((string) ($get('reviewer_provider') ?: 'anthropic')))
                        ->helperText('Critique output is short, so a cheap fast model (e.g. GPT-4o mini / Haiku) is ideal here.'),
                    Select::make('passes')
                        ->label('Max review→fix cycles')
                        ->options([1 => '1 (recommended — you review the result anyway)', 2 => '2', 3 => '3 (most thorough, slowest)'])
                        ->default(1)
                        ->required()
                        ->native(false)
                        ->helperText('Each cycle: reviewer + hard-rule check critique the draft, the writer rewrites to fix. Runs in the background; the hard rules have the final word (reviewer nitpicks never block).'),
                    Textarea::make('notes')
                        ->label('Notes for the AI (optional)')
                        ->rows(3)
                        ->placeholder('e.g. emphasise the new Indonesian arrivals; mention Ramadan delivery hours'),
                ])
                ->modalHeading('Write this category page with AI')
                ->modalDescription('The agent reads every product in this category, analyzes how top UAE category pages present this product type, researches the keywords, and writes fresh E-E-A-T content + SEO title & meta. It runs in the BACKGROUND (the dashboard stays fast) and REPLACES the current content block, short description and SEO meta when done. FAQs are only added when the category has none.')
                ->modalSubmitActionLabel('Start writing')
                ->action(function (Category $record, array $data): void {
                    // Run the multi-minute LLM work in a DETACHED process so
                    // this request (and the DB-backed session it holds)
                    // returns in milliseconds — the dashboard never freezes.
                    CategoryWriter::setStatus($record->id, 'running', 'Queued — starting the writer…');

                    $options = array_filter([
                        '--provider' => (string) $data['provider'],
                        '--model' => $data['model'] ?: null,
                        '--reviewer' => $data['reviewer_provider'] ?: null,
                        '--reviewer-model' => $data['reviewer_model'] ?? null ?: null,
                        '--passes' => (string) ($data['passes'] ?? 1),
                        '--notes' => $data['notes'] ?? null ?: null,
                        '--user' => (string) auth()->id(),
                    ], fn ($v) => $v !== null);

                    $args = ['category:write', (string) $record->id];
                    foreach ($options as $flag => $value) {
                        $args[] = "{$flag}={$value}";
                    }

                    $launched = BackgroundProcess::artisan($args);

                    if (! $launched) {
                        // No detached process (e.g. platform without nohup) —
                        // run inline as a last resort so the feature still
                        // works. Slower, but correct.
                        Artisan::call('category:write', ['category' => $record->id] + $options);
                        $this->redirect(CategoryResource::getUrl('edit', ['record' => $record]));

                        return;
                    }

                    Notification::make()
                        ->title('AI is writing in the background')
                        ->body('The dashboard stays responsive. Click “AI status” in a minute or two to load the result.')
                        ->info()
                        ->persistent()
                        ->send();
                }),
            Action::make('aiStatus')
                ->label(fn (Category $record): string => match (CategoryWriter::status($record->id)['status'] ?? null) {
                    'running' => 'AI writing… check',
                    'done' => 'AI finished — load result',
                    'failed' => 'AI failed — details',
                    default => 'AI status',
                })
                ->icon(Heroicon::OutlinedArrowPath)
                ->color(fn (Category $record): string => match (CategoryWriter::status($record->id)['status'] ?? null) {
                    'done' => 'success', 'failed' => 'danger', default => 'gray',
                })
                ->visible(fn (Category $record): bool => CategoryWriter::status($record->id) !== null)
                ->action(function (Category $record): void {
                    $status = CategoryWriter::status($record->id);

                    match ($status['status'] ?? null) {
                        'done' => tap($this, function () use ($record, $status): void {
                            Notification::make()->title('Category written ✓')->body($status['message'])->success()->send();
                            CategoryWriter::clearStatus($record->id);
                            $this->redirect(CategoryResource::getUrl('edit', ['record' => $record]));
                        }),
                        'failed' => tap($this, function () use ($record, $status): void {
                            Notification::make()->title('AI writing failed — nothing was changed')
                                ->body($status['message'])->danger()->persistent()->send();
                            CategoryWriter::clearStatus($record->id);
                        }),
                        default => Notification::make()->title('Still writing…')
                            ->body('The AI is still working on this category. Check again in a moment.')->info()->send(),
                    };
                }),
            DeleteAction::make(),
        ];
    }
}
