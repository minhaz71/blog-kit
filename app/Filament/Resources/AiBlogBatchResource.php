<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiBlogBatchResource\Pages\CreateAiBlogBatch;
use App\Filament\Resources\AiBlogBatchResource\Pages\EditAiBlogBatch;
use App\Filament\Resources\AiBlogBatchResource\Pages\ListAiBlogBatches;
use App\Models\AiImportBatch;
use App\Models\AiUsageLog;
use App\Services\Ai\LlmClient;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The blog twin of the AI Product Publisher — same engine (batches, live
 * monitor, cost tracking, review loop), pointed at articles. The AI plans
 * a topic cluster from your niche, or writes the exact titles you give it.
 */
class AiBlogBatchResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = AiImportBatch::class;

    protected static ?string $slug = 'ai-blog-batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'AI Blog Writer';

    protected static ?string $label = 'AI blog batch';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('kind', 'blog');
    }

    /**
     * Checkbox options for "Publish to": this install (the `local` sentinel)
     * first, then every active connected site keyed by its ID.
     */
    public static function siteCheckboxOptions(): array
    {
        $localName = (string) setting('general.site_name', config('app.name'));

        return [\App\Services\Network\NetworkTargets::LOCAL => "This site — {$localName} (local)"]
            + \App\Models\ConnectedSite::query()->active()->orderBy('id')->pluck('name', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('kind')->default('blog'),
            Hidden::make('user_id')->default(fn () => auth()->id()),

            // Layout: the brief leads full-width (two balanced columns
            // inside so fields never trail off into empty space), then the
            // engine spans the bottom in three columns.
            Section::make('What to write')
                ->icon(Heroicon::OutlinedLightBulb)
                ->iconColor('warning')
                ->description('Give a niche and the AI designs a pillar-and-spoke topic cluster around it (deduplicated against your existing posts). Or paste your own titles — then it writes exactly those.')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Batch name')
                        ->required()
                        ->placeholder('July cluster — heated tobacco guides'),
                    Select::make('blog_category_id')
                        ->label('Blog category')
                        ->options(fn () => \App\Models\PostCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->helperText('Every article in the batch is filed here.'),
                    Textarea::make('niche')
                        ->label('Niche / topic area')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder('IQOS ILUMA devices and TEREA sticks in the UAE: usage, flavors, comparisons, buying guidance')
                        ->helperText('The AI builds the topic cluster from this. Required unless you provide titles below.')
                        ->requiredWithout('topic_ideas'),
                    Select::make('topic_count')
                        ->label('How many articles')
                        ->options([3 => '3', 5 => '5', 8 => '8', 10 => '10', 15 => '15', 20 => '20'])
                        ->default(5)
                        ->native(false)
                        ->helperText('Used when the AI plans the cluster (1 pillar + spokes). Ignored when you give titles.'),
                    Grid::make(2)->schema([
                        TextInput::make('target_country')->label('Target country')->placeholder('United Arab Emirates')->default('United Arab Emirates'),
                        TextInput::make('target_city')->label('Target city')->placeholder('Dubai')->default('Dubai'),
                    ]),
                    Textarea::make('topic_ideas')
                        ->label('Your title ideas (optional)')
                        ->rows(4)
                        ->columnSpanFull()
                        ->placeholder("TEREA Amber vs Sienna: which flavor suits you\nHow to clean an IQOS ILUMA properly\nAre Japan-edition TEREA sticks different?")
                        ->helperText('One title per line. When filled, the AI skips planning and writes exactly these.'),
                    Textarea::make('prompt')
                        ->label('Store / brand brief')
                        ->rows(4)
                        ->required()
                        ->columnSpan(1)
                        ->default(AiImportBatch::DEFAULT_STORE_BRIEF)
                        ->helperText('Context every article gets: who you are, who reads it, tone, non-negotiable rules. Sent once per batch and cached.'),
                    \Filament\Forms\Components\FileUpload::make('csv_path')
                        ->label('Bulk CSV of article briefs (optional)')
                        ->disk('local')
                        ->directory('ai-imports')
                        ->columnSpan(1)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->helperText('One article per row. Columns: title, keywords (comma-separated, first = primary), country, city, niche, details, angle, publish_date, publish_time. Date only (e.g. 2026-08-01) publishes at 00:00; add publish_time (e.g. 14:30) or a full datetime to publish at that exact time. Extra columns are passed to the AI as research context. A CSV overrides both the niche plan and the title list.'),
                ]),

            Section::make('AI engine — Writer & Reviewer')
                ->icon(Heroicon::OutlinedCpuChip)
                ->iconColor('success')
                ->description('Multi-agent, same as the product publisher: the writer drafts, a separate (cheaper) reviewer critiques against the blog rulebook, the writer rewrites, then the deterministic quality gate decides.')
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
                        ->afterStateUpdated(fn (Set $set) => $set('model', null)),
                    Select::make('model')
                        ->label('Writer model')
                        ->options(fn (Get $get): array => AiImportBatch::modelOptions($get('provider') ?: 'anthropic'))
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('provider') ?: 'anthropic'))
                        ->helperText(function (Get $get): string {
                            $model = $get('model') ?: LlmClient::defaultModel($get('provider') ?: 'anthropic');
                            [$in, $out, $cache] = AiUsageLog::priceFor($model);

                            return $in > 0 || $out > 0
                                ? "\${$in} / 1M input tokens · \${$out} / 1M output tokens · \${$cache} / 1M cached input."
                                : 'Pricing not listed for this model — usage is still tracked and logged.';
                        }),
                    Select::make('reviewer_provider')
                        ->label('Reviewer provider')
                        ->options(AiImportBatch::PROVIDERS)
                        ->default('openai')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('reviewer_model', null)),
                    Select::make('reviewer_model')
                        ->label('Reviewer model')
                        ->options(fn (Get $get): array => AiImportBatch::modelOptions($get('reviewer_provider') ?: 'openai'))
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('reviewer_provider') ?: 'openai')),
                    Select::make('review_passes')
                        ->label('Max review→fix cycles')
                        ->options([1 => '1 (cheapest)', 2 => '2', 3 => '3 (recommended)', 4 => '4'])
                        ->default(3)
                        ->native(false),
                    Select::make('publish_mode')
                        ->options(['draft' => 'Save as drafts (review first)', 'publish' => 'Publish immediately'])
                        ->default('draft')
                        ->native(false),
                    Select::make('publish_interval_minutes')
                        ->label('Delay between articles')
                        ->options(AiImportBatch::PUBLISH_INTERVALS)
                        ->native(false)
                        ->placeholder('No delay — all live as soon as written')
                        ->helperText('Staggers publishing: article 1 at batch start, article 2 one interval later, and so on ("scheduled" until its time; the blog cron publishes each on schedule). A publish_date column in the CSV overrides this per article. Applies in publish mode.'),
                    Select::make('link_scope')
                        ->label('Internal linking targets')
                        ->options(fn (): array => ecommerce_enabled() ? [
                            'ecommerce' => 'Ecommerce — products, product categories, blog, blog categories, home',
                            'blog_only' => 'Blog only — other articles and blog categories',
                        ] : [
                            'blog_only' => 'Blog only — other articles and blog categories',
                        ])
                        // Default to blog-only unless the store module is on, so a
                        // pure blog never links into a (non-existent) catalog.
                        ->default(fn (): string => ecommerce_enabled() ? 'ecommerce' : 'blog_only')
                        ->native(false)
                        ->helperText('What the AI may link to inside each article, with keyword-bearing anchors. Blog-only keeps links within the blog; ecommerce mode also routes readers to relevant products and categories.'),
                    Toggle::make('require_approval')
                        ->label('Hold articles that fail review')
                        ->default(true)
                        ->inline(false)
                        ->helperText('On: an article the reviewer never approves is saved as a DRAFT post labeled "needs review" (never lost — publish it from Content → Posts or via Approve & publish on the item). Off: publish the best version after the last cycle.'),
                ]),
            Section::make('Multisite publishing')
                ->description('Choose which sites each article publishes to — this site and any connected sites are all checkboxes. Untick "This site" to write for the connected sites only (the article stays off this blog). A per-row "site_ids" column in the CSV (e.g. "local,2,5" or "all") overrides this default for that article.')
                ->visible(fn (): bool => is_network_hub())
                ->schema([
                    \Filament\Forms\Components\CheckboxList::make('network_site_ids')
                        ->label('Publish to')
                        ->options(fn (): array => self::siteCheckboxOptions())
                        ->descriptions([
                            \App\Services\Network\NetworkTargets::LOCAL => 'Publish on this install (Hemdox Blog Kit).',
                        ])
                        ->default([\App\Services\Network\NetworkTargets::LOCAL])
                        ->bulkToggleable()
                        ->columns(2)
                        ->helperText(fn (): string => \App\Models\ConnectedSite::query()->active()->exists()
                            ? 'Ticked sites each receive the article. Connected sites are pushed in the background after it is written.'
                            : 'No connected sites yet — add them under Network → Connected Sites. Until then, articles publish to this site.'),
                ]),
            Section::make('AI thumbnail image')
                ->description('Generate a thumbnail from each article\'s title with one image request (no revision). A per-row "generate_image" CSV column (yes/no) overrides this. Set the provider/model in Settings → AI settings (recommended: OpenAI gpt-image-1).')
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\Toggle::make('generate_images')
                        ->label('Generate a thumbnail for each article')
                        ->inline(false)
                        ->helperText(fn (): string => \App\Services\Ai\ImageGenerator::isConfigured()
                            ? 'Image provider configured ('.\App\Services\Ai\ImageGenerator::provider().').'
                            : '⚠ No image provider key set yet — add one in Settings → AI settings.'),
                    \Filament\Forms\Components\TextInput::make('image_style')
                        ->label('Image style (optional)')
                        ->placeholder('modern flat editorial illustration, soft lighting')
                        ->helperText('Appended to the title-based prompt. A per-row "image_style" column overrides this.'),
                ]),
            Section::make('Affiliate content')
                ->description('Turn these into affiliate/product-recommendation articles: the writer adds an FTC disclosure, honest pros/cons reviews, and a call-to-action button per product; affiliate links get rel="sponsored nofollow" automatically. Put each article\'s links in a CSV "affiliate_links" column ("Product Name | https://aff.link" separated by ; or new lines).')
                ->schema([
                    \Filament\Forms\Components\Toggle::make('affiliate_mode')
                        ->label('This is an affiliate blog (affiliate content)')
                        ->inline(false)
                        ->helperText('A CSV row with an "affiliate_links" column is treated as affiliate content even if this is off.'),
                    \Filament\Forms\Components\Textarea::make('affiliate_disclosure')
                        ->label('Affiliate disclosure (optional)')
                        ->rows(2)
                        ->placeholder(\App\Services\Ai\BlogWriter::DEFAULT_AFFILIATE_DISCLOSURE)
                        ->helperText('Shown near the top of every affiliate article. Leave blank for the default.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                TextColumn::make('niche')->limit(40)->placeholder('Own titles')->toggleable(),
                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => AiImportBatch::PROVIDERS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'anthropic' => 'primary',
                        'openai' => 'success',
                        'gemini' => 'info',
                        default => 'gray',
                    }),
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
                    ->url(fn (AiImportBatch $record): string => AiImportBatchResource::getUrl('monitor', ['record' => $record])),
                \Filament\Actions\Action::make('start')
                    ->label(fn (AiImportBatch $record) => $record->status === 'pending' ? 'Start' : 'Restart failed')
                    ->icon(fn (AiImportBatch $record) => $record->status === 'pending' ? Heroicon::OutlinedPlay : Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->visible(fn (AiImportBatch $record) => in_array($record->status, ['pending', 'completed', 'failed']))
                    ->requiresConfirmation()
                    ->modalDescription('Plans the topics (if needed) and writes EVERY article start to finish in the background. Open the Live Monitor to watch. You only click this once.')
                    ->action(function (AiImportBatch $record): void {
                        $retry = $record->status !== 'pending';

                        $args = ['ai:run-batch', (string) $record->id];
                        if ($retry) {
                            $args[] = '--retry-failed';
                        }

                        $launched = \App\Support\BackgroundProcess::artisan($args);

                        if (! $launched) {
                            // No process spawning available → plan inline and
                            // queue the items for a worker.
                            if ($record->total_items === 0 && ! $record->items()->exists()) {
                                \App\Jobs\PlanAiBlogBatch::dispatchSync($record);
                                $record->refresh();
                            }
                            $record->update(['status' => 'processing', 'failed_items' => 0]);
                            foreach ($record->items()->whereIn('status', ['pending', 'failed'])->pluck('id') as $id) {
                                $record->items()->whereKey($id)->update(['status' => 'pending', 'error' => null]);
                                \App\Jobs\WriteAiBlogPost::dispatch($id);
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title($launched ? 'Batch started — planning and writing all articles in the background' : 'Batch queued')
                            ->body($launched
                                ? 'Open the Live Monitor to watch it plan the cluster and write every article. No need to click again.'
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
        // Same items list as the product publisher — Re-run, Approve &
        // publish, and the created-post link all live here.
        return [
            AiImportBatchResource\RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiBlogBatches::route('/'),
            'create' => CreateAiBlogBatch::route('/create'),
            'edit' => EditAiBlogBatch::route('/{record}/edit'),
        ];
    }
}
