<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRateResource\Pages\CreateTaxRate;
use App\Filament\Resources\TaxRateResource\Pages\EditTaxRate;
use App\Filament\Resources\TaxRateResource\Pages\ListTaxRates;
use App\Models\TaxRate;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TaxRateResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('rate')->numeric()->required()->suffix('%')->helperText('Percent value, e.g. 7.5.'),
                TextInput::make('country')->maxLength(2)->helperText('Two-letter ISO code.'),
                TextInput::make('state'),
                TextInput::make('city'),
                TextInput::make('postal_code'),
                TextInput::make('tax_class')->default('standard')->required(),
                TextInput::make('priority')->numeric()->default(1),
            ]),
            Grid::make(2)->schema([
                Toggle::make('applies_to_shipping'),
                Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('rate')->suffix('%')->sortable(),
                TextColumn::make('country')->toggleable(),
                TextColumn::make('state')->toggleable(),
                TextColumn::make('tax_class')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('priority')->sortable(),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('priority');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxRates::route('/'),
            'create' => CreateTaxRate::route('/create'),
            'edit' => EditTaxRate::route('/{record}/edit'),
        ];
    }
}
