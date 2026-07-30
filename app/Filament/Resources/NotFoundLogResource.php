<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotFoundLogResource\Pages\ListNotFoundLogs;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class NotFoundLogResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = NotFoundLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 31;

    protected static ?string $label = '404 monitor';

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
                TextColumn::make('url')->searchable()->limit(60),
                TextColumn::make('hits')->sortable(),
                TextColumn::make('referrer')->toggleable()->limit(40),
                TextColumn::make('ip_address')->label('IP')->toggleable(),
                TextColumn::make('user_agent')->limit(30)->toggleable(),
                TextColumn::make('last_hit_at')->dateTime()->sortable()->label('Last hit'),
            ])
            ->recordActions([
                Action::make('createRedirect')
                    ->label('Redirect')
                    ->icon(Heroicon::OutlinedArrowUturnRight)
                    ->schema([
                        TextInput::make('target')->required()->label('Redirect to'),
                        Select::make('status_code')->options([301 => '301', 302 => '302'])->default(301)->required()->native(false),
                    ])
                    ->action(function (NotFoundLog $record, array $data): void {
                        Redirect::updateOrCreate(
                            ['source' => $record->url],
                            ['target' => $data['target'], 'status_code' => (int) $data['status_code'], 'is_active' => true],
                        );
                    }),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('last_hit_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotFoundLogs::route('/'),
        ];
    }
}
