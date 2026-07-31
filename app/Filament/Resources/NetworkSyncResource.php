<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NetworkSyncResource\Pages\ListNetworkSync;
use App\Models\NetworkPostLink;
use App\Services\Network\NetworkSyncService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hub-side sync status of every published-to-site post (network_post_links):
 * synced / pending (hub has newer, not yet pushed) / conflict (edited on the
 * spoke) / failed. Resolve conflicts by pushing the hub version, or unlink.
 */
class NetworkSyncResource extends Resource
{
    protected static ?string $model = NetworkPostLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Sync status';

    protected static ?string $modelLabel = 'sync link';

    public static function canAccess(): bool
    {
        return network_enabled() && is_network_hub() && \App\Support\AdminAccess::allows(static::class);
    }

    /** Badge count of links needing attention (conflict or failed). */
    public static function getNavigationBadge(): ?string
    {
        if (! network_enabled() || ! is_network_hub()) {
            return null;
        }

        $n = NetworkPostLink::whereIn('status', ['conflict', 'failed'])->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('post.title')->label('Post')->limit(40)->searchable()->sortable(),
                TextColumn::make('site.name')->label('Site')->badge()->color('gray')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'synced' => 'success',
                    'pending' => 'info',
                    'conflict' => 'warning',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('last_pushed_at')->label('Last pushed')->since()->placeholder('never')->sortable(),
                TextColumn::make('conflict_detected_at')->label('Conflict since')->since()->placeholder('—'),
                TextColumn::make('last_error')->label('Error')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'synced' => 'Synced', 'pending' => 'Pending push', 'conflict' => 'Conflict', 'failed' => 'Failed',
                ]),
            ])
            ->recordActions([
                Action::make('pushHubVersion')
                    ->label('Push hub version')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('primary')
                    ->visible(fn (NetworkPostLink $r): bool => in_array($r->status, ['conflict', 'pending', 'failed'], true))
                    ->requiresConfirmation()
                    ->modalDescription('Overwrite the remote copy with the current hub version, resolving the difference in the hub\'s favor.')
                    ->action(function (NetworkPostLink $record): void {
                        (new NetworkSyncService)->resolveHubWins($record);
                        Notification::make()->title('Pushing hub version…')->success()->send();
                    }),
                Action::make('unlink')
                    ->label('Unlink')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Delete the remote copy on that site and remove this link. The hub post stays.')
                    ->action(function (NetworkPostLink $record): void {
                        (new NetworkSyncService)->removeLink($record);
                        Notification::make()->title('Removing remote copy…')->success()->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Nothing published to the network yet');
    }

    public static function getPages(): array
    {
        return ['index' => ListNetworkSync::route('/')];
    }
}
