<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared row/bulk action builders so every resource gets the same
 * real-admin-panel treatment (publish/draft/activate/view/duplicate).
 */
class ResourceActions
{
    /**
     * "Refresh with AI" bulk action: rewrites the selected records' copy in
     * place via App\Services\Ai\ContentRefresh. $kind is 'product' or 'blog'.
     * Works bulk or category-wise (filter the table, select all, refresh).
     */
    public static function refreshWithAi(string $kind): BulkAction
    {
        return BulkAction::make('refreshWithAi')
            ->label('Refresh with AI')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('info')
            ->modalHeading('Rewrite the selected '.($kind === 'blog' ? 'articles' : 'products').' with AI')
            ->modalDescription('The AI reads each current page, preserves its facts (specs, prices, compatibility), fills the gaps competitors cover, and rewrites it fully for Google, Bing and AI answer engines with strong E-E-A-T. Price, stock and the URL are never changed. Drafts by default so you review before it goes live.')
            ->schema([
                \Filament\Forms\Components\Select::make('provider')
                    ->label('Writer provider')
                    ->options(\App\Models\AiImportBatch::PROVIDERS)
                    ->default('anthropic')->required()->native(false),
                \Filament\Forms\Components\TextInput::make('model')
                    ->label('Model (blank = provider default)'),
                \Filament\Forms\Components\Select::make('publish_mode')
                    ->options(['draft' => 'Save as drafts (review first)', 'publish' => 'Update live immediately'])
                    ->default('draft')->native(false),
            ])
            ->action(function (Collection $records, array $data) use ($kind): void {
                $refresh = app(\App\Services\Ai\ContentRefresh::class);
                $opts = ['provider' => $data['provider'] ?? 'anthropic', 'model' => $data['model'] ?? null, 'publish_mode' => $data['publish_mode'] ?? 'draft'];

                $batch = $kind === 'blog'
                    ? $refresh->posts($records, $opts)
                    : $refresh->products($records, $opts);
                $refresh->start($batch);

                \Filament\Notifications\Notification::make()
                    ->title('Refresh started')
                    ->body($records->count().' '.($kind === 'blog' ? 'article(s)' : 'product(s)').' queued. Watch progress in the AI '.($kind === 'blog' ? 'Blog Writer' : 'Product Publisher').' live monitor.')
                    ->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** Publish + Move to draft bulk actions for status-based content. */
    public static function statusBulks(string $field = 'status'): array
    {
        return [
            BulkAction::make('bulkPublish')
                ->label('Publish')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->action(fn (Collection $records) => $records->each->update([$field => 'published']))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('bulkDraft')
                ->label('Move to draft')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('warning')
                ->action(fn (Collection $records) => $records->each->update([$field => 'draft']))
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /** Activate/Deactivate bulk actions for is_active-style toggles. */
    public static function activeBulks(string $field = 'is_active'): array
    {
        return [
            BulkAction::make('bulkActivate')
                ->label('Activate')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->action(fn (Collection $records) => $records->each->update([$field => true]))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('bulkDeactivate')
                ->label('Deactivate')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('warning')
                ->action(fn (Collection $records) => $records->each->update([$field => false]))
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /** Row action linking to the public page (View for published, Preview otherwise). */
    public static function viewRow(Closure $url, ?Closure $isPublished = null): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('view')
            ->label(fn (Model $record): string => ($isPublished === null || $isPublished($record)) ? 'View' : 'Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (Model $record): string => $url($record).(($isPublished === null || $isPublished($record)) ? '' : '?preview=1'))
            ->openUrlInNewTab();
    }

    /**
     * Coalesce cache purges for a bulk action: hold per-record purges while
     * the whole batch deletes/restores, then clear the cache ONCE at the end
     * instead of rewriting the purge flag per record. Returns the same action
     * for chaining.
     *
     * @template T of \Filament\Actions\BulkAction
     *
     * @param  T  $action
     * @return T
     */
    public static function coalescePurge(\Filament\Actions\BulkAction $action): \Filament\Actions\BulkAction
    {
        return $action
            ->before(fn () => \App\Services\Performance\LiteSpeedPurger::beginBatch())
            ->after(fn () => \App\Services\Performance\LiteSpeedPurger::endBatch());
    }

    /** Row action duplicating a content record (title/slug based) as a draft. */
    public static function duplicateRow(string $titleField = 'title', array $syncRelations = []): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Duplicate')
            ->modalDescription('Creates a draft copy including SEO meta.')
            ->action(function (Model $record) use ($titleField, $syncRelations): void {
                $copy = $record->replicate();
                $copy->{$titleField} = $record->{$titleField}.' (copy)';
                $copy->slug = \Illuminate\Support\Str::slug($record->{$titleField})
                    .'-copy-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));

                if ($copy->isFillable('status') || $record->getAttribute('status') !== null) {
                    $copy->status = 'draft';
                }

                if ($record->getAttribute('is_system') !== null) {
                    $copy->is_system = false;
                }

                $copy->save();

                foreach ($syncRelations as $relation) {
                    $copy->{$relation}()->sync($record->{$relation}->pluck('id'));
                }

                if (method_exists($record, 'seoMeta') && $record->seoMeta) {
                    $copy->seoMeta()->create(
                        $record->seoMeta->only([
                            'title', 'description', 'focus_keyword', 'og_title',
                            'og_description', 'og_image', 'twitter_title',
                            'twitter_description', 'twitter_image', 'schema_enabled',
                        ]) + ['noindex' => true],
                    );
                }

                \Filament\Notifications\Notification::make()
                    ->title('Duplicated as draft')
                    ->success()
                    ->send();
            });
    }
}
