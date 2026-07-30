<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Support\ResourceActions;
use App\Filament\Support\SeoForm;
use App\Models\Page;
use BackedEnum;
use App\Filament\Support\Editor;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PageResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Page')->columnSpanFull()->tabs([
                Tab::make('Content')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true),
                        Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])->default('published')->required()->native(false),
                        Select::make('template')->options(['default' => 'Default', 'landing' => 'Landing', 'wide' => 'Wide'])->default('default')->native(false),
                    ]),
                    Editor::rich('content')->columnSpanFull()->required(),
                    Toggle::make('is_system')->helperText('System pages (cart, checkout, etc.) cannot be deleted.')->disabled(),
                ]),
                Tab::make('SEO')->schema(SeoForm::components()),
                Editor::customCodeTab(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Columns are toggleable ("Screen Options") — use the
                // column-toggle icon in the table header to show/hide any.
                TextColumn::make('title')->searchable()->sortable()->limit(50),
                TextColumn::make('slug')->toggleable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('is_system')->boolean()->label('System'),
                SeoForm::scoreColumn(),
                TextColumn::make('seoMeta.title')->label('SEO title')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seoMeta.description')->label('SEO description')->limit(50)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Modified')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                // Trash lives in the "Trash" tab (see ListPages::getTabs).
            ])
            ->recordActions([
                ResourceActions::viewRow(
                    fn (Page $record): string => route('page.show', $record->slug),
                    fn (Page $record): bool => $record->status === 'published',
                ),
                \Filament\Actions\EditAction::make(),
                ResourceActions::duplicateRow('title'),
                \Filament\Actions\RestoreAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    ...ResourceActions::statusBulks(),
                    // coalescePurge: delete the whole batch, then clear cache once.
                    ResourceActions::coalescePurge(\Filament\Actions\DeleteBulkAction::make()
                        ->label('Move to trash')
                        // System pages (cart, checkout, …) must never be deleted.
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $skipped = $records->where('is_system', true)->count();
                            $records->where('is_system', false)->each->delete();

                            \Filament\Notifications\Notification::make()
                                ->title($skipped > 0 ? "Trashed — {$skipped} system page(s) skipped" : 'Moved to trash')
                                ->success()
                                ->send();
                        })),
                    ResourceActions::coalescePurge(\Filament\Actions\RestoreBulkAction::make()),
                    ResourceActions::coalescePurge(\Filament\Actions\ForceDeleteBulkAction::make()
                        ->label('Delete permanently')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $skipped = $records->where('is_system', true)->count();
                            $records->where('is_system', false)->each->forceDelete();

                            \Filament\Notifications\Notification::make()
                                ->title($skipped > 0 ? "Deleted — {$skipped} system page(s) skipped" : 'Deleted permanently')
                                ->success()
                                ->send();
                        })),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [FaqsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
