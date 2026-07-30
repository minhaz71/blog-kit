<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\InternalLink;
use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\InternalLinkScanner;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * RankMath-style internal link report: inbound/outbound counts per product
 * and post (editorial links only — menus/pages excluded), orphan lists,
 * live search, and a drill-down showing exactly which pages link to a
 * product and with what anchor text. Counts come from two aggregate
 * queries; drill-down details load only when opened.
 */
class InternalLinksReport extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Internal links';

    protected string $view = 'filament.pages.internal-links-report';

    /** Table rows are capped — the page must stay fast on huge catalogs. */
    protected const MAX_ROWS = 200;

    public string $search = '';

    /** "Product:5" | "Post:3" — the row whose detail panel is open. */
    public ?string $detail = null;

    public function syncNow(): void
    {
        @set_time_limit(300);

        $stats = app(InternalLinkScanner::class)->scanAll();

        Notification::make()
            ->title("Link index rebuilt in {$stats['seconds']}s — {$stats['links']} link(s) across {$stats['sources']} live products/posts.")
            ->success()
            ->send();
    }

    public function showDetail(string $type, int $id): void
    {
        $base = class_basename($type);
        $this->detail = in_array($base, ['Post', 'Category'], true) ? "{$base}:{$id}" : "Product:{$id}";
    }

    /** Map a "Product|Post|Category" row key back to its model class. */
    protected function classForBasename(string $basename): string
    {
        return match ($basename) {
            'Post' => Post::class,
            'Category' => Category::class,
            default => Product::class,
        };
    }

    public function closeDetail(): void
    {
        $this->detail = null;
    }

    /** Remove ONE inbound link (by internal_links row id) without opening the source page. */
    public function unlink(int $linkId): void
    {
        $link = InternalLink::with(['source', 'target'])->find($linkId);

        if (! $link || ! $link->source || ! $link->target) {
            Notification::make()->title('Link no longer exists')->warning()->send();

            return;
        }

        try {
            $removed = app(\App\Services\Seo\LinkApplier::class)
                ->unlink($link->source, $link->target->url(), $link->anchor);

            Notification::make()
                ->title($removed ? 'Link removed from "'.($link->source->name ?? $link->source->title).'"'
                    : 'Nothing to remove — the link was already gone; index refreshed.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Could not unlink')->body($e->getMessage())->warning()->send();
        }
    }

    /** Remove EVERY inbound link to the open target in one go. */
    public function unlinkAllInbound(): void
    {
        if (! $this->detail) {
            return;
        }

        @set_time_limit(300);

        [$basename, $id] = explode(':', $this->detail);
        $class = $this->classForBasename($basename);
        $target = $class::find((int) $id);

        if (! $target) {
            return;
        }

        $applier = app(\App\Services\Seo\LinkApplier::class);
        $count = 0;

        InternalLink::query()
            ->where('target_type', $class)->where('target_id', $target->id)
            ->with('source')->get()
            ->each(function ($link) use ($applier, $target, &$count): void {
                if ($link->source && $applier->unlink($link->source, $target->url(), $link->anchor)) {
                    $count++;
                }
            });

        Notification::make()
            ->title("Removed {$count} inbound link(s) to \"".($target->name ?? $target->title).'".')
            ->success()
            ->send();
    }

    /** Lazily-loaded drill-down for the open row: who links here + where it links. */
    protected function detailData(): ?object
    {
        if (! $this->detail) {
            return null;
        }

        [$basename, $id] = explode(':', $this->detail);
        $class = $this->classForBasename($basename);
        $model = $class::find((int) $id);

        if (! $model) {
            return null;
        }

        $inbound = InternalLink::query()
            ->where('target_type', $class)
            ->where('target_id', $model->id)
            ->with('source')
            ->limit(100)
            ->get()
            ->filter(fn ($link) => $link->source !== null)
            ->map(fn ($link) => (object) [
                'id' => $link->id,
                'name' => $link->source->name ?? $link->source->title,
                'url' => $link->source->url(),
                'anchor' => $link->anchor,
                'kind' => class_basename($link->source_type),
            ]);

        $outbound = InternalLink::query()
            ->where('source_type', $class)
            ->where('source_id', $model->id)
            ->with('target')
            ->limit(100)
            ->get()
            ->filter(fn ($link) => $link->target !== null)
            ->map(fn ($link) => (object) [
                'name' => $link->target->name ?? $link->target->title,
                'url' => $link->target->url(),
                'anchor' => $link->anchor,
                'kind' => class_basename($link->target_type),
            ]);

        return (object) [
            'name' => $model->name ?? $model->title,
            'url' => $model->url(),
            'kind' => class_basename($class),
            'inbound' => $inbound,
            'outbound' => $outbound,
        ];
    }

    protected function getViewData(): array
    {
        // Two aggregate queries build every count on this page — no N+1.
        $inbound = InternalLink::query()
            ->selectRaw('target_type, target_id, COUNT(*) as links')
            ->groupBy('target_type', 'target_id')
            ->get()
            ->groupBy('target_type');

        $outbound = InternalLink::query()
            ->selectRaw('source_type, source_id, COUNT(*) as links')
            ->groupBy('source_type', 'source_id')
            ->get()
            ->groupBy('source_type');

        $productInbound = ($inbound[Product::class] ?? collect())->pluck('links', 'target_id');
        $productOutbound = ($outbound[Product::class] ?? collect())->pluck('links', 'source_id');
        $postInbound = ($inbound[Post::class] ?? collect())->pluck('links', 'target_id');
        $postOutbound = ($outbound[Post::class] ?? collect())->pluck('links', 'source_id');
        $categoryInbound = ($inbound[Category::class] ?? collect())->pluck('links', 'target_id');
        $categoryOutbound = ($outbound[Category::class] ?? collect())->pluck('links', 'source_id');

        // Live pages only — matching what the scanner indexes as sources.
        $products = Product::query()
            ->where('status', 'published')
            ->select(['id', 'name', 'slug', 'status'])
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->get()
            ->map(fn ($p) => (object) [
                'id' => $p->id,
                'kind' => 'Product',
                'name' => $p->name,
                'url' => $p->url(),
                'editUrl' => \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $p]),
                'inbound' => (int) ($productInbound[$p->id] ?? 0),
                'outbound' => (int) ($productOutbound[$p->id] ?? 0),
            ]);

        $posts = Post::query()
            ->published()
            ->select(['id', 'title', 'slug'])
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'like', '%'.trim($this->search).'%'))
            ->get()
            ->map(fn ($p) => (object) [
                'id' => $p->id,
                'kind' => 'Post',
                'name' => $p->title,
                'url' => $p->url(),
                'editUrl' => \App\Filament\Resources\PostResource::getUrl('edit', ['record' => $p]),
                'inbound' => (int) ($postInbound[$p->id] ?? 0),
                'outbound' => (int) ($postOutbound[$p->id] ?? 0),
            ]);

        $categories = Category::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'slug'])
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->get()
            ->map(fn ($c) => (object) [
                'id' => $c->id,
                'kind' => 'Category',
                'name' => $c->name,
                'url' => $c->url(),
                'editUrl' => \App\Filament\Resources\CategoryResource::getUrl('edit', ['record' => $c]),
                'inbound' => (int) ($categoryInbound[$c->id] ?? 0),
                'outbound' => (int) ($categoryOutbound[$c->id] ?? 0),
            ]);

        // Fewest inbound first — those are the pages that need action.
        $products = $products->sortBy([['inbound', 'asc'], ['name', 'asc']])->values();
        $posts = $posts->sortBy([['inbound', 'asc'], ['name', 'asc']])->values();
        $categories = $categories->sortBy([['inbound', 'asc'], ['name', 'asc']])->values();

        $distribution = [
            'orphans' => $products->where('inbound', 0)->count() + $posts->where('inbound', 0)->count() + $categories->where('inbound', 0)->count(),
            'weak' => $products->whereBetween('inbound', [1, 2])->count() + $posts->whereBetween('inbound', [1, 2])->count() + $categories->whereBetween('inbound', [1, 2])->count(),
            'healthy' => $products->where('inbound', '>=', 3)->count() + $posts->where('inbound', '>=', 3)->count() + $categories->where('inbound', '>=', 3)->count(),
        ];

        return [
            'scannedAt' => setting('seo.links_scanned_at'),
            'totalLinks' => InternalLink::count(),
            'productTotal' => $products->count(),
            'products' => $products->take(self::MAX_ROWS),
            'orphanProducts' => $products->where('inbound', 0)->values(),
            'posts' => $posts->take(self::MAX_ROWS),
            'orphanPosts' => $posts->where('inbound', 0)->values(),
            'categoryTotal' => $categories->count(),
            'categories' => $categories->take(self::MAX_ROWS),
            'orphanCategories' => $categories->where('inbound', 0)->values(),
            'distribution' => $distribution,
            'detailData' => $this->detailData(),
        ];
    }
}
