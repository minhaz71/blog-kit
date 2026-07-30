<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiImportBatchResource\Pages\CreateAiImportBatch;
use App\Filament\Resources\AiImportBatchResource\Pages\EditAiImportBatch;
use App\Filament\Resources\AiImportBatchResource\Pages\ListAiImportBatches;
use App\Filament\Resources\AiImportBatchResource\Pages\MonitorAiImportBatch;
use App\Jobs\StartAiImportBatch;
use App\Models\AiImportBatch;
use App\Models\AiUsageLog;
use App\Services\Ai\LlmClient;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class AiImportBatchResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = AiImportBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 9;

    protected static ?string $label = 'AI product publisher';

    protected static ?string $pluralLabel = 'AI product publisher';

    public static function getNavigationBadge(): ?string
    {
        $active = AiImportBatch::whereIn('status', ['processing', 'linking'])->count();

        return $active > 0 ? (string) $active : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Layout: Import leads full-width (it is THE action); below it a
            // balanced pair — short cards stacked left, tall style card
            // right; the engine spans the bottom. No floating voids.
            Section::make('Import')
                ->icon(Heroicon::OutlinedDocumentArrowUp)
                ->iconColor('primary')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->placeholder('July catalog import'),
                    FileUpload::make('csv_path')
                        ->label('CSV file')
                        ->required()
                        ->disk('local')
                        ->directory('ai-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->helperText('Columns: name, regular_price, sale_price, short_description, specifications, brand, category, category_id, keywords (comma-separated, first = primary). category = names, multiple with | (created if new); category_id = exact existing IDs from Catalog → Categories, multiple with | — pins products to precise categories, immune to typos/renames. The writer also receives the category\'s parent chain, so copy fits the range. Extra columns are passed to the AI as context. Images come from the Drive folder below, not the CSV. Grab the sample CSV from the list page to start.'),
                    Textarea::make('prompt')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull()
                        ->hintAction(
                            \Filament\Actions\Action::make('useTemplate')
                                ->label('Use detailed template')
                                ->icon(Heroicon::OutlinedDocumentText)
                                ->action(fn (Set $set) => $set('prompt', \App\Services\Ai\ProductWriter::DEFAULT_STORE_PROMPT)),
                        )
                        ->placeholder('Write for a premium vape shop in Dubai. Confident, clean tone. Highlight flavor and battery life. Target keyword = product name + "UAE".')
                        ->helperText('Your writing brief — tone, audience, market positioning, SEO angle. The agent follows this for every product.'),
                ]),
            Group::make([
                Section::make('Market targeting')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->iconColor('info')
                    ->description('Sent once per batch (cached) — the AI localizes copy, keywords, and meta fields for this market.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('target_country')->placeholder('United Arab Emirates'),
                        TextInput::make('target_city')->placeholder('Dubai'),
                        TextInput::make('target_language')->label('Language / locale')->placeholder('English (UK spelling), prices in AED'),
                        TextInput::make('audience_note')->label('Audience')->placeholder('Adult vapers 25-40, value-conscious, mobile shoppers'),
                    ]),
                Section::make('Images & publishing')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->iconColor('gray')
                    ->schema([
                        TextInput::make('drive_folder')
                            ->label('Google Drive folder (link or ID)')
                            ->placeholder('https://drive.google.com/drive/folders/…')
                            ->helperText('Optional. Share the folder as "anyone with the link can view". Each product gets the image whose FILENAME best matches its name — "amber kazakhstan.jpg" matches "IQOS Terea Amber Kazakhstan". Downloaded, renamed to the product slug, alt text set automatically. No close match = no image (never a wrong one).'),
                        Select::make('publish_mode')
                            ->options(['draft' => 'Save as drafts (review first)', 'publish' => 'Publish immediately'])
                            ->default('draft')
                            ->native(false),
                        \Filament\Forms\Components\Toggle::make('require_approval')
                            ->label('Hold products that fail review')
                            ->default(true)
                            ->inline(false)
                            ->helperText('On: a product the reviewer never approves is held as "needs review" and NOT published until you fix it. Off: publish the best version after the last cycle regardless.'),
                    ]),
            ])->columnSpan(1),
            Section::make('Writing style & output format')
                ->columnSpan(1)
                ->icon(Heroicon::OutlinedPencilSquare)
                ->iconColor('warning')
                ->schema([
                    Textarea::make('system_prompt')
                        ->label('System prompt (advanced)')
                        ->rows(5)
                        ->placeholder(\App\Services\Ai\ProductWriter::DEFAULT_SYSTEM)
                        ->helperText('The agent\'s core writing instructions. Leave empty to use the default (shown above) or the global default from AI settings. This block is sent once per batch and cached by the provider — editing it does not multiply token cost.'),
                    Grid::make(2)->schema([
                        Select::make('competitor_count')
                            ->label('Competitors to outperform per product')
                            ->options([0 => 'Off', 2 => '2', 3 => '3 (recommended)', 5 => '5'])
                            ->default(3)
                            ->native(false)
                            ->helperText('The AI positions each product against this many typical market competitors — covering gaps they leave, sharper benefits, search-intent language.'),
                        Select::make('output_format')
                            ->label('HTML output format')
                            ->options(\App\Models\AiImportBatch::OUTPUT_FORMATS)
                            ->default('html_css')
                            ->required()
                            ->live()
                            ->native(false),
                    ]),
                    Textarea::make('custom_classes')
                        ->label('Your CSS classes')
                        ->rows(4)
                        ->visible(fn (Get $get) => $get('output_format') === 'html_classes')
                        ->placeholder("product-intro — opening paragraph\nfeature-list — benefits ul\nspec-table — specifications table\ncta-box — closing call-to-action")
                        ->helperText('Enter once — reused for every product in the batch (and cached by the provider). One class per line, optionally with a hint after a dash.'),
                    Select::make('allowed_tags')
                        ->label('Allowed HTML tags')
                        ->multiple()
                        ->options(\App\Models\AiImportBatch::TAG_OPTIONS)
                        ->placeholder('All standard tags (h2, h3, p, ul, ol, table, blockquote…)')
                        ->helperText('Restrict which tags the AI may use in descriptions. Leave empty for the sensible default set.'),
                ]),
            Section::make('AI engine — Writer & Reviewer')
                ->icon(Heroicon::OutlinedCpuChip)
                ->iconColor('success')
                ->description('Multi-agent: the writer drafts, a separate (cheaper) reviewer critiques, the writer rewrites to fix every issue, then it publishes. Keeping the reviewer on a different provider gives a genuine second opinion.')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    Select::make('provider')
                        ->label('Writer provider')
                        ->options(AiImportBatch::PROVIDERS)
                        ->default('anthropic')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('model', null))
                        ->helperText('Writes and rewrites the copy. Claude recommended. Keys: Settings → AI settings.'),
                    Select::make('model')
                        ->label('Writer model')
                        ->options(fn (Get $get): array => AiImportBatch::modelOptions($get('provider') ?: 'anthropic'))
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('provider') ?: 'anthropic'))
                        ->helperText(function (Get $get): string {
                            $provider = $get('provider') ?: 'anthropic';
                            $model = $get('model') ?: LlmClient::defaultModel($provider);
                            [$inPrice, $outPrice, $cachePrice] = AiUsageLog::priceFor($model);

                            if ($inPrice <= 0 && $outPrice <= 0) {
                                return 'Pricing not listed for this model — usage is still tracked and logged.';
                            }

                            return "\${$inPrice} / 1M input tokens · \${$outPrice} / 1M output tokens · \${$cachePrice} / 1M cached input.";
                        }),
                    Select::make('reviewer_provider')
                        ->label('Reviewer provider')
                        ->options(AiImportBatch::PROVIDERS)
                        ->default('openai')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('reviewer_model', null))
                        ->helperText('Critiques the draft (SEO, E-E-A-T, facts, links, FAQ, tone). A cheap model is ideal — it only writes a short issue list.'),
                    Select::make('reviewer_model')
                        ->label('Reviewer model')
                        ->options(fn (Get $get): array => AiImportBatch::modelOptions($get('reviewer_provider') ?: 'openai'))
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('reviewer_provider') ?: 'openai'))
                        ->helperText(function (Get $get): \Illuminate\Support\HtmlString {
                            $writerProvider = $get('provider') ?: 'anthropic';
                            $writerModel = $get('model') ?: LlmClient::defaultModel($writerProvider);
                            $provider = $get('reviewer_provider') ?: 'openai';
                            $model = $get('reviewer_model') ?: LlmClient::defaultModel($provider);
                            [$inPrice, $outPrice] = AiUsageLog::priceFor($model);

                            $price = $inPrice > 0 || $outPrice > 0
                                ? "\${$inPrice} / 1M input · \${$outPrice} / 1M output."
                                : 'Pricing not listed — usage still tracked.';

                            $mode = ($writerProvider === $provider && $writerModel === $model)
                                ? '<strong style="color:#059669">⚡ Single-model mode:</strong> review + fix run as ONE call per cycle on a shared prompt cache — fewest tokens.'
                                : '<strong style="color:#0f766e">🔀 Cross-check mode:</strong> an independent model critiques (short output = cheap), the writer fixes — strongest quality gate.';

                            return new \Illuminate\Support\HtmlString("{$price}<br>{$mode}");
                        }),
                    Select::make('review_passes')
                        ->label('Max review→fix cycles')
                        ->options([1 => '1 (cheapest)', 2 => '2', 3 => '3 (recommended)', 4 => '4'])
                        ->default(3)
                        ->native(false)
                        ->helperText('Writer↔reviewer rounds before the deterministic quality gate decides. Each extra cycle is a full rewrite (roughly $0.07-0.12 per product). With 1, the copy gets one critique and one fix.'),
                    Select::make('price_mode')
                        ->options(['csv' => 'Use CSV prices as-is', 'ai' => 'Let AI suggest the sale price'])
                        ->default('csv')
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Blog batches live under Content → AI Blog Writer; this list is
            // the product publisher only. (getEloquentQuery stays unfiltered
            // so the shared Live Monitor page opens blog batches too.)
            ->modifyQueryUsing(fn ($query) => $query->where('kind', 'product'))
            ->columns([
                TextColumn::make('name')->searchable()->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => AiImportBatch::PROVIDERS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'anthropic' => 'primary',
                        'openai' => 'success',
                        'gemini' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('model')
                    ->placeholder(fn (AiImportBatch $record) => 'Default — '.LlmClient::defaultModel($record->provider))
                    ->toggleable()
                    ->color('gray'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'completed' => 'success',
                    'processing', 'linking' => 'warning',
                    'paused', 'stopped' => 'info',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('progress')
                    ->state(fn (AiImportBatch $record) => "{$record->done_items}/{$record->total_items}"
                        .($record->failed_items > 0 ? " ({$record->failed_items} failed)" : ''))
                    ->color(fn (AiImportBatch $record) => $record->failed_items > 0 ? 'danger' : 'gray'),
                TextColumn::make('spend')
                    ->label('Cost (USD)')
                    ->state(fn (AiImportBatch $record) => '$'.number_format((float) $record->usageLogs()->sum('cost'), 4))
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('success'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('monitor')
                    ->label('Live monitor')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('info')
                    ->url(fn (AiImportBatch $record): string => static::getUrl('monitor', ['record' => $record])),
                \Filament\Actions\Action::make('start')
                    ->label(fn (AiImportBatch $record) => $record->status === 'pending' ? 'Start' : 'Restart failed')
                    ->icon(fn (AiImportBatch $record) => $record->status === 'pending' ? Heroicon::OutlinedPlay : Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->visible(fn (AiImportBatch $record) => in_array($record->status, ['pending', 'completed', 'failed']))
                    ->requiresConfirmation()
                    ->modalDescription('This runs the whole batch — every product, start to finish — in the background. Open the Live Monitor to watch progress. You only click this once.')
                    ->action(function (AiImportBatch $record): void {
                        $retry = $record->status !== 'pending';

                        // Parse now (synchronously) so items appear in the
                        // monitor immediately; the writing runs in a detached
                        // background process that finishes ALL products.
                        if (! $retry && $record->total_items === 0) {
                            StartAiImportBatch::dispatchSync($record);
                        }

                        $args = ['ai:run-batch', (string) $record->id];
                        if ($retry) {
                            $args[] = '--retry-failed';
                        }

                        $launched = \App\Support\BackgroundProcess::artisan($args);

                        if (! $launched) {
                            // Environment can't spawn a process → fall back to
                            // the queue (needs a worker) so it still proceeds.
                            $record->update(['status' => 'processing']);
                            foreach ($record->items()->whereIn('status', ['pending', 'failed'])->pluck('id') as $id) {
                                $record->items()->whereKey($id)->update(['status' => 'pending', 'error' => null]);
                                \App\Jobs\WriteAiProduct::dispatch($id);
                            }
                            $record->update(['failed_items' => 0]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title($launched ? 'Batch started — processing all products in the background' : 'Batch queued')
                            ->body($launched
                                ? 'Open the Live Monitor to watch it work through every product. No need to click again.'
                                : 'Queued for a worker. Run "php artisan queue:work" (or "composer dev") to process them.')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\EditAction::make()->color('gray'),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            AiImportBatchResource\RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiImportBatches::route('/'),
            'create' => CreateAiImportBatch::route('/create'),
            'edit' => EditAiImportBatch::route('/{record}/edit'),
            'monitor' => MonitorAiImportBatch::route('/{record}/monitor'),
        ];
    }
}
