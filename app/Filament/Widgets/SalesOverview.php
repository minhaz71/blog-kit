<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Sales figures only make sense with the ecommerce module on. */
    public static function canView(): bool
    {
        return ecommerce_enabled();
    }

    protected function getStats(): array
    {
        $currency = setting('general.currency', 'USD');
        $today = now()->startOfDay();
        $weekAgo = now()->subDays(7);

        // Final sales = completed orders. These are the realised revenue that
        // rolls into the sales report; measured by when they were completed.
        $todaySales = (float) Order::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $today)
            ->sum('total');

        $weekSales = (float) Order::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $weekAgo)
            ->sum('total');

        // In-process orders = everything still moving through fulfilment.
        $inProcess = Order::query()->whereIn('status', ['pending', 'processing', 'on_hold']);
        $inProcessCount = (clone $inProcess)->count();
        $inProcessValue = (float) (clone $inProcess)->sum('total');

        $lowStock = \App\Models\Product::query()
            ->where('manage_stock', true)
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
            ->count();

        return [
            Stat::make('Completed sales today', number_format($todaySales, 2).' '.$currency)
                ->description('Final sales completed since midnight')
                ->color('success'),
            Stat::make('Completed sales last 7 days', number_format($weekSales, 2).' '.$currency)
                ->description('Final sales — rolling week')
                ->color('info'),
            Stat::make('Orders in process', (string) $inProcessCount)
                ->description(number_format($inProcessValue, 2).' '.$currency.' · pending, processing, on hold')
                ->color($inProcessCount > 20 ? 'warning' : 'gray'),
            Stat::make('Low stock', (string) $lowStock)
                ->description('At or under threshold')
                ->color($lowStock > 0 ? 'warning' : 'success'),
        ];
    }
}
