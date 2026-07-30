<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailLogResource\Pages\ListEmailLogs;
use App\Models\EmailLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EmailLogResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = EmailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 21;

    protected static ?string $label = 'Email log';

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
                TextColumn::make('to_email')->searchable()->label('To'),
                TextColumn::make('subject')->limit(60)->searchable(),
                TextColumn::make('template_key')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'sent' ? 'success' : 'danger'),
                TextColumn::make('error')
                    ->label('Error / reason')
                    ->limit(80)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Sent at'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['sent' => 'Sent', 'failed' => 'Failed']),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailLogs::route('/'),
        ];
    }
}
