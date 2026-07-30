<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Support\ResourceActions;
use App\Filament\Support\SeoForm;
use App\Models\Category;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use App\Filament\Support\Editor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Category')
                    ->columnSpanFull()
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
                                    Select::make('parent_id')
                                        ->label('Parent category')
                                        ->relationship(
                                            'parent',
                                            'name',
                                            ignoreRecord: true,
                                        )
                                        ->searchable()
                                        ->preload(),
                                    TextInput::make('sort_order')
                                        ->numeric()
                                        ->default(0),
                                ]),
                                Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Editor::rich('content_block')
                                    ->label('Content block (shown on category page)')
                                    ->columnSpanFull(),
                                Grid::make(2)->schema([
                                    FileUpload::make('image')
                                        ->image()
                                        ->disk('public')
                                        ->directory('categories'),
                                    FileUpload::make('banner')
                                        ->image()
                                        ->disk('public')
                                        ->directory('categories/banners'),
                                ]),
                                TextInput::make('image_alt')
                                    ->label('Image alt text (SEO)')
                                    ->maxLength(255)
                                    ->helperText('Describes the category image/banner for search engines. Falls back to the category name.'),
                                Toggle::make('is_active')
                                    ->default(true),
                            ]),
                        Tab::make('SEO')
                            ->schema(SeoForm::components()),
                        Editor::customCodeTab(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Category ID copied — paste it in the CSV category_id column')
                    ->sortable()
                    ->tooltip('Use this ID in the product CSV\'s category_id column for an exact match.'),
                ImageColumn::make('image')
                    ->disk('public')
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->tooltip('Total products in this category.'),
                TextColumn::make('sort_order')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->tooltip('Products fall back here when their category is deleted.')
                    ->state(fn (Category $record): bool => (int) setting('catalog.default_category_id') === $record->id),
                SeoForm::scoreColumn(),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship('parent', 'name'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('makeDefault')
                    ->label('Make default')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->visible(fn (Category $record): bool => (int) setting('catalog.default_category_id') !== $record->id)
                    ->requiresConfirmation()
                    ->modalDescription('Products land here when their category is deleted and they would otherwise have no category.')
                    ->action(function (Category $record): void {
                        \App\Models\Setting::set('catalog.default_category_id', $record->id);
                        \Filament\Notifications\Notification::make()
                            ->title("\"{$record->name}\" is now the default category")
                            ->success()->send();
                    }),
                ResourceActions::viewRow(
                    fn (Category $record): string => \App\Support\Permalinks::category($record->slug),
                    fn (Category $record): bool => (bool) $record->is_active,
                ),
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('refreshWithAi')
                        ->label('Refresh with AI')
                        ->icon(\Filament\Support\Icons\Heroicon::OutlinedSparkles)
                        ->color('info')
                        ->visible(fn () => \App\Services\Ai\CategoryWriter::available())
                        ->modalHeading('Rewrite the selected category descriptions with AI')
                        ->modalDescription('For each category the agent reads its products, analyzes how top pages present this product type, and rewrites the description + SEO meta for E-E-A-T and ranking. Runs in the background; replaces the current content.')
                        ->schema([
                            \Filament\Forms\Components\Select::make('provider')
                                ->options(\App\Services\Ai\CategoryWriter::PROVIDERS)
                                ->default('anthropic')->required()->native(false),
                            \Filament\Forms\Components\TextInput::make('model')->label('Model (blank = default)'),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $launched = 0;
                            foreach ($records as $category) {
                                \App\Services\Ai\CategoryWriter::setStatus($category->id, 'running', 'Queued — starting the writer…');
                                $args = ['category:write', (string) $category->id, '--provider=' . ($data['provider'] ?? 'anthropic'), '--user=' . (string) auth()->id()];
                                if (! empty($data['model'])) {
                                    $args[] = '--model=' . $data['model'];
                                }
                                if (\App\Support\BackgroundProcess::artisan($args)) {
                                    $launched++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Category refresh started')
                                ->body("{$launched} category description(s) rewriting in the background.")
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    ...ResourceActions::activeBulks(),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            FaqsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
