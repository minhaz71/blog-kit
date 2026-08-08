<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostCategoryResource\Pages\CreatePostCategory;
use App\Filament\Resources\PostCategoryResource\Pages\EditPostCategory;
use App\Filament\Resources\PostCategoryResource\Pages\ListPostCategories;
use App\Models\PostCategory;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PostCategoryResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = PostCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                \Filament\Forms\Components\Select::make('parent_id')
                    ->label('Mother category')
                    ->relationship(
                        'parent',
                        'name',
                        fn ($query, ?\App\Models\PostCategory $record) => $query
                            ->whereNull('parent_id')
                            ->when($record, fn ($q) => $q->whereKeyNot($record->id)),
                    )
                    ->searchable()
                    ->native(false)
                    ->placeholder('— none (this is a mother category) —')
                    ->helperText('Leave blank for a top-level (mother) category; pick one to make this a sub-category.'),
                TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower shows first in the menu.'),
                \Filament\Forms\Components\Toggle::make('is_active')->default(true)->inline(false),
                \Filament\Forms\Components\Toggle::make('show_in_menu')->default(true)->inline(false)
                    ->helperText('Include this category in the auto-generated header menu.'),
            ]),
            Textarea::make('description')->rows(3)->columnSpanFull(),
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
                    ->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Mother')->placeholder('— top level —')->badge()->color('gray')->toggleable(),
                TextColumn::make('slug')->toggleable(),
                TextColumn::make('posts_count')->counts('posts')->label('Posts'),
                \Filament\Tables\Columns\IconColumn::make('show_in_menu')->boolean()->label('Menu')->toggleable(),
                \Filament\Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->tooltip('Posts fall back here when their category is deleted.')
                    ->state(fn (PostCategory $record): bool => (int) setting('blog.default_post_category_id') === $record->id),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('makeDefault')
                    ->label('Make default')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->visible(fn (PostCategory $record): bool => (int) setting('blog.default_post_category_id') !== $record->id)
                    ->requiresConfirmation()
                    ->modalDescription('Posts move here when their category is deleted, so no post is ever left uncategorised.')
                    ->action(function (PostCategory $record): void {
                        \App\Models\Setting::set('blog.default_post_category_id', $record->id);
                        \Filament\Notifications\Notification::make()
                            ->title("\"{$record->name}\" is now the default blog category")
                            ->success()->send();
                    }),
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostCategories::route('/'),
            'create' => CreatePostCategory::route('/create'),
            'edit' => EditPostCategory::route('/{record}/edit'),
        ];
    }
}
