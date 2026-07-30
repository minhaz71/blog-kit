<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use App\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use App\Models\ShippingZone;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShippingZoneResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = ShippingZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('sort_order')->numeric()->default(0),
                TagsInput::make('countries')->helperText('Two-letter ISO codes, e.g. US, CA, GB. Leave empty for rest of world.'),
                TagsInput::make('states')->helperText('State/region names or codes.'),
                TagsInput::make('cities'),
                TagsInput::make('postcodes'),
                Toggle::make('is_active')->default(true),
            ]),
            Repeater::make('methods')
                ->relationship()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('type')
                            ->options([
                                'flat_rate' => 'Flat rate',
                                'free_shipping' => 'Free shipping',
                                'local_pickup' => 'Local pickup',
                                'weight_based' => 'Weight-based',
                                'value_based' => 'Value-based',
                            ])
                            ->default('flat_rate')
                            ->required()
                            ->native(false),
                        TextInput::make('title')->required(),
                        TextInput::make('cost')->numeric()->default(0),
                        TextInput::make('min_order_amount')->numeric()->label('Free/tier threshold'),
                        TextInput::make('delivery_estimate'),
                        TextInput::make('sort_order')->numeric()->default(0),
                        Toggle::make('is_active')->default(true),
                    ]),
                    Textarea::make('description')->rows(2)->columnSpanFull(),
                    Fieldset::make('Weight tiers (weight-based methods)')
                        ->columns(1)
                        ->schema([
                            Repeater::make('weight_tiers')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('up_to_kg')->numeric()->required()->label('Up to (kg)'),
                                        TextInput::make('cost')->numeric()->required(),
                                    ]),
                                ])
                                ->reorderable(false)
                                ->addActionLabel('Add tier')
                                ->columnSpanFull(),
                        ]),
                    Fieldset::make('Per shipping class surcharge')->columns(1)->schema([
                        KeyValue::make('class_costs')
                            ->keyLabel('Shipping class slug')
                            ->valueLabel('Extra cost')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
                    Fieldset::make('Advanced conditions (table-rate)')->columns(2)->schema([
                        TextInput::make('conditions.min_qty')->numeric()->label('Min items'),
                        TextInput::make('conditions.max_qty')->numeric()->label('Max items'),
                        TextInput::make('conditions.min_weight_kg')->numeric()->label('Min weight (kg)'),
                        TextInput::make('conditions.max_weight_kg')->numeric()->label('Max weight (kg)'),
                        TextInput::make('conditions.min_subtotal')->numeric()->label('Min subtotal'),
                        TextInput::make('conditions.max_subtotal')->numeric()->label('Max subtotal'),
                        TagsInput::make('conditions.allowed_shipping_class_slugs')->label('Only for these class slugs'),
                        TagsInput::make('conditions.allowed_customer_roles')->label('Only for these customer roles')
                            ->helperText('e.g. guest, customer, wholesale'),
                        Select::make('conditions.day_of_week')
                            ->multiple()
                            ->native(false)
                            ->options([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'])
                            ->label('Day of week'),
                        Grid::make(2)->schema([
                            TextInput::make('conditions.time_start')->label('Available from (HH:MM)'),
                            TextInput::make('conditions.time_end')->label('Available until (HH:MM)'),
                        ]),
                        TagsInput::make('conditions.allowed_postcodes')
                            ->helperText('Exact or "AB1*" prefix. Empty = any.'),
                        TagsInput::make('conditions.blocked_postcodes'),
                    ]),
                ])
                ->orderColumn('sort_order')
                ->columnSpanFull()
                ->addActionLabel('Add shipping method')
                ->itemLabel(fn (array $state) => ($state['title'] ?? '—').' ('.($state['type'] ?? '').')'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('methods_count')->counts('methods')->label('Methods'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
