<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ErrorLogResource\Pages\ListErrorLogs;
use App\Models\ErrorLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Admin-only application error log. Public visitors never see stack traces
 * (they get the friendly error page); the full technical detail lands here
 * for staff to diagnose and resolve.
 */
class ErrorLogResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static ?string $model = ErrorLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 95;

    protected static ?string $navigationLabel = 'Error log';

    protected static ?string $label = 'error';

    protected static ?string $recordTitleAttribute = 'message';

    public static function getNavigationBadge(): ?string
    {
        $open = ErrorLog::where('resolved', false)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('resolved')
                    ->label('')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedExclamationCircle)
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('exception_class')
                    ->label('Type')
                    ->formatStateUsing(fn (ErrorLog $r) => $r->shortClass())
                    ->badge()->color('gray'),
                TextColumn::make('message')->limit(60)->wrap()->searchable()
                    ->description(fn (ErrorLog $r) => $r->file ? class_basename($r->file).':'.$r->line : null),
                TextColumn::make('status_code')->label('HTTP')->badge()
                    ->color(fn ($state) => $state >= 500 ? 'danger' : 'warning'),
                TextColumn::make('occurrences')->label('Count')->alignCenter()->sortable()
                    ->badge()->color(fn ($state) => $state > 10 ? 'danger' : ($state > 1 ? 'warning' : 'gray')),
                TextColumn::make('url')->limit(35)->toggleable()->url(fn (ErrorLog $r) => $r->url, true),
                TextColumn::make('last_seen_at')->label('Last seen')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('resolved')->options([0 => 'Open', 1 => 'Resolved'])->default(0),
                SelectFilter::make('status_code')->options([500 => '500', 503 => '503']),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                Action::make('toggleResolved')
                    ->label(fn (ErrorLog $r) => $r->resolved ? 'Reopen' : 'Mark resolved')
                    ->icon(fn (ErrorLog $r) => $r->resolved ? Heroicon::OutlinedArrowPath : Heroicon::OutlinedCheck)
                    ->color(fn (ErrorLog $r) => $r->resolved ? 'gray' : 'success')
                    ->action(fn (ErrorLog $r) => $r->update(['resolved' => ! $r->resolved])),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('resolveSelected')
                    ->label('Mark resolved')
                    ->icon(Heroicon::OutlinedCheck)->color('success')
                    ->action(fn (Collection $records) => $records->each->update(['resolved' => true]))
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Error')->columns(2)->schema([
                TextEntry::make('exception_class')->label('Exception'),
                TextEntry::make('status_code')->label('HTTP status'),
                TextEntry::make('message')->columnSpanFull(),
                TextEntry::make('file')->columnSpanFull()
                    ->formatStateUsing(fn (ErrorLog $r) => $r->file.($r->line ? ':'.$r->line : '')),
            ]),
            Section::make('Request')->columns(2)->schema([
                TextEntry::make('method'),
                TextEntry::make('url')->columnSpanFull(),
                TextEntry::make('user_id')->label('User ID')->placeholder('Guest'),
                TextEntry::make('ip')->label('IP address'),
                TextEntry::make('occurrences')->badge(),
                TextEntry::make('last_seen_at')->dateTime(),
            ]),
            Section::make('Stack trace')->collapsed()->schema([
                TextEntry::make('trace')->label('')->columnSpanFull()
                    ->formatStateUsing(fn (?string $state) => $state)
                    ->extraAttributes(['style' => 'white-space:pre-wrap;font-family:ui-monospace,monospace;font-size:.75rem;line-height:1.5']),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListErrorLogs::route('/'),
        ];
    }
}
