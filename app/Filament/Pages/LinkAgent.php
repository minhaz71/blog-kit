<?php

namespace App\Filament\Pages;

use App\Models\LinkSuggestion;
use App\Models\LinkTarget;
use App\Services\Seo\LinkApplier;
use App\Services\Seo\LinkDictionary;
use App\Services\Seo\LinkSuggestionEngine;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The link agent dashboard: every pending suggestion across the catalog,
 * grouped by source page, with one-click Apply / Dismiss and Undo for
 * applied links. Suggest-only by design — nothing ever auto-applies.
 */
class LinkAgent extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 25;

    protected static ?string $title = 'Link agent';

    public string $search = '';

    protected string $view = 'filament.pages.link-agent';

    public static function getNavigationBadge(): ?string
    {
        $pending = LinkSuggestion::where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public function rebuild(): void
    {
        @set_time_limit(300);

        app(LinkDictionary::class)->rebuild();
        $stats = app(LinkSuggestionEngine::class)->scanAll();

        Notification::make()
            ->title("Re-scanned {$stats['sources']} pages — {$stats['suggestions']} suggestion(s) pending.")
            ->success()
            ->send();
    }

    public function apply(int $id): void
    {
        $suggestion = LinkSuggestion::find($id);

        if (! $suggestion) {
            return;
        }

        try {
            app(LinkApplier::class)->apply($suggestion);

            Notification::make()
                ->title('Link applied: "'.$suggestion->anchor.'"')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Could not apply')->body($e->getMessage())->warning()->send();
        }
    }

    public function dismiss(int $id): void
    {
        LinkSuggestion::whereKey($id)->where('status', 'pending')->update(['status' => 'dismissed']);
    }

    public function undo(int $id): void
    {
        $suggestion = LinkSuggestion::find($id);

        if (! $suggestion) {
            return;
        }

        try {
            app(LinkApplier::class)->undo($suggestion);
            Notification::make()->title('Link removed — back in the suggestion queue.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Could not undo')->body($e->getMessage())->warning()->send();
        }
    }

    protected function getViewData(): array
    {
        $pending = LinkSuggestion::query()
            ->where('status', 'pending')
            ->with(['source', 'target'])
            ->orderByDesc('score')
            ->limit(300)
            ->get()
            ->filter(fn ($s) => $s->source && $s->target);

        if (trim($this->search) !== '') {
            $needle = mb_strtolower(trim($this->search));
            $pending = $pending->filter(fn ($s) => str_contains(mb_strtolower($s->source->name ?? $s->source->title ?? ''), $needle)
                || str_contains(mb_strtolower($s->target->name ?? $s->target->title ?? ''), $needle));
        }

        $applied = LinkSuggestion::query()
            ->where('status', 'applied')
            ->with(['source', 'target'])
            ->latest('applied_at')
            ->limit(50)
            ->get()
            ->filter(fn ($s) => $s->source && $s->target);

        return [
            'groups' => $pending->groupBy(fn ($s) => $s->source_type.'#'.$s->source_id),
            'applied' => $applied,
            'stats' => (object) [
                'pending' => LinkSuggestion::where('status', 'pending')->count(),
                'applied' => LinkSuggestion::where('status', 'applied')->count(),
                'phrases' => LinkTarget::count(),
            ],
        ];
    }
}
