<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingClassResource\Pages\CreateShippingClass;
use App\Filament\Resources\ShippingClassResource\Pages\EditShippingClass;
use App\Filament\Resources\ShippingClassResource\Pages\ListShippingClasses;
use App\Models\ShippingClass;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ShippingClassResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = ShippingClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                TextInput::make('extra_cost')->numeric()->default(0)->helperText('Added to shipping cost for products in this class.'),
            ]),
            Textarea::make('description')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('extra_cost')->money(setting('general.currency', 'USD'))->sortable(),
                TextColumn::make('products_count')->counts('products')->label('Products'),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingClasses::route('/'),
            'create' => CreateShippingClass::route('/create'),
            'edit' => EditShippingClass::route('/{record}/edit'),
        ];
    }
}
