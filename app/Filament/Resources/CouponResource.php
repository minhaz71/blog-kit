<?php

namespace App\Filament\Resources;

use App\Filament\Support\ResourceActions;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\CouponResource\Pages\EditCoupon;
use App\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CouponResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('code')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->helperText('Case-insensitive coupon code.'),
                Select::make('type')
                    ->options([
                        'percent' => 'Percentage discount',
                        'fixed_cart' => 'Fixed cart discount',
                        'fixed_product' => 'Fixed product discount',
                        'free_shipping' => 'Free shipping',
                        'bxgy' => 'Buy X get Y',
                        'first_order' => 'First-order discount',
                    ])
                    ->default('percent')
                    ->required()
                    ->live()
                    ->native(false),
                TextInput::make('value')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText(fn (Get $get) => $get('type') === 'percent' ? 'Percent, e.g. 10 = 10% off.' : 'Amount in store currency.'),
                Toggle::make('free_shipping')->inline(false)->helperText('Combine with discount above.'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('min_order_amount')->numeric()->minValue(0),
                TextInput::make('max_order_amount')->numeric()->minValue(0),
                TextInput::make('buy_qty')->numeric()->minValue(1)->label('Buy qty (X)')->visible(fn (Get $get) => $get('type') === 'bxgy'),
                TextInput::make('get_qty')->numeric()->minValue(1)->label('Get qty (Y)')->visible(fn (Get $get) => $get('type') === 'bxgy'),
                TextInput::make('usage_limit')->numeric()->minValue(1)->label('Total usage limit'),
                TextInput::make('usage_limit_per_user')->numeric()->minValue(1)->label('Per-customer limit'),
                DateTimePicker::make('starts_at')->seconds(false),
                DateTimePicker::make('expires_at')->seconds(false)->after('starts_at'),
            ]),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            Grid::make(3)->schema([
                Toggle::make('first_order_only'),
                Toggle::make('is_active')->default(true),
            ]),
            Select::make('products')->relationship('products', 'name')->multiple()->searchable()->preload()->label('Restricted to products'),
            Select::make('categories')->relationship('categories', 'name')->multiple()->searchable()->preload()->label('Restricted to categories'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => Str::headline($state)),
                TextColumn::make('value')->sortable(),
                TextColumn::make('used_count')->label('Uses')->sortable(),
                TextColumn::make('usage_limit')->label('Limit')->placeholder('—'),
                TextColumn::make('expires_at')->dateTime()->sortable()->placeholder('Never'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'percent' => 'Percentage',
                    'fixed_cart' => 'Fixed cart',
                    'fixed_product' => 'Fixed product',
                    'free_shipping' => 'Free shipping',
                    'bxgy' => 'Buy X get Y',
                    'first_order' => 'First-order',
                ]),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    ...ResourceActions::activeBulks(),\Filament\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
