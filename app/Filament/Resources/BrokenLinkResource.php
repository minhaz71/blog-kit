<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrokenLinkResource\Pages\ListBrokenLinks;
use App\Models\BrokenLink;
use App\Models\Post;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Broken internal links: pages that still link to a product/post that was
 * deleted. Each row points at the page to fix; reports auto-resolve when the
 * target is restored or the link is removed, and can be dismissed manually.
 */
class BrokenLinkResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = BrokenLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLinkSlash;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 32;

    protected static ?string $label = 'Broken links';

    public static function canCreate(): bool
    {
        return false;
    }

    /** Red badge with the open count so a build-up of dead links is visible. */
    public static function getNavigationBadge(): ?string
    {
        $open = BrokenLink::query()->open()->count();

        return $open > 0 ? (string) $open : null;
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
                TextColumn::make('source_label')
                    ->label('On page')
                    ->state(fn (BrokenLink $r): string => self::sourceLabel($r))
                    ->searchable(query: fn ($query, $search) => $query->where('url', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('url')
                    ->label('Dead link')
                    ->color('danger')
                    ->limit(50),
                TextColumn::make('anchor')
                    ->label('Anchor text')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('reason')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Detected')
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->label('Status')
                    ->badge()
                    ->state(fn (BrokenLink $r): string => $r->resolved_at ? 'Resolved' : 'Open')
                    ->color(fn (BrokenLink $r): string => $r->resolved_at ? 'success' : 'danger'),
            ])
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('resolved_at')
                    ->label('Status')
                    ->placeholder('Open only')
                    ->trueLabel('Resolved')
                    ->falseLabel('All')
                    ->queries(
                        true: fn ($query) => $query->resolved(),
                        false: fn ($query) => $query,
                        blank: fn ($query) => $query->open(),
                    ),
            ])
            ->recordActions([
                Action::make('unlink')
                    ->label('Unlink')
                    ->icon(Heroicon::OutlinedScissors)
                    ->color('warning')
                    ->visible(fn (BrokenLink $r): bool => $r->resolved_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Remove the dead link from the page?')
                    ->modalDescription('The <a> tag is removed from the page content; its text stays as plain text. The page is re-saved (cache purged, posts keep a revision).')
                    ->action(function (BrokenLink $r): void {
                        $removed = $r->unlink();
                        \Filament\Notifications\Notification::make()
                            ->title($removed > 0 ? "Unlinked {$removed} dead link(s) on the page" : 'Nothing to unlink — the link was already gone; report resolved')
                            ->success()->send();
                    }),
                Action::make('fix')
                    ->label('Fix page')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (BrokenLink $r): ?string => self::sourceEditUrl($r))
                    ->openUrlInNewTab()
                    ->visible(fn (BrokenLink $r): bool => self::sourceEditUrl($r) !== null),
                Action::make('resolve')
                    ->label('Mark resolved')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (BrokenLink $r): bool => $r->resolved_at === null)
                    ->action(fn (BrokenLink $r) => $r->update(['resolved_at' => now()])),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulkUnlink')
                        ->label('Unlink selected')
                        ->icon(Heroicon::OutlinedScissors)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Remove these dead links from their pages?')
                        ->modalDescription('Each <a> tag is removed from its page; the anchor text stays as plain text.')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            // Coalesce the cache purge: many pages may re-save.
                            \App\Services\Performance\LiteSpeedPurger::beginBatch();
                            $removed = 0;
                            try {
                                foreach ($records as $record) {
                                    $removed += $record->resolved_at === null ? $record->unlink() : 0;
                                }
                            } finally {
                                \App\Services\Performance\LiteSpeedPurger::endBatch();
                            }

                            \Filament\Notifications\Notification::make()
                                ->title("Unlinked {$removed} dead link(s) across the selected reports")
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** "Product: TEREA Amber" / "Post: Best flavors" / a bare type when the source is gone. */
    protected static function sourceLabel(BrokenLink $report): string
    {
        $type = class_basename((string) $report->source_type);
        $source = $report->source;

        $title = $source?->name ?? $source?->title ?? "#{$report->source_id}";

        return "{$type}: {$title}";
    }

    /** Admin edit URL of the page that contains the broken link, when known. */
    protected static function sourceEditUrl(BrokenLink $report): ?string
    {
        return match ($report->source_type) {
            Product::class => ProductResource::getUrl('edit', ['record' => $report->source_id]),
            Post::class => PostResource::getUrl('edit', ['record' => $report->source_id]),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrokenLinks::route('/'),
        ];
    }
}
