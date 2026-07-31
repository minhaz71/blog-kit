<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NetworkPostResource\Pages\ListNetworkPosts;
use App\Models\ConnectedSite;
use App\Models\NetworkRemotePost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Aggregated, read-only view of posts across every connected site — one table
 * the hub admin can filter by site name and status. Rows are a local mirror
 * refreshed by the pull job (Sync all sites). Editing remote posts arrives
 * with two-way sync.
 */
class NetworkPostResource extends Resource
{
    protected static ?string $model = NetworkRemotePost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'All sites\' posts';

    protected static ?string $modelLabel = 'network post';

    public static function canAccess(): bool
    {
        return network_enabled() && is_network_hub() && \App\Support\AdminAccess::allows(static::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]); // read-only mirror; no edit form
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('Site')->badge()->color('gray')->sortable()->searchable(),
                TextColumn::make('title')->searchable()->limit(50)->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'published' => 'success',
                    'scheduled' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('category_name')->label('Category')->placeholder('—')->toggleable(),
                TextColumn::make('author_name')->label('Author')->placeholder('—')->toggleable(),
                TextColumn::make('published_at')->label('Published')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('remote_updated_at')->label('Updated')->since()->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('site_id')
                    ->label('Site')
                    ->options(fn () => ConnectedSite::query()->orderBy('id')->pluck('name', 'id')),
                SelectFilter::make('status')->options([
                    'published' => 'Published',
                    'draft' => 'Draft',
                    'scheduled' => 'Scheduled',
                ]),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (NetworkRemotePost $r): ?string => $r->url, true)
                    ->visible(fn (NetworkRemotePost $r): bool => filled($r->url)),
            ])
            ->defaultSort('remote_updated_at', 'desc')
            ->emptyStateHeading('No posts mirrored yet')
            ->emptyStateDescription('Click "Sync all sites" to pull posts from your connected sites.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNetworkPosts::route('/'),
        ];
    }
}
