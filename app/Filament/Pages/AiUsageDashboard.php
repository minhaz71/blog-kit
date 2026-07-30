<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiImportBatchResource;
use App\Models\AiImportItem;
use App\Models\AiUsageLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AiUsageDashboard extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'AI usage & cost';

    protected string $view = 'filament.pages.ai-usage-dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newBatch')
                ->label('New product batch')
                ->icon(Heroicon::OutlinedSparkles)
                ->url(AiImportBatchResource::getUrl('create')),
            Action::make('batches')
                ->label('All batches')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->url(AiImportBatchResource::getUrl()),
            Action::make('settings')
                ->label('AI settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(AiSettings::getUrl()),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(function (): void {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['date', 'provider', 'model', 'purpose', 'batch', 'input_tokens', 'cached_tokens', 'output_tokens', 'cost_usd']);

                    AiUsageLog::with('batch:id,name')->orderBy('id')->chunk(500, function ($logs) use ($out): void {
                        foreach ($logs as $log) {
                            fputcsv($out, [
                                $log->created_at->toDateTimeString(),
                                $log->provider,
                                $log->model,
                                $log->purpose,
                                $log->batch?->name,
                                $log->input_tokens,
                                $log->cached_tokens,
                                $log->output_tokens,
                                $log->cost,
                            ]);
                        }
                    });

                    fclose($out);
                }, 'ai-usage-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv'])),
        ];
    }

    /** Aggregates for the Blade view; re-queried on every Livewire poll. */
    protected function getViewData(): array
    {
        $totals = fn ($query) => $query
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('COALESCE(SUM(input_tokens),0) as input')
            ->selectRaw('COALESCE(SUM(output_tokens),0) as output')
            ->selectRaw('COALESCE(SUM(cached_tokens),0) as cached')
            ->selectRaw('COALESCE(SUM(cost),0) as cost')
            ->first();

        $allTime = $totals(AiUsageLog::query());

        return [
            'today' => $totals(AiUsageLog::whereDate('created_at', today())),
            'week' => $totals(AiUsageLog::where('created_at', '>=', now()->subDays(7))),
            'allTime' => $allTime,
            'byModel' => AiUsageLog::query()
                ->select('provider', 'model')
                ->selectRaw('COUNT(*) as requests')
                ->selectRaw('SUM(input_tokens) as input')
                ->selectRaw('SUM(output_tokens) as output')
                ->selectRaw('SUM(cached_tokens) as cached')
                ->selectRaw('SUM(cost) as cost')
                ->groupBy('provider', 'model')
                ->orderByDesc(DB::raw('SUM(cost)'))
                ->get(),
            'byProduct' => $this->productCosts(),
            'recent' => AiUsageLog::with('batch:id,name')
                ->latest()
                ->limit(15)
                ->get(),
            'cacheSavings' => self::cacheSavings(),
        ];
    }

    /** Product-wise spend: every AI-written product with its token + $ cost. */
    protected function productCosts(): \Illuminate\Support\Collection
    {
        $perItem = AiUsageLog::query()
            ->whereNotNull('item_id')
            ->selectRaw('item_id')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('SUM(input_tokens) as input')
            ->selectRaw('SUM(cached_tokens) as cached')
            ->selectRaw('SUM(output_tokens) as output')
            ->selectRaw('SUM(cost) as cost')
            ->groupBy('item_id')
            ->orderByDesc(DB::raw('SUM(cost)'))
            ->limit(50)
            ->get();

        $itemsById = AiImportItem::with(['product:id,name', 'batch:id,name'])
            ->findMany($perItem->pluck('item_id'))
            ->keyBy('id');

        return $perItem->map(function ($row) use ($itemsById): object {
            $item = $itemsById->get($row->item_id);

            return (object) [
                'name' => $item
                    ? ($item->product?->name ?? ($item->row['name'] ?? "Item #{$row->item_id}"))
                    : "Item #{$row->item_id}",
                'product_id' => $item?->product_id,
                'batch' => $item?->batch?->name,
                'status' => $item?->status,
                'requests' => (int) $row->requests,
                'input' => (int) $row->input,
                'cached' => (int) $row->cached,
                'output' => (int) $row->output,
                'cost' => (float) $row->cost,
            ];
        });
    }

    /**
     * Estimated $ saved by prompt caching (cached tokens billed at cache
     * rate vs full input rate). One aggregate row per model — constant
     * memory regardless of history size (this runs on every 10s poll).
     */
    protected static function cacheSavings(): float
    {
        return (float) AiUsageLog::query()
            ->selectRaw('model, COALESCE(SUM(cached_tokens), 0) as cached')
            ->groupBy('model')
            ->get()
            ->sum(function ($row): float {
                [$inPrice, , $cachePrice] = AiUsageLog::priceFor($row->model);

                return (int) $row->cached * max(0, $inPrice - $cachePrice) / 1_000_000;
            });
    }
}
