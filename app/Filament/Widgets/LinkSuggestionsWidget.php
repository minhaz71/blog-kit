<?php

namespace App\Filament\Widgets;

use App\Models\LinkSuggestion;
use App\Services\Seo\LinkApplier;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * Footer widget on product/post edit pages: pending link suggestions for
 * THIS record with one-click Apply/Dismiss. Applying reloads the page so
 * the open form picks up the new content (otherwise saving the form would
 * overwrite the freshly inserted link).
 */
class LinkSuggestionsWidget extends Widget
{
    protected string $view = 'filament.widgets.link-suggestions';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function apply(int $id): void
    {
        $suggestion = $this->suggestionFor($id);

        if (! $suggestion) {
            return;
        }

        try {
            app(LinkApplier::class)->apply($suggestion);

            Notification::make()
                ->title('Link applied — reloading so the editor shows the updated content.')
                ->success()
                ->send();

            $this->js('setTimeout(() => window.location.reload(), 800)');
        } catch (\Throwable $e) {
            Notification::make()->title('Could not apply')->body($e->getMessage())->warning()->send();
        }
    }

    public function dismiss(int $id): void
    {
        $this->suggestionFor($id)?->update(['status' => 'dismissed']);
    }

    protected function suggestionFor(int $id): ?LinkSuggestion
    {
        // Scoped to this record — the widget can never touch other content.
        return LinkSuggestion::query()
            ->whereKey($id)
            ->where('source_type', $this->record::class)
            ->where('source_id', $this->record->getKey())
            ->first();
    }

    protected function getViewData(): array
    {
        if (! $this->record) {
            return ['suggestions' => collect()];
        }

        return [
            'suggestions' => LinkSuggestion::query()
                ->where('source_type', $this->record::class)
                ->where('source_id', $this->record->getKey())
                ->where('status', 'pending')
                ->with('target')
                ->orderByDesc('score')
                ->get()
                ->filter(fn ($s) => $s->target),
        ];
    }
}
