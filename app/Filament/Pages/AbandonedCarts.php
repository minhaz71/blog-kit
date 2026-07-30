<?php

namespace App\Filament\Pages;

use App\Models\Cart;
use App\Models\Order;
use App\Support\AbandonedCartFlow;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use UnitEnum;

class AbandonedCarts extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 29;

    protected static ?string $title = 'Abandoned carts';

    protected string $view = 'filament.pages.abandoned-carts';

    /** Active carts with items + a reachable shopper, idle past the first stage. */
    protected function abandonedQuery()
    {
        $firstDelay = AbandonedCartFlow::firstDelayMinutes();

        return Cart::query()
            ->where('status', 'active')
            ->where('updated_at', '<', now()->subMinutes($firstDelay))
            ->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('user_id'))
            ->whereHas('items');
    }

    protected function getViewData(): array
    {
        $stageCount = AbandonedCartFlow::stageCount();

        $openCount = (clone $this->abandonedQuery())->count();

        $carts = (clone $this->abandonedQuery())
            ->with(['items.product', 'items.variation', 'user'])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $valueAtRisk = $carts->sum(fn (Cart $c) => $c->subtotal());

        $emailed = Cart::where('reminder_stage', '>', 0)->count();
        $remindersSent = (int) Cart::sum('reminder_stage');
        $recoveredCount = Cart::whereNotNull('recovered_at')->count();

        $recoveredRevenue = (float) Order::whereIn(
            'id',
            Cart::whereNotNull('recovered_at')->whereNotNull('order_id')->pluck('order_id')
        )->sum('total');

        $rows = $carts->map(fn (Cart $c) => [
            'email' => $c->recipientEmail(),
            'name' => $c->customer_name ?: ($c->user?->name ?: '—'),
            'guest' => $c->user_id === null,
            'items' => $c->itemCount(),
            'value' => price_format($c->subtotal()),
            'stage' => (int) $c->reminder_stage,
            'stage_count' => $stageCount,
            'last_active' => $c->updated_at?->diffForHumans(),
            'last_reminder' => $c->last_reminder_at?->diffForHumans() ?? 'Not yet',
        ])->all();

        return [
            'enabled' => AbandonedCartFlow::enabled(),
            'stages' => AbandonedCartFlow::stages(),
            'schedulerLive' => true,
            'settingsUrl' => Route::has('filament.admin.pages.abandoned-cart-settings')
                ? route('filament.admin.pages.abandoned-cart-settings')
                : null,
            'stats' => [
                'open' => $openCount,
                'value_at_risk' => price_format($valueAtRisk),
                'emailed' => $emailed,
                'reminders_sent' => $remindersSent,
                'recovered' => $recoveredCount,
                'recovered_revenue' => price_format($recoveredRevenue),
                'recovery_rate' => $emailed > 0 ? round($recoveredCount / $emailed * 100) : 0,
            ],
            'rows' => $rows,
        ];
    }
}
