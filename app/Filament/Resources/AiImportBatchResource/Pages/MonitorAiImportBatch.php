<?php

namespace App\Filament\Resources\AiImportBatchResource\Pages;

use App\Filament\Resources\AiImportBatchResource;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\AiUsageLog;
use Filament\Resources\Pages\Page;

class MonitorAiImportBatch extends Page
{
    protected static string $resource = AiImportBatchResource::class;

    protected string $view = 'filament.pages.ai-batch-monitor';

    public AiImportBatch $record;

    /** Batch kinds produced by the blog pipeline (not the ecommerce catalog). */
    protected const BLOG_KINDS = ['blog', 'blog_ideas', 'comparison_ideas'];

    /**
     * The monitor is shared by the ecommerce AI product batches AND the AI blog
     * pipeline. Its resource is ecommerce-gated (403 when the store is off), so
     * gate by the BATCH KIND instead: a blog/research batch is monitorable on a
     * blog-only site (via access_ai_blog_batches); a product batch keeps the
     * resource's ecommerce gate.
     */
    /**
     * This monitor is shared by the AI BLOG pipeline and the ecommerce AI
     * PRODUCT importer. Its resource is ecommerce-gated (403 when the store is
     * off), which wrongly blocked blog-batch monitoring on a blog-only site.
     * Filament calls canAccess() with NO record (and again on every Livewire
     * update), so we can't gate by the specific batch reliably — instead allow
     * anyone who can access EITHER AI batches. Both are admin-level content
     * tools, so this is safe.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return static::mayMonitor();
    }

    /**
     * The base resource Page enforces access via the CanAuthorizeResourceAccess
     * trait's mount/hydrate hooks, which call authorizeResourceAccess() and abort
     * 403 on the RESOURCE's gate (ecommerce) — bypassing canAccess(). Override it
     * so a blog-only site (store off) can still monitor blog batches.
     */
    public static function authorizeResourceAccess(): void
    {
        abort_unless(static::mayMonitor(), 403);
    }

    /** Allow anyone who can access EITHER the AI blog or AI product batches. */
    protected static function mayMonitor(): bool
    {
        return (bool) auth()->user()?->can('access_ai_blog_batches')
            || AiImportBatchResource::canAccess();
    }

    public function mount(AiImportBatch $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return 'Live monitor — '.$this->record->name;
    }

    /**
     * Breadcrumbs are derived from this page's $resource (the ecommerce product
     * importer, whose index 403s when the store is off). For a BLOG batch, point
     * the trail back to the AI Blog Batches list so a blog-only admin never
     * bounces into that forbidden ecommerce page.
     */
    public function getBreadcrumbs(): array
    {
        if (in_array($this->record->kind, self::BLOG_KINDS, true)) {
            return [
                \App\Filament\Resources\AiBlogBatchResource::getUrl() => 'AI blog batches',
                'Live monitor',
            ];
        }

        return parent::getBreadcrumbs();
    }

    /** Show where this batch's articles are headed (this site vs a connected spoke). */
    public function getSubheading(): ?string
    {
        if (! function_exists('is_network_hub') || ! is_network_hub()) {
            return null;
        }

        $ids = array_values(array_filter((array) $this->record->network_site_ids));
        if ($ids === []) {
            return 'Target: This site';
        }

        $names = \App\Models\ConnectedSite::whereIn('id', $ids)->pluck('name')->all();

        return 'Target: '.($names !== [] ? implode(', ', $names) : 'This site').' (connected site) — kept as a hidden draft here, pushed there.';
    }

    public function pauseBatch(): void
    {
        if ($this->record->status === 'processing') {
            $this->record->update(['status' => 'paused']);
            AiActivityLog::write($this->record->id, null, 'control', '⏸ Batch paused by user — running item finishes, the rest wait.', 'warning');
        }
    }

    public function resumeBatch(): void
    {
        // paused/stopped = user halted it; processing = a background run
        // stalled (e.g. the process was killed) and needs re-kicking.
        if (in_array($this->record->status, ['paused', 'stopped', 'processing'])) {
            $this->record->update(['status' => 'processing', 'error' => null]);
            AiActivityLog::write($this->record->id, null, 'control', '▶ Batch resumed — finishing the remaining products in the background.', 'success');

            // Background runner finishes everything left; fall back to the
            // queue if the environment can't spawn a process — dispatching the
            // RIGHT writer for the batch kind (blog vs product).
            if (! \App\Support\BackgroundProcess::artisan(['ai:run-batch', (string) $this->record->id])) {
                $isBlog = in_array($this->record->kind, self::BLOG_KINDS, true);
                foreach ($this->record->items()->whereIn('status', ['pending', 'failed'])->pluck('id') as $itemId) {
                    $isBlog
                        ? \App\Jobs\WriteAiBlogPost::dispatch($itemId)
                        : \App\Jobs\WriteAiProduct::dispatch($itemId);
                }
            }
        }
    }

    public function stopBatch(): void
    {
        if (in_array($this->record->status, ['processing', 'paused'])) {
            $waiting = $this->record->items()->whereIn('status', ['pending', 'failed'])->count();
            $this->record->update(['status' => 'stopped']);
            AiActivityLog::write($this->record->id, null, 'control', "⏹ Batch stopped by user — {$waiting} product(s) not processed. Resume anytime.", 'warning');
        }
    }

    /** Recover a batch whose parse job never ran (no worker at Start time). */
    public function parseNow(): void
    {
        if ($this->record->total_items === 0 && in_array($this->record->status, ['pending', 'processing'])) {
            \App\Jobs\StartAiImportBatch::dispatchSync($this->record);
        }
    }

    /** Run the next pending item in the foreground — no queue worker needed. */
    public function processNextItem(): void
    {
        $item = $this->record->items()
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                    ->orWhere(fn ($sq) => $sq->whereIn('status', ['writing', 'reviewing'])
                        ->where('updated_at', '<', now()->subMinutes(\App\Jobs\WriteAiProduct::RECLAIM_MINUTES)));
            })
            ->orderBy('id')
            ->first();

        if ($item === null) {
            return;
        }

        @set_time_limit(600);

        // The job's atomic claim makes this safe even if a queue worker
        // grabs the same item at the same moment — only one proceeds.
        \App\Jobs\WriteAiProduct::dispatchSync($item->id);
    }

    /**
     * Per-site cost breakdown: spend + tokens grouped by the site each article
     * was written for (item.site_key), plus a "planning" row for the cluster
     * calls that aren't tied to a single article. Returns [] for a single-site
     * batch so the panel only appears when it's actually multisite.
     *
     * @return array<int, array{label: string, cost: float, tokens: int, articles: int}>
     */
    protected function siteSpend(): array
    {
        $rows = AiUsageLog::query()
            ->join('ai_import_items', 'ai_usage_logs.item_id', '=', 'ai_import_items.id')
            ->where('ai_usage_logs.batch_id', $this->record->id)
            ->groupBy('ai_import_items.site_key')
            ->selectRaw('ai_import_items.site_key as site_key,
                SUM(ai_usage_logs.cost) as cost,
                SUM(ai_usage_logs.input_tokens + ai_usage_logs.output_tokens) as tokens,
                COUNT(DISTINCT ai_import_items.id) as articles')
            ->get();

        // Only worth showing when more than one distinct site is involved.
        if ($rows->count() < 2 && ! $rows->contains(fn ($r) => ctype_digit((string) $r->site_key))) {
            return [];
        }

        $siteNames = \App\Models\ConnectedSite::query()->pluck('name', 'id');
        $localName = (string) setting('general.site_name', config('app.name'));

        $out = $rows->map(fn ($r) => [
            'label' => match (true) {
                $r->site_key === \App\Services\Network\NetworkTargets::LOCAL => "This site — {$localName}",
                $r->site_key === \App\Services\Network\NetworkTargets::SHARED => 'Shared (multiple sites)',
                ctype_digit((string) $r->site_key) => (string) ($siteNames[(int) $r->site_key] ?? "Site #{$r->site_key}"),
                default => 'Unattributed',
            },
            'cost' => (float) $r->cost,
            'tokens' => (int) $r->tokens,
            'articles' => (int) $r->articles,
        ])->sortByDesc('cost')->values()->all();

        // Planning calls (no item_id) — one cluster call per site.
        $planningCost = (float) AiUsageLog::where('batch_id', $this->record->id)->whereNull('item_id')->sum('cost');
        $planningTokens = (int) AiUsageLog::where('batch_id', $this->record->id)->whereNull('item_id')->sum(\Illuminate\Support\Facades\DB::raw('input_tokens + output_tokens'));

        if ($planningCost > 0) {
            $out[] = ['label' => 'Planning (all sites)', 'cost' => $planningCost, 'tokens' => $planningTokens, 'articles' => 0];
        }

        return $out;
    }

    protected function getViewData(): array
    {
        $this->record->refresh();

        $pendingItems = $this->record->items()->whereIn('status', ['pending', 'failed'])->count();

        // "No worker" banner only when BOTH signals agree: this batch shows
        // no recent item activity AND queued jobs sit unclaimed — a worker
        // busy on one long AI item must not trigger a false alarm.
        $workerStalled = false;

        if ($pendingItems > 0 && config('queue.default') === 'database') {
            try {
                $batchActive = $this->record->items()
                    ->where('updated_at', '>=', now()->subSeconds(90))
                    ->whereIn('status', ['writing', 'reviewing'])
                    ->exists();

                $workerStalled = ! $batchActive && \Illuminate\Support\Facades\DB::table('jobs')
                    ->whereNull('reserved_at')
                    ->where('created_at', '<=', now()->subSeconds(60)->getTimestamp())
                    ->exists();
            } catch (\Throwable) {
                // jobs table missing — can't tell; stay quiet.
            }
        }

        // Prompt-cache effectiveness: how much of the input was served from
        // cache (10% of the normal token price) and what that saved.
        $usage = AiUsageLog::where('batch_id', $this->record->id)
            ->selectRaw('SUM(input_tokens) as input, SUM(cached_tokens) as cached, SUM(cost) as cost')
            ->first();

        $cacheHitRate = ($usage->input ?? 0) > 0 ? (int) round(100 * $usage->cached / $usage->input) : 0;

        // Each cached token would otherwise bill at the full input rate —
        // savings ≈ cached × (input price − cache price), summed per model.
        $cacheSaved = 0.0;
        foreach (AiUsageLog::where('batch_id', $this->record->id)->get(['model', 'cached_tokens']) as $row) {
            [$inPrice, , $cachePrice] = AiUsageLog::priceFor($row->model);
            $cacheSaved += $row->cached_tokens * ($inPrice - $cachePrice) / 1_000_000;
        }

        return [
            'batch' => $this->record,
            'siteSpend' => $this->siteSpend(),
            'items' => $this->record->items()->orderBy('id')->get(),
            'feed' => AiActivityLog::where('batch_id', $this->record->id)
                ->latest('id')
                ->limit(60)
                ->get(),
            'spend' => (float) ($usage->cost ?? 0),
            'cacheHitRate' => $cacheHitRate,
            'cacheSaved' => $cacheSaved,
            'workerStalled' => $workerStalled,
            'pendingItems' => $pendingItems,
        ];
    }
}
