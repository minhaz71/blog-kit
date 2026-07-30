<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class OrderResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'order_number';

    /** Human labels for each order status ("pending" reads as "Pending payment"). */
    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending payment',
            'processing' => 'Processing',
            'on_hold' => 'On hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'failed' => 'Failed',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Status')
                ->description('Move the order to “Pending payment” to edit its items. In any other status the items are locked — you can still change status or record payment.')
                ->columns(3)
                ->schema([
                    Select::make('status')
                        ->options(self::statusOptions())
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('payment_status')
                        ->options(array_combine(Order::PAYMENT_STATUSES, array_map(fn ($s) => Str::headline($s), Order::PAYMENT_STATUSES)))
                        ->required()
                        ->native(false),
                    Placeholder::make('placed_at')
                        ->label('Placed')
                        ->content(fn (?Order $record) => $record?->created_at?->format('M j, Y \a\t g:ia') ?? '—'),
                ]),

            Section::make('Items')
                ->description(fn (Get $get): string => $get('status') === 'pending'
                    ? 'Add, remove or change items and quantities. Totals recalculate when you save.'
                    : 'Locked. Set the status to “Pending payment” to edit items.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Grid::make(12)->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $product = \App\Models\Product::find($state);
                                        if ($product) {
                                            $set('name', $product->name);
                                            $set('sku', $product->sku);
                                            $set('unit_price', (float) $product->price);
                                        }
                                    })
                                    ->columnSpan(5),
                                TextInput::make('name')
                                    ->label('Line name')
                                    ->required()
                                    ->columnSpan(4),
                                TextInput::make('unit_price')
                                    ->label('Unit price')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->prefix(store_currency())
                                    ->columnSpan(2),
                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->addActionLabel('Add item')
                        ->reorderable(false)
                        ->disabled(fn (Get $get): bool => $get('status') !== 'pending')
                        ->columnSpanFull(),
                ]),

            Section::make('Totals')
                ->columns(4)
                ->schema([
                    Placeholder::make('t_subtotal')->label('Subtotal')
                        ->content(fn (?Order $record) => price_format((float) ($record?->subtotal ?? 0))),
                    Placeholder::make('t_discount')->label('Discount')
                        ->content(fn (?Order $record) => price_format((float) ($record?->discount_total ?? 0))),
                    Placeholder::make('t_shipping')->label('Shipping')
                        ->content(fn (?Order $record) => price_format((float) ($record?->shipping_total ?? 0))),
                    Placeholder::make('t_tax')->label('Tax')
                        ->content(fn (?Order $record) => price_format((float) ($record?->tax_total ?? 0))),
                    Placeholder::make('t_fee')
                        ->label(fn (?Order $record) => $record?->payment_fee_label ?: 'Payment fee')
                        ->content(fn (?Order $record) => price_format((float) ($record?->payment_fee ?? 0)))
                        ->visible(fn (?Order $record) => (float) ($record?->payment_fee ?? 0) > 0),
                    Placeholder::make('t_total')->label('Total')
                        ->content(fn (?Order $record) => new HtmlString('<span class="text-base font-bold">'.e(price_format((float) ($record?->total ?? 0))).'</span>')),
                ]),

            Section::make('Customer')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Placeholder::make('c_name')->label('Name')
                        ->content(fn (?Order $record) => $record?->customerName() ?: '—'),
                    Placeholder::make('c_email')->label('Email')
                        ->content(fn (?Order $record) => $record?->customer_email ?? '—'),
                    Placeholder::make('c_phone')->label('Phone')
                        ->content(fn (?Order $record) => $record?->customer_phone ?? '—'),
                    Placeholder::make('c_method')->label('Payment method')
                        ->content(fn (?Order $record) => $record?->payment_method ? Str::headline($record->payment_method) : '—'),
                    Placeholder::make('c_ship')->label('Shipping address')
                        ->content(fn (?Order $record) => new HtmlString(self::formatAddress($record?->shipping_address))),
                    Placeholder::make('c_bill')->label('Billing address')
                        ->content(fn (?Order $record) => new HtmlString(self::formatAddress($record?->billing_address))),
                ]),

            Textarea::make('customer_note')
                ->label('Customer note')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    protected static function formatAddress(?array $address): string
    {
        if (empty($address)) {
            return '—';
        }

        $lines = array_filter([
            trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? '')),
            $address['address_line_1'] ?? null,
            $address['address_line_2'] ?? null,
            trim(($address['city'] ?? '').' '.($address['postal_code'] ?? '')),
            $address['country'] ?? null,
        ]);

        return implode('<br>', array_map('e', $lines)) ?: '—';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable()->sortable(),
                TextColumn::make('customer_email')->searchable()->toggleable(),
                TextColumn::make('customer_phone')->toggleable(),
                TextColumn::make('total')
                    ->money(fn (Order $o) => $o->currency ?? 'USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'info',
                        'on_hold', 'pending' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('payment_status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('payment_method')->toggleable(),
                TextColumn::make('invoice_downloads_count')
                    ->counts('invoiceDownloads')
                    ->label('Invoice ⬇')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->tooltip('Times this order\'s PDF invoice was downloaded')
                    ->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Placed'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
                SelectFilter::make('payment_status')
                    ->options(array_combine(Order::PAYMENT_STATUSES, array_map(fn ($s) => Str::headline($s), Order::PAYMENT_STATUSES))),
                SelectFilter::make('payment_method')
                    ->options(fn () => \App\Models\PaymentMethod::pluck('name', 'key')->all()
                        + ['stripe' => 'Stripe', 'paypal' => 'PayPal']),
                \Filament\Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make()->label('View / edit'),
                \Filament\Actions\DeleteAction::make()->label('Trash'),
                \Filament\Actions\RestoreAction::make(),
                \Filament\Actions\ForceDeleteAction::make()
                    ->label('Delete forever')
                    ->modalDescription('Permanently removes the order and its items. It will no longer appear in any report. This cannot be undone.'),
                Action::make('markProcessing')
                    ->label('Processing')
                    ->color('info')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->visible(fn (Order $o) => ! $o->trashed() && $o->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn (Order $o) => $o->updateStatus('processing', auth()->user())),
                Action::make('markOnHold')
                    ->label('On hold')
                    ->color('warning')
                    ->icon(Heroicon::OutlinedPauseCircle)
                    ->visible(fn (Order $o) => ! $o->trashed() && in_array($o->status, ['pending', 'processing']))
                    ->requiresConfirmation()
                    ->action(fn (Order $o) => $o->updateStatus('on_hold', auth()->user())),
                Action::make('backToPending')
                    ->label('Pending payment (unlock)')
                    ->color('gray')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->visible(fn (Order $o) => ! $o->trashed() && in_array($o->status, ['processing', 'on_hold']))
                    ->requiresConfirmation()
                    ->modalDescription('Moves the order back to “Pending payment” so you can edit its items.')
                    ->action(fn (Order $o) => $o->updateStatus('pending', auth()->user())),
                Action::make('markCompleted')
                    ->label('Complete')
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (Order $o) => ! $o->trashed() && in_array($o->status, ['pending', 'processing', 'on_hold']))
                    ->requiresConfirmation()
                    ->modalDescription('Completing the order counts it as a final sale in your reports.')
                    ->action(fn (Order $o) => $o->updateStatus('completed', auth()->user())),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->visible(fn (Order $o) => ! $o->trashed() && ! in_array($o->status, ['completed', 'cancelled', 'refunded']))
                    ->requiresConfirmation()
                    ->action(fn (Order $o) => $o->updateStatus('cancelled', auth()->user())),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulkComplete')
                        ->label('Mark completed')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Completing counts these as final sales in your reports. Orders already completed, cancelled or refunded are skipped.')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $done = 0;
                            foreach ($records as $order) {
                                if (! $order->trashed() && in_array($order->status, ['pending', 'processing', 'on_hold'], true)) {
                                    $order->updateStatus('completed', auth()->user());
                                    $done++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title("Marked {$done} order(s) completed")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\DeleteBulkAction::make()->label('Move to trash'),
                    \Filament\Actions\RestoreBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make()->label('Delete forever'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Include trashed rows so the "Trashed" filter can surface the trash box. */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
