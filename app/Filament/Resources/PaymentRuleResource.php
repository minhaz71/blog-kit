<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentRuleResource\Pages\CreatePaymentRule;
use App\Filament\Resources\PaymentRuleResource\Pages\EditPaymentRule;
use App\Filament\Resources\PaymentRuleResource\Pages\ListPaymentRules;
use App\Models\PaymentRule;
use App\Models\ShippingMethod;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PaymentRuleResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = PaymentRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rule')->columns(2)->schema([
                TextInput::make('name')->required()
                    ->helperText('Descriptive name — e.g. "COD surcharge (Dubai)".'),
                Select::make('payment_method')
                    ->required()
                    ->native(false)
                    ->options([
                        '*' => 'Any payment method',
                        'cod' => 'Cash on delivery',
                        'bank_transfer' => 'Bank transfer',
                        'stripe' => 'Stripe (card)',
                        'paypal' => 'PayPal',
                    ])
                    ->helperText('The gateway this rule matches. Choose "Any" to apply regardless of gateway.'),
                TextInput::make('priority')->numeric()->default(10)
                    ->helperText('Lower runs first when multiple rules match.'),
                Toggle::make('is_active')->default(true)->inline(false),
                TextInput::make('customer_message')
                    ->columnSpanFull()
                    ->helperText('Optional message shown at checkout when this rule applies.'),
            ]),
            Section::make('When to apply')->columns(2)->schema([
                TextInput::make('min_order_amount')->numeric()->prefix(setting('general.currency_symbol', '$')),
                TextInput::make('max_order_amount')->numeric()->prefix(setting('general.currency_symbol', '$')),
                TagsInput::make('allowed_countries')->helperText('ISO codes (US, CA, GB...). Empty = any.'),
                TagsInput::make('blocked_countries')->helperText('Never apply for these countries.'),
                TagsInput::make('allowed_cities')->helperText('Case-insensitive city names.'),
                TagsInput::make('blocked_cities'),
                Select::make('allowed_shipping_methods')
                    ->multiple()
                    ->options(fn () => ShippingMethod::query()->with('zone')->get()->mapWithKeys(fn ($m) => [$m->id => ($m->zone?->name ?? 'Zone').': '.$m->title])->all())
                    ->searchable()
                    ->preload()
                    ->helperText('Restrict to these shipping methods. Empty = any.'),
                Select::make('blocked_shipping_methods')
                    ->multiple()
                    ->options(fn () => ShippingMethod::query()->with('zone')->get()->mapWithKeys(fn ($m) => [$m->id => ($m->zone?->name ?? 'Zone').': '.$m->title])->all())
                    ->searchable()
                    ->preload(),
                Toggle::make('first_order_only')->inline(false)
                    ->helperText('Only apply for the customer\'s first order.'),
            ]),
            Section::make('Adjustment')->columns(2)->schema([
                TextInput::make('fee_amount')->numeric()->default(0)->prefix(setting('general.currency_symbol', '$'))
                    ->helperText('Positive fee added to the order (e.g. 10 for a COD surcharge).'),
                TextInput::make('discount_amount')->numeric()->default(0)->prefix(setting('general.currency_symbol', '$'))
                    ->helperText('Flat discount off the subtotal.'),
                TextInput::make('discount_percent')->numeric()->suffix('%')->default(0)
                    ->helperText('Percentage discount off the subtotal (e.g. 5 = 5%).'),
                Toggle::make('free_shipping')->inline(false),
                Toggle::make('disallow_coupons')->inline(false)
                    ->helperText('Prevent regular coupons stacking with this rule (e.g. no coupons for COD).'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('payment_method')->badge()->formatStateUsing(fn (string $state) => $state === '*' ? 'Any' : Str::headline($state)),
                TextColumn::make('fee_amount')->money(setting('general.currency', 'USD'))->label('Fee')->toggleable(),
                TextColumn::make('discount_amount')->money(setting('general.currency', 'USD'))->label('Discount')->toggleable(),
                TextColumn::make('discount_percent')->suffix('%')->label('% off')->toggleable(),
                IconColumn::make('free_shipping')->boolean()->label('Free ship'),
                TextColumn::make('priority')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('payment_method')->options([
                    '*' => 'Any',
                    'cod' => 'COD',
                    'bank_transfer' => 'Bank transfer',
                    'stripe' => 'Stripe',
                    'paypal' => 'PayPal',
                ]),
            ])
            ->recordActions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->defaultSort('priority');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentRules::route('/'),
            'create' => CreatePaymentRule::route('/create'),
            'edit' => EditPaymentRule::route('/{record}/edit'),
        ];
    }
}
