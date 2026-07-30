<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Str;

class RecentOrders extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent orders';

    /** Orders exist only with the ecommerce module on. */
    public static function canView(): bool
    {
        return ecommerce_enabled();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest()->limit(10))
            ->columns([
                TextColumn::make('order_number')->searchable(),
                TextColumn::make('customer_email')->limit(30),
                TextColumn::make('total')->money(setting('general.currency', 'USD')),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Str::headline($state))
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'processing' => 'info',
                        'pending', 'on_hold' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')->since()->label('Placed'),
            ])
            ->paginated(false);
    }
}
