<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoginAttemptResource\Pages\ListLoginAttempts;
use App\Models\LoginAttempt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LoginAttemptResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = LoginAttempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('ip_address')->searchable()->label('IP'),
                IconColumn::make('successful')->boolean(),
                IconColumn::make('is_admin_area')->boolean()->label('Admin'),
                TextColumn::make('user_agent')->limit(40)->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('failed')->query(fn (Builder $q) => $q->where('successful', false))->toggle(),
                Filter::make('admin_area')->query(fn (Builder $q) => $q->where('is_admin_area', true))->toggle(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginAttempts::route('/'),
        ];
    }
}
