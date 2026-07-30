<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\VariationsRelationManager;
use App\Filament\Support\Editor;
use App\Filament\Support\ResourceActions;
use App\Filament\Support\SeoForm;
use App\Models\Product;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Product')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                            if ($operation === 'create') {
                                                $set('slug', Str::slug($state ?? ''));
                                            }
                                        }),
                                    TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true),
                                    Select::make('type')
                                        ->options(array_combine(Product::TYPES, array_map(
                                            fn (string $t): string => Str::headline($t),
                                            Product::TYPES,
                                        )))
                                        ->default('simple')
                                        ->required()
                                        ->native(false),
                                    Select::make('brand_id')
                                        ->label('Brand')
                                        ->relationship('brand', 'name')
                                        ->searchable()
                                        ->preload(),
                                    Select::make('categories')
                                        ->relationship('categories', 'name')
                                        ->multiple()
                                        ->searchable()
                                        ->preload(),
                                    Select::make('tags')
                                        ->relationship('tags', 'name')
                                        ->multiple()
                                        ->searchable()
                                        ->preload(),
                                ]),
                                Editor::rich('short_description')
                                    ->columnSpanFull(),
                                Editor::rich('description')
                                    ->columnSpanFull(),
                                KeyValue::make('specifications')
                                    ->keyLabel('Specification')
                                    ->valueLabel('Value')
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Pricing')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('price')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->default(0),
                                    TextInput::make('sale_price')
                                        ->numeric()
                                        ->minValue(0)
                                        ->lt('price')
                                        ->helperText('Must be lower than the regular price.'),
                                    DateTimePicker::make('sale_starts_at')
                                        ->seconds(false),
                                    DateTimePicker::make('sale_ends_at')
                                        ->seconds(false)
                                        ->after('sale_starts_at'),
                                ]),
                            ]),
                        Tab::make('Inventory')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('sku')
                                        ->label('SKU')
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true),
                                    TextInput::make('gtin')
                                        ->label('GTIN / EAN / UPC barcode')
                                        ->maxLength(14)
                                        ->helperText('Optional — strengthens the Google product schema identifier.'),
                                    Toggle::make('manage_stock')
                                        ->default(true)
                                        ->live()
                                        ->inline(false),
                                    TextInput::make('stock_qty')
                                        ->numeric()
                                        ->default(0)
                                        ->visible(fn (Get $get): bool => (bool) $get('manage_stock')),
                                    TextInput::make('low_stock_threshold')
                                        ->numeric()
                                        ->default(5)
                                        ->visible(fn (Get $get): bool => (bool) $get('manage_stock')),
                                    Select::make('stock_status')
                                        ->options([
                                            'in_stock' => 'In stock',
                                            'out_of_stock' => 'Out of stock',
                                            'on_backorder' => 'On backorder',
                                        ])
                                        ->default('in_stock')
                                        ->required()
                                        ->native(false),
                                ]),
                            ]),
                        Tab::make('Shipping')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('weight')
                                        ->numeric()
                                        ->suffix('kg'),
                                    Select::make('shipping_class_id')
                                        ->label('Shipping class')
                                        ->relationship('shippingClass', 'name')
                                        ->preload(),
                                    TextInput::make('length')
                                        ->numeric()
                                        ->suffix('cm'),
                                    TextInput::make('width')
                                        ->numeric()
                                        ->suffix('cm'),
                                    TextInput::make('height')
                                        ->numeric()
                                        ->suffix('cm'),
                                ]),
                            ]),
                        Tab::make('Images')
                            ->schema([
                                FileUpload::make('featured_image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('products')
                                    ->imageEditor()
                                    // Permalink = slug of the ORIGINAL file name, fixed at upload.
                                    ->getUploadedFileNameForStorageUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file, \Filament\Schemas\Components\Utilities\Get $get): string {
                                        $slug = \App\Services\Seo\ImageSeoRules::slugFromOriginalName(
                                            $file->getClientOriginalName(),
                                            (string) ($get('slug') ?: $get('name') ?: 'product'),
                                        );
                                        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

                                        return basename(\App\Services\Seo\ImageSeoRules::uniquePath('products', $slug, $extension));
                                    })
                                    ->helperText('The URL slug comes from the file name ("terea kazakhstan amber.jpg" → /terea-kazakhstan-amber.jpg) and is fixed once uploaded — name files properly first. Alt/title/caption are editable in the Media library after saving.'),
                            ]),
                        Tab::make('Related')
                            ->schema([
                                Select::make('relatedProducts')
                                    ->relationship('relatedProducts', 'name', ignoreRecord: true)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->pivotData(['type' => 'related']),
                                Select::make('upsells')
                                    ->relationship('upsells', 'name', ignoreRecord: true)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->pivotData(['type' => 'upsell']),
                                Select::make('crossSells')
                                    ->label('Cross-sells')
                                    ->relationship('crossSells', 'name', ignoreRecord: true)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->pivotData(['type' => 'cross_sell']),
                            ]),
                        Tab::make('SEO')
                            ->schema(SeoForm::components()),
                        Editor::customCodeTab(),
                        Tab::make('Flags & Status')
                            ->schema([
                                Grid::make(3)->schema([
                                    Toggle::make('is_featured'),
                                    Toggle::make('is_new_arrival'),
                                    Toggle::make('is_best_seller'),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('visibility')
                                        ->options([
                                            'visible' => 'Shop & search',
                                            'catalog' => 'Shop only',
                                            'search' => 'Search only',
                                            'hidden' => 'Hidden',
                                        ])
                                        ->default('visible')
                                        ->required()
                                        ->native(false),
                                    Select::make('status')
                                        ->options([
                                            'draft' => 'Draft',
                                            'published' => 'Published',
                                            'archived' => 'Archived',
                                        ])
                                        ->default('published')
                                        ->required()
                                        ->native(false),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Columns are toggleable ("Screen Options") — click the
                // column-toggle icon in the table header to show/hide any of
                // these per your workflow. Name + status stay pinned.
                ImageColumn::make('featured_image')
                    ->label('Image')
                    ->disk('public')
                    ->square()
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('price')
                    ->money(setting('general.currency', 'USD'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('stock_status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'on_backorder' => 'warning',
                        default => 'danger',
                    })
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),
                SeoForm::scoreColumn(),
                TextColumn::make('seoMeta.title')
                    ->label('SEO title')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seoMeta.description')
                    ->label('SEO description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('type')
                    ->options(array_combine(Product::TYPES, array_map(
                        fn (string $t): string => Str::headline($t),
                        Product::TYPES,
                    ))),
                SelectFilter::make('stock_status')
                    ->options([
                        'in_stock' => 'In stock',
                        'out_of_stock' => 'Out of stock',
                        'on_backorder' => 'On backorder',
                    ]),
                SelectFilter::make('categories')
                    ->label('Category')
                    ->relationship('categories', 'name', modifyQueryUsing: fn ($query) => $query->withCount('products')->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\Category $record): string => "{$record->name} ({$record->products_count})")
                    ->searchable()
                    ->preload(),
                SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                \Filament\Tables\Filters\TernaryFilter::make('has_image')
                    ->label('Image')
                    ->placeholder('All products')
                    ->trueLabel('Has an image')
                    ->falseLabel('Missing image')
                    ->queries(
                        true: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where(fn ($w) => $w
                            ->whereNotNull('featured_image')->where('featured_image', '!=', '')
                            ->orWhereHas('images')),
                        false: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where(fn ($w) => $w
                            ->whereNull('featured_image')->orWhere('featured_image', ''))
                            ->whereDoesntHave('images'),
                    ),
                // Trash lives in the "Trash" tab (see ListProducts::getTabs).
            ])
            ->recordActions([
                \Filament\Actions\Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Product $record): string => \App\Support\Permalinks::product($record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (Product $record): bool => ! $record->trashed()),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()->label('Trash'),
                \Filament\Actions\RestoreAction::make(),
                \Filament\Actions\ForceDeleteAction::make()->label('Delete forever'),
                \Filament\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate product')
                    ->modalDescription('Creates a draft copy including SEO meta, categories, and tags.')
                    ->action(function (Product $record): void {
                        $copy = $record->replicate(['sku']);
                        $copy->name = $record->name.' (copy)';
                        $copy->slug = Str::slug($record->name).'-copy-'.Str::lower(Str::random(4));
                        $copy->status = 'draft';
                        $copy->save();

                        $copy->categories()->sync($record->categories->pluck('id'));
                        $copy->tags()->sync($record->tags->pluck('id'));

                        if ($record->seoMeta) {
                            $copy->seoMeta()->create(
                                $record->seoMeta->only([
                                    'title', 'description', 'focus_keyword', 'og_title',
                                    'og_description', 'og_image', 'twitter_title',
                                    'twitter_description', 'twitter_image', 'schema_enabled',
                                ]) + ['noindex' => true],
                            );
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Product duplicated as draft')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    ResourceActions::refreshWithAi('product'),
                    \Filament\Actions\BulkAction::make('fillImagesFromDrive')
                        ->label('Get images from Drive')
                        ->icon(Heroicon::OutlinedPhoto)
                        ->color('primary')
                        ->modalHeading('Fill product images from a Google Drive folder')
                        ->modalDescription('For each selected product, the best-matching photo in the folder — including any subfolders — is downloaded (matched by file name) and attached with alt text. No AI — nothing is charged. Share the folder as “anyone with the link can view”.')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('folder')
                                ->label('Google Drive folder (link or ID)')
                                ->placeholder('https://drive.google.com/drive/folders/…')
                                ->default(fn () => (string) setting('catalog.drive_image_folder'))
                                ->required(),
                            \Filament\Forms\Components\Toggle::make('override')
                                ->label('Replace images on products that already have one')
                                ->helperText('Off = only fills products that are missing an image.')
                                ->default(false),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            if ((string) setting('ai.google_drive_api_key') === '') {
                                \Filament\Notifications\Notification::make()
                                    ->title('No Google Drive API key')
                                    ->body('Add one under Settings → AI settings first — folder image matching needs it.')
                                    ->danger()->send();

                                return;
                            }

                            \App\Console\Commands\FillProductImagesFromDrive::clearStatus();

                            $args = [
                                'products:fill-images',
                                '--folder='.$data['folder'],
                                '--ids='.$records->pluck('id')->implode(','),
                                '--user='.(string) auth()->id(),
                            ];
                            if (! empty($data['override'])) {
                                $args[] = '--override';
                            }

                            $launched = \App\Support\BackgroundProcess::artisan($args);

                            \Filament\Notifications\Notification::make()
                                ->title($launched ? 'Fetching images from Drive in the background' : 'Could not start the image fetcher')
                                ->body($launched
                                    ? 'Watch progress with the “Drive image status” button; a notification lands when it finishes.'
                                    : 'No background worker available in this environment.')
                                ->{$launched ? 'success' : 'warning'}()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulkCategories')
                        ->label('Set categories')
                        ->icon(Heroicon::OutlinedFolder)
                        ->schema([
                            Select::make('category_ids')
                                ->label('Categories')
                                ->options(fn () => \App\Models\Category::orderBy('name')->pluck('name', 'id')->all())
                                ->multiple()
                                ->searchable()
                                ->required(),
                            Select::make('mode')
                                ->options(['add' => 'Add to existing', 'replace' => 'Replace existing'])
                                ->default('add')
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            foreach ($records as $product) {
                                $data['mode'] === 'replace'
                                    ? $product->categories()->sync($data['category_ids'])
                                    : $product->categories()->syncWithoutDetaching($data['category_ids']);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulkPrice')
                        ->label('Adjust price')
                        ->icon(Heroicon::OutlinedCurrencyDollar)
                        ->schema([
                            Select::make('field')
                                ->options(['price' => 'Regular price', 'sale_price' => 'Sale price'])
                                ->default('price')
                                ->required()
                                ->native(false),
                            Select::make('type')
                                ->label('Change type')
                                ->options([
                                    'set' => 'Set to fixed value',
                                    'increase_fixed' => 'Increase by fixed amount',
                                    'decrease_fixed' => 'Decrease by fixed amount',
                                    'increase_percent' => 'Increase by percent',
                                    'decrease_percent' => 'Decrease by percent',
                                ])
                                ->default('set')
                                ->required()
                                ->native(false),
                            TextInput::make('amount')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $amount = (float) $data['amount'];
                            foreach ($records as $product) {
                                $current = (float) ($product->{$data['field']} ?? 0);
                                $new = match ($data['type']) {
                                    'set' => $amount,
                                    'increase_fixed' => $current + $amount,
                                    'decrease_fixed' => $current - $amount,
                                    'increase_percent' => $current * (1 + $amount / 100),
                                    'decrease_percent' => $current * (1 - $amount / 100),
                                };
                                $product->update([$data['field'] => max(0, round($new, 2))]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulkStatus')
                        ->label('Change status')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->schema([
                            Select::make('status')
                                ->options([
                                    'published' => 'Published',
                                    'draft' => 'Draft',
                                    'archived' => 'Archived',
                                ])
                                ->required()
                                ->native(false),
                        ])
                        ->action(fn (\Illuminate\Support\Collection $records, array $data) => $records->each->update(['status' => $data['status']]))
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulkStock')
                        ->label('Change stock status')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->schema([
                            Select::make('stock_status')
                                ->options([
                                    'in_stock' => 'In stock',
                                    'out_of_stock' => 'Out of stock',
                                    'on_backorder' => 'On backorder',
                                ])
                                ->required()
                                ->native(false),
                        ])
                        ->action(fn (\Illuminate\Support\Collection $records, array $data) => $records->each->update(['stock_status' => $data['stock_status']]))
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulkFlags')
                        ->label('Set flags')
                        ->icon(Heroicon::OutlinedStar)
                        ->schema([
                            Select::make('flag')
                                ->options([
                                    'is_featured' => 'Featured',
                                    'is_new_arrival' => 'New arrival',
                                    'is_best_seller' => 'Best seller',
                                ])
                                ->required()
                                ->native(false),
                            Select::make('value')
                                ->options([1 => 'On', 0 => 'Off'])
                                ->required()
                                ->native(false),
                        ])
                        ->action(fn (\Illuminate\Support\Collection $records, array $data) => $records->each->update([$data['flag'] => (bool) $data['value']]))
                        ->deselectRecordsAfterCompletion(),
                    // coalescePurge: delete the whole batch, then clear cache once.
                    ResourceActions::coalescePurge(\Filament\Actions\DeleteBulkAction::make()->label('Move to trash')),
                    ResourceActions::coalescePurge(\Filament\Actions\RestoreBulkAction::make()),
                    ResourceActions::coalescePurge(\Filament\Actions\ForceDeleteBulkAction::make()->label('Delete permanently')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Include trashed rows so the "Trashed" filter can surface the trash box. */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            VariationsRelationManager::class,
            ImagesRelationManager::class,
            FaqsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
