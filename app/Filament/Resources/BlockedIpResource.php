<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockedIpResource\Pages\CreateBlockedIp;
use App\Filament\Resources\BlockedIpResource\Pages\EditBlockedIp;
use App\Filament\Resources\BlockedIpResource\Pages\ListBlockedIps;
use App\Models\BlockedIp;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BlockedIpResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = BlockedIp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Blocked IP';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('ip_address')->required()->unique(ignoreRecord: true)->label('IP address'),
                TextInput::make('reason'),
                DateTimePicker::make('expires_at')->helperText('Leave empty for permanent block.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip_address')->searchable()->label('IP'),
                TextColumn::make('reason')->limit(50)->searchable(),
                TextColumn::make('expires_at')->dateTime()->placeholder('Permanent')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Blocked at'),
            ])
            ->recordActions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlockedIps::route('/'),
            'create' => CreateBlockedIp::route('/create'),
            'edit' => EditBlockedIp::route('/{record}/edit'),
        ];
    }
}
