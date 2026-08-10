<?php

namespace App\Filament\Resources;

use App\Models\AiImportBatch;
use App\Models\BlogTopicIdea;
use App\Services\Ai\BlogPlanner;
use App\Services\Ai\LlmClient;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The Content Cluster & Funnel Builder's WAITING AREA.
 *
 * "Generate ideas" researches the store's products, mines customer pain
 * points and real queries, designs clusters, and parks 3-5×-verified
 * top/middle-funnel title ideas here (products = bottom funnel). Nothing
 * is written until the admin selects ideas (all, several, or one) and
 * sends them to the existing AI blog writer engine.
 */
class BlogTopicIdeaResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = BlogTopicIdea::class;

    protected static ?string $slug = 'blog-topic-ideas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Blog Ideas (Funnel)';

    protected static ?string $label = 'blog topic idea';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $waiting = BlogTopicIdea::query()->where('status', 'waiting')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(120),
            TextInput::make('primary_keyword'),
            Textarea::make('pain_point')->rows(2),
            Textarea::make('angle')->rows(2),
            Textarea::make('outline_text')
                ->label('Outline (one section per line)')
                ->rows(5)
                ->afterStateHydrated(function (Textarea $component, $record) {
                    $component->state(implode("\n", (array) ($record?->outline ?? [])));
                })
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->wrap()->weight('semibold')
                    ->description(fn (BlogTopicIdea $r) => $r->pain_point ? 'Pain: '.mb_substr($r->pain_point, 0, 90) : null),
                TextColumn::make('funnel_stage')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'top' => 'info', 'middle' => 'warning', 'bottom' => 'danger', default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'top' => 'Top funnel', 'middle' => 'Middle funnel', 'bottom' => 'Bottom funnel', default => $state,
                    }),
                TextColumn::make('cluster')->badge()->color('gray')->searchable(),
                TextColumn::make('role')->badge()->color(fn (string $state) => match ($state) {
                    'pillar' => 'success', 'comparison' => 'warning', default => 'gray',
                }),
                TextColumn::make('site.name')->label('For site')->badge()->color('info')
                    ->placeholder('This site')->visible(fn () => network_enabled() && is_network_hub()),
                TextColumn::make('primary_keyword')->toggleable()->searchable(),
                TextColumn::make('verified_rounds')->label('Checks')->alignCenter(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'waiting' => 'info', 'queued' => 'warning', 'written' => 'success', default => 'gray',
                }),
                TextColumn::make('created_at')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'waiting' => 'Waiting', 'queued' => 'Queued', 'written' => 'Written', 'dismissed' => 'Dismissed',
                ])->default('waiting'),
                SelectFilter::make('funnel_stage')->options(['top' => 'Top funnel', 'middle' => 'Middle funnel', 'bottom' => 'Bottom funnel']),
                SelectFilter::make('role')->options([
                    'pillar' => 'Pillar', 'spoke' => 'Spoke', 'comparison' => 'Comparison',
                ]),
                SelectFilter::make('cluster')->options(
                    fn () => BlogTopicIdea::query()->distinct()->orderBy('cluster')->pluck('cluster', 'cluster')->all()
                )->searchable(),
                SelectFilter::make('site_id')->label('Target site')
                    ->options(fn () => ['local' => 'This site'] + \App\Models\ConnectedSite::query()
                        ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? ($data['value'] === 'local' ? $query->whereNull('site_id') : $query->where('site_id', $data['value']))
                        : $query)
                    ->visible(fn () => network_enabled() && is_network_hub()),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->tooltip('Download every idea (except dismissed) as a CSV to edit the briefs in Excel, then re-import.')
                    ->action(function () {
                        $ideas = BlogTopicIdea::query()->where('status', '!=', 'dismissed')->orderByDesc('id')->get();
                        $csv = \App\Services\Ai\BlogIdeaCsv::export($ideas);

                        return response()->streamDownload(fn () => print($csv), 'blog-ideas-'.now()->format('Y-m-d-His').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
                \Filament\Actions\Action::make('importCsv')
                    ->label('Import CSV')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->modalDescription('Upload a CSV exported from here (or built to the same columns). Rows with an "id" UPDATE that idea; a blank "id" CREATES a new one. Outline and secondary_keywords are pipe-separated ("A | B | C").')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('file')
                            ->label('CSV file')
                            ->disk('local')
                            ->directory('idea-imports')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);
                        $res = \App\Services\Ai\BlogIdeaCsv::import($path);
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title('Import complete')
                            ->body("{$res['updated']} updated · {$res['created']} created · {$res['skipped']} skipped.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('send')
                    ->label('Send to writer')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('primary')
                    ->visible(fn (BlogTopicIdea $r) => $r->status === 'waiting')
                    ->schema(self::writerForm())
                    ->action(function (BlogTopicIdea $record, array $data): void {
                        $batch = self::sendToWriter(collect([$record]), $data);
                        Notification::make()->title('Sent to the AI writer')
                            ->body("1 article queued in batch \"{$batch->name}\". Watch it in AI Blog Writer → Live Monitor.")
                            ->success()->send();
                    }),
                \Filament\Actions\EditAction::make()->color('gray'),
                \Filament\Actions\Action::make('dismiss')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (BlogTopicIdea $r) => $r->status === 'waiting')
                    ->action(fn (BlogTopicIdea $r) => $r->update(['status' => 'dismissed'])),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkAction::make('sendSelected')
                    ->label('Send selected to writer')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('primary')
                    ->schema(self::writerForm())
                    ->action(function (Collection $records, array $data): void {
                        $waiting = $records->where('status', 'waiting')->values();

                        if ($waiting->isEmpty()) {
                            Notification::make()->title('Nothing to send')->body('Only ideas with status "waiting" can be sent.')->warning()->send();

                            return;
                        }

                        $batch = self::sendToWriter($waiting, $data);
                        Notification::make()->title('Sent to the AI writer')
                            ->body("{$waiting->count()} article(s) queued in batch \"{$batch->name}\". Watch them in AI Blog Writer → Live Monitor.")
                            ->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                \Filament\Actions\BulkAction::make('exportSelected')
                    ->label('Export selected to CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(function (Collection $records) {
                        $csv = \App\Services\Ai\BlogIdeaCsv::export($records);

                        return response()->streamDownload(fn () => print($csv), 'blog-ideas-selected-'.now()->format('Y-m-d-His').'.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),
                \Filament\Actions\BulkAction::make('dismissSelected')
                    ->label('Dismiss selected')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'dismissed']))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** The small "how to write these" form shown on send (single + bulk). */
    protected static function writerForm(): array
    {
        return [
            Select::make('blog_category_id')
                ->label('Blog category')
                ->options(fn () => \App\Models\PostCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->native(false),
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
                ->placeholder(fn (Get $get): string => 'Provider default — '.LlmClient::defaultModel($get('provider') ?: 'anthropic')),
            Select::make('review_passes')
                ->label('Review → fix cycles')
                ->options([1 => '1 (cheapest)', 2 => '2', 3 => '3 (recommended)', 4 => '4'])
                ->default(3)
                ->native(false)
                ->helperText('How many times a reviewer critiques and the writer rewrites before publishing. More cycles = higher quality and higher cost.'),
            Select::make('publish_mode')
                ->options(['draft' => 'Save as drafts (review first)', 'publish' => 'Publish immediately'])
                ->default('draft')
                ->native(false),
            Select::make('publish_interval_minutes')
                ->label('Delay between articles')
                ->options(AiImportBatch::PUBLISH_INTERVALS)
                ->native(false)
                ->placeholder('No delay — all live as soon as written')
                ->helperText('Staggers publishing: first article at batch start, each next one interval later (the blog cron publishes them on schedule). Applies in publish mode.'),
            \Filament\Forms\Components\Toggle::make('generate_images')
                ->label('Generate AI thumbnails')
                ->default(true)
                ->helperText('Create a featured image for each article with your configured image provider. Generated BEFORE the network push, so it ships to connected sites too.'),
            Select::make('image_style')
                ->label('Thumbnail style')
                ->options(collect(\App\Services\Ai\ThumbnailService::STYLE_PRESETS)->keys()->mapWithKeys(fn ($k) => [$k => ucfirst($k)])->all())
                ->default('editorial')
                ->native(false)
                ->visible(fn (Get $get): bool => (bool) $get('generate_images')),
        ];
    }

    /**
     * Turn selected ideas into a normal blog batch with PRE-BUILT items —
     * the full researched brief rides each row into the proven writer
     * pipeline (planner skipped; the research already happened here).
     */
    public static function sendToWriter(Collection $ideas, array $data): AiImportBatch
    {
        $brief = AiImportBatch::DEFAULT_STORE_BRIEF;

        // Which sites does THIS batch target, as a whole? Each idea was
        // researched for one site (its spoke `site_id`, or local). Aggregate the
        // distinct set onto the batch so the "Target" column and Live monitor
        // show the real destination(s) — e.g. "puff" — instead of defaulting to
        // "This site". The per-item row['site_ids'] still drives actual routing.
        $batchTargets = $ideas
            ->map(fn ($idea) => $idea->site_id ? (int) $idea->site_id : \App\Services\Network\NetworkTargets::LOCAL)
            ->unique()
            ->values()
            ->all();

        $batch = AiImportBatch::create([
            'kind' => 'blog',
            'csv_path' => '',
            'name' => 'Funnel articles — '.now()->format('M j, H:i'),
            'user_id' => auth()->id(),
            'prompt' => $brief,
            'provider' => $data['provider'] ?? 'anthropic',
            'model' => $data['model'] ?? null,
            'reviewer_provider' => $data['provider'] ?? 'anthropic',
            'review_passes' => (int) ($data['review_passes'] ?? 3),
            'blog_category_id' => $data['blog_category_id'] ?? null,
            'publish_mode' => $data['publish_mode'] ?? 'draft',
            'publish_interval_minutes' => $data['publish_interval_minutes'] ?? null,
            'generate_images' => $data['generate_images'] ?? true,
            'image_style' => $data['image_style'] ?? null,
            'network_site_ids' => $batchTargets,
            'link_scope' => 'ecommerce',
            'funnel_rounds' => (int) $ideas->max('verified_rounds'), // marks it a funnel batch
            'status' => 'processing',
            'link_catalog' => (new BlogPlanner)->buildLinkCatalog('ecommerce'), // cached
        ]);

        foreach ($ideas as $idea) {
            $item = $batch->items()->create([
                'row' => [
                    'name' => $idea->title,
                    'keywords' => implode(', ', array_filter(array_merge(
                        [$idea->primary_keyword],
                        (array) $idea->secondary_keywords
                    ))),
                    'angle' => (string) $idea->angle,
                    'outline' => implode(' | ', (array) $idea->outline),
                    'funnel_stage' => $idea->funnel_stage,
                    'cluster' => $idea->cluster,
                    'role' => $idea->role,
                    'primary_keyword' => (string) $idea->primary_keyword,
                    // Multisite: an idea researched for a spoke carries its target
                    // so WriteAiBlogPost fans the finished article out to that site.
                    'site_ids' => $idea->site_id ? (string) $idea->site_id : 'local',
                    'pain_point' => (string) $idea->pain_point,
                    'search_query' => (string) $idea->search_query,
                    'audience_need' => (string) $idea->audience_need,
                    'required_links' => implode(', ', (array) $idea->link_targets),
                    'idea_id' => (string) $idea->id,
                    'compared_product_ids' => (array) $idea->compared_product_ids,
                    'compared_products' => self::comparedProductsBrief($idea->compared_product_ids),
                ],
                'status' => 'pending',
            ]);

            // Items are created 'pending'; the batch RUNNER (below) writes them.
            // Never dispatch the heavy writer inline here — on a sync queue that
            // would run every LLM write inside the web request and time out.
            $idea->update(['status' => 'queued', 'writer_batch_id' => $batch->id]);
        }

        $batch->forceFill(['total_items' => $ideas->count(), 'topic_count' => $ideas->count()])->save();

        \App\Models\AiActivityLog::write($batch->id, null, 'plan',
            '🧠 Plan ready — '.$ideas->count().' article(s) from the funnel waiting area (research pre-attached).', 'success');

        // Write in a DETACHED background process (no web-request timeout). Falls
        // back to queued jobs only if a process can't be spawned.
        $launched = \App\Support\BackgroundProcess::artisan(['ai:run-batch', (string) $batch->id]);
        if (! $launched) {
            foreach ($batch->items()->pluck('id') as $itemId) {
                \App\Jobs\WriteAiBlogPost::dispatch($itemId);
            }
        }

        return $batch;
    }

    /**
     * Compact per-product fact sheet (name + resolved attribute facets) for
     * a comparison idea's pair, so the writer works from real structured
     * data instead of guessing at what actually differs.
     */
    protected static function comparedProductsBrief(?array $productIds): string
    {
        if (empty($productIds)) {
            return '';
        }

        return \App\Models\Product::query()->whereIn('id', $productIds)
            ->with('attributeValues.attribute')
            ->get()
            ->map(function ($product) {
                $facts = $product->attributeValues
                    ->filter(fn ($v) => $v->attribute)
                    ->map(fn ($v) => $v->attribute->name.': '.$v->value)
                    ->implode(', ');

                return $product->name.($facts !== '' ? " ({$facts})" : '');
            })
            ->implode(' vs ');
    }

    public static function getPages(): array
    {
        return [
            'index' => BlogTopicIdeaResource\Pages\ListBlogTopicIdeas::route('/'),
        ];
    }
}
