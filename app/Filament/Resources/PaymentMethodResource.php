<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethodResource\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethodResource\Pages\ListPaymentMethods;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 11;

    protected static ?string $label = 'Payment method';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Method')
                ->description('These are the pay-on-delivery / manual options customers see at checkout. Online gateways (Stripe, PayPal) are configured under Payment gateways.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->placeholder('Card on Delivery')
                        ->helperText('The name customers see, e.g. "Cash on Delivery", "Card on Delivery".'),
                    Toggle::make('is_active')->default(true)->inline(false),
                    TextInput::make('description')
                        ->placeholder('Pay by card to the courier on delivery.')
                        ->helperText('Short line shown under the name.')
                        ->columnSpanFull(),
                    Textarea::make('instructions')
                        ->label('Checkout message')
                        ->rows(3)
                        ->placeholder('Pay via card on delivery — the courier will bring a card machine to your door.')
                        ->helperText('Shown when this method is selected and on the confirmation page / emails.')
                        ->columnSpanFull(),
                ]),
            Section::make('Surcharge (optional)')
                ->description('Add a charge for choosing this method. It appears as its own named line on the order total.')
                ->columns(3)
                ->schema([
                    TextInput::make('fee_fixed')
                        ->label('Fixed fee')
                        ->numeric()->default(0)->minValue(0)->prefix(store_currency()),
                    TextInput::make('fee_percent')
                        ->label('Percent of subtotal')
                        ->numeric()->default(0)->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('fee_label')
                        ->label('Charge name')
                        ->placeholder('Card payment charge')
                        ->helperText('What the fee line is called on the total.'),
                ]),
            Section::make('Behaviour')->columns(2)->schema([
                \Filament\Forms\Components\Select::make('mark_as')
                    ->label('After the customer places the order')
                    ->options([
                        'processing' => 'Confirm immediately (processing)',
                        'pending' => 'Hold as pending (awaiting confirmation)',
                    ])
                    ->default('processing')
                    ->native(false),
                TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower shows first at checkout.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('name')->searchable()->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                TextColumn::make('key')->badge()->color('gray')->toggleable(),
                TextColumn::make('fee')
                    ->label('Surcharge')
                    ->state(fn (PaymentMethod $r) => $r->hasFee()
                        ? trim(((float) $r->fee_fixed > 0 ? price_format((float) $r->fee_fixed) : '')
                            .((float) $r->fee_percent > 0 ? ' +'.$r->fee_percent.'%' : ''))
                            .($r->fee_label ? " ({$r->fee_label})" : '')
                        : '—'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
