<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RedirectResource\Pages\CreateRedirect;
use App\Filament\Resources\RedirectResource\Pages\EditRedirect;
use App\Filament\Resources\RedirectResource\Pages\ListRedirects;
use App\Models\Redirect;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('source')->required()->helperText('Path, e.g. /old-product-slug. May be a regex if "Is regex" is on.')->columnSpanFull(),
                TextInput::make('target')->required()->helperText('Destination path or full URL.')->columnSpanFull(),
                Select::make('status_code')
                    ->options([301 => '301 Permanent', 302 => '302 Temporary', 307 => '307 Temporary', 410 => '410 Gone'])
                    ->default(301)
                    ->required()
                    ->native(false),
                TextInput::make('hits')->numeric()->disabled()->dehydrated(false),
                Toggle::make('is_regex')->helperText('Treat source as a regex pattern.'),
                Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')->searchable()->limit(50),
                TextColumn::make('target')->searchable()->limit(50),
                TextColumn::make('status_code')->badge(),
                IconColumn::make('is_regex')->boolean()->label('Regex'),
                TextColumn::make('hits')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status_code')->options([301 => '301', 302 => '302', 307 => '307', 410 => '410']),
            ])
            ->recordActions([\Filament\Actions\EditAction::make()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
