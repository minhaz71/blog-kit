<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Support\ResourceActions;
use App\Filament\Support\SeoForm;
use App\Models\Post;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PostResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Post')->columnSpanFull()->tabs([
                Tab::make('Content')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true),
                        Select::make('author_id')
                            ->label('Author')
                            ->relationship('author', 'name')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->default(fn () => auth()->id()),
                        Select::make('post_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->preload()
                            ->searchable(),
                    ]),
                    Textarea::make('excerpt')->rows(3)->columnSpanFull(),
                    Editor::rich('content')->columnSpanFull()->required(),
                    FileUpload::make('featured_image')->image()->disk('public')->directory('posts')->imageEditor(),
                    TextInput::make('featured_image_alt')->helperText('Descriptive alt text for the featured image.'),
                    Select::make('tags')->relationship('tags', 'name')->multiple()->preload()->searchable(),
                ]),
                Tab::make('Publishing')->schema([
                    Grid::make(2)->schema([
                        Select::make('status')
                            ->options(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'])
                            ->default('draft')->required()->native(false)->live(),
                        DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('status') === 'scheduled')
                            ->helperText(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('status') === 'scheduled'
                                ? 'The post goes live automatically at this time (checked every minute).'
                                : null),
                        TextInput::make('reading_time')->numeric()->default(3)->suffix('min'),
                        Toggle::make('show_toc')->label('Show table of contents')->default(true),
                    ]),
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
                ImageColumn::make('featured_image')->disk('public')->square()->toggleable(),
                TextColumn::make('title')->searchable()->sortable()->limit(50),
                TextColumn::make('author.name')->toggleable(),
                TextColumn::make('last_edited')
                    ->label('Last edited')
                    ->state(fn (\App\Models\Post $r) => ($r->lastEditor?->name ?: 'AI writer').' · '.$r->updated_at->diffForHumans())
                    ->toggleable(),
                TextColumn::make('category.name')->badge()->toggleable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'published' => 'success', 'scheduled' => 'warning', default => 'gray',
                }),
                TextColumn::make('published_at')->dateTime()->sortable()->toggleable(),
                SeoForm::scoreColumn(),
                TextColumn::make('seoMeta.title')->label('SEO title')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seoMeta.description')->label('SEO description')->limit(50)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published']),
                SelectFilter::make('category')->relationship('category', 'name'),
                // Trash lives in the "Trash" tab (see ListPosts::getTabs).
            ])
            ->recordActions([
                ResourceActions::viewRow(
                    fn (Post $record): string => route('blog.show', $record->slug),
                    fn (Post $record): bool => $record->status === 'published' && $record->published_at?->isPast(),
                ),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('revisions')
                    ->label('Revisions')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedClock)
                    ->color('gray')
                    ->url(fn (\App\Models\Post $record): string => static::getUrl('revisions', ['record' => $record])),
                ResourceActions::duplicateRow('title', ['tags']),
                \Filament\Actions\DeleteAction::make()->label('Trash'),
                \Filament\Actions\RestoreAction::make(),
                \Filament\Actions\ForceDeleteAction::make()->label('Delete forever'),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    ResourceActions::refreshWithAi('blog'),
                    ...ResourceActions::statusBulks(),
                    // coalescePurge: delete the whole batch, then clear cache once.
                    ResourceActions::coalescePurge(\Filament\Actions\DeleteBulkAction::make()->label('Move to trash')),
                    ResourceActions::coalescePurge(\Filament\Actions\RestoreBulkAction::make()),
                    ResourceActions::coalescePurge(\Filament\Actions\ForceDeleteBulkAction::make()->label('Delete permanently')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
            'revisions' => \App\Filament\Resources\PostResource\Pages\PostRevisions::route('/{record}/revisions'),
        ];
    }
}
