<?php

namespace App\Filament\Resources\AiImportBatchResource\RelationManagers;

use App\Jobs\WriteAiProduct;
use App\Models\AiImportItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row.name')->label('Item')->limit(45),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'published', 'linked' => 'success',
                    'failed' => 'danger',
                    'pending' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('passes_done')->label('QA passes'),
                TextColumn::make('product.name')->label('Created product')->placeholder('—')
                    ->url(fn (AiImportItem $record) => $record->product_id ? "/admin/products/{$record->product_id}/edit" : null)
                    ->visible(fn () => $this->getOwnerRecord()->kind !== 'blog'),
                TextColumn::make('post.title')->label('Created post')->placeholder('—')->limit(40)
                    ->description(fn (AiImportItem $record) => $record->post?->status === 'draft' ? 'draft — needs review' : null)
                    ->url(fn (AiImportItem $record) => $record->post_id ? "/admin/posts/{$record->post_id}/edit" : null)
                    ->visible(fn () => $this->getOwnerRecord()->kind === 'blog'),
                TextColumn::make('error')->limit(60)->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'writing' => 'Writing', 'reviewing' => 'Reviewing',
                    'needs_review' => 'Needs review', 'published' => 'Published',
                    'linked' => 'Linked', 'failed' => 'Failed',
                ]),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('retry')
                    ->label(fn (AiImportItem $record) => $record->status === 'needs_review' ? 'Re-run' : 'Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (AiImportItem $record) => in_array($record->status, ['failed', 'needs_review'], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (AiImportItem $record) => $record->status === 'needs_review'
                        ? 'Runs the full write → review cycle again for this product. Its reserved URL stays valid — sibling links to it are kept.'
                        : 'Retries this failed product from the start.')
                    ->action(function (AiImportItem $record): void {
                        $record->update(['status' => 'pending', 'error' => null]);

                        if ($record->batch && ! in_array($record->batch->status, ['processing', 'linking'], true)) {
                            $record->batch->update(['status' => 'processing']);
                        }

                        // Background runner processes it to completion — no
                        // queue worker needed (queue dispatch as fallback).
                        if (! \App\Support\BackgroundProcess::artisan(['ai:run-batch', (string) $record->batch_id])) {
                            WriteAiProduct::dispatch($record->id);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Item re-running — watch it on the Live monitor')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('approvePublish')
                    ->label('Approve & publish')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (AiImportItem $record) => $record->status === 'needs_review' && ! empty($record->ai_output))
                    ->requiresConfirmation()
                    ->modalDescription('The finished draft is already written and stored — this publishes it AS IS, overriding the reviewer\'s remaining complaints. No AI tokens are spent. You can still edit the published post afterwards.')
                    ->action(function (AiImportItem $record): void {
                        $batch = $record->batch;
                        $output = (array) $record->ai_output;

                        if ($batch->kind === 'blog') {
                            $post = (new \App\Services\Ai\BlogPublisher)->publish($record, $output);
                            $record->update(['preview_url' => route('blog.show', $post->slug)]);
                            $url = $post->status === 'published' ? route('blog.show', $post->slug) : null;
                        } else {
                            $product = (new \App\Services\Ai\ProductPublisher)->publish($record, $output);
                            $url = $product->url();
                        }

                        $batch->increment('done_items');
                        \App\Models\AiActivityLog::write($batch->id, $record->id, 'publish',
                            '✅ Held item approved by admin and published as written.', 'success');
                        \App\Jobs\FinalizeAiImportBatch::dispatch($batch);

                        \Filament\Notifications\Notification::make()
                            ->title('Published')
                            ->body($url ? 'Live at '.$url : 'Saved (check the post — it may be scheduled or a draft per the batch publish mode).')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id');
    }
}
