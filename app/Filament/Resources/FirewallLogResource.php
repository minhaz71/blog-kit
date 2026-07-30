<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirewallLogResource\Pages\ListFirewallLogs;
use App\Models\FirewallLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FirewallLogResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = FirewallLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 2;

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
                TextColumn::make('rule')->badge()->searchable()->color(fn (string $state) => match ($state) {
                    'sqli', 'xss' => 'danger',
                    'blocked_ip', 'rate_limit' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('ip_address')->searchable()->label('IP'),
                TextColumn::make('method')->badge(),
                TextColumn::make('url')->limit(50),
                TextColumn::make('user_agent')->limit(30)->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('rule')->options([
                    'sqli' => 'SQL injection',
                    'xss' => 'XSS',
                    'traversal' => 'Path traversal',
                    'bad_bot' => 'Bad bot',
                    'sensitive_file' => 'Sensitive file',
                    'scanner_path' => 'Scanner path',
                    'blocked_ip' => 'Blocked IP',
                    'rate_limit' => 'Rate limit',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFirewallLogs::route('/'),
        ];
    }
}
