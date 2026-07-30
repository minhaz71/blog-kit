<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CustomerResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?string $label = 'Customer';

    protected static ?string $pluralLabel = 'Customers';

    public static function getEloquentQuery(): Builder
    {
        // Customers = users with no admin roles.
        return parent::getEloquentQuery()->whereDoesntHave('roles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone'),
                Select::make('customer_group_id')->relationship('customerGroup', 'name')->preload(),
                Toggle::make('is_active')->default(true),
                Toggle::make('accepts_marketing'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->toggleable(),
                TextColumn::make('orders_count')->counts('orders')->label('Orders')->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('created_at')->date()->sortable()->label('Joined'),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
