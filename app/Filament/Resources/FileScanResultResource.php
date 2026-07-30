<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileScanResultResource\Pages\ListFileScanResults;
use App\Models\FileScanResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FileScanResultResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = FileScanResult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 5;

    protected static ?string $label = 'File scan';

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
                TextColumn::make('path')->limit(60)->searchable(),
                TextColumn::make('issue')->badge()->color(fn (string $state) => match ($state) {
                    'eval_usage', 'shell_exec', 'php_in_uploads' => 'danger',
                    'obfuscated', 'base64_abuse' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('severity')->badge()->color(fn (string $state) => match ($state) {
                    'critical' => 'danger',
                    'high' => 'warning',
                    default => 'gray',
                }),
                IconColumn::make('is_resolved')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']),
                SelectFilter::make('is_resolved')->options([1 => 'Resolved', 0 => 'Open']),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label('Mark resolved')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (FileScanResult $r) => ! $r->is_resolved)
                    ->action(fn (FileScanResult $r) => $r->update(['is_resolved' => true])),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFileScanResults::route('/'),
        ];
    }
}
