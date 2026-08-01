<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConnectedSiteResource\Pages\CreateConnectedSite;
use App\Filament\Resources\ConnectedSiteResource\Pages\EditConnectedSite;
use App\Filament\Resources\ConnectedSiteResource\Pages\ListConnectedSites;
use App\Models\ConnectedSite;
use App\Services\Network\NetworkClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hub-side registry of the spoke installs this control panel manages. Visible
 * only when the network module is on AND this install's role is 'hub'.
 */
class ConnectedSiteResource extends Resource
{
    protected static ?string $model = ConnectedSite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Connected sites';

    /** Hub-only + module-gated + permission-gated. */
    public static function canAccess(): bool
    {
        return network_enabled() && is_network_hub() && \App\Support\AdminAccess::allows(static::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Site')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)
                        ->helperText('A label for this site in your dashboard.'),
                    TextInput::make('base_url')->label('Base URL')->required()->url()
                        ->helperText('The site root, e.g. https://site2.example.com')
                        ->columnSpanFull(),
                ]),
            Section::make('Credentials')
                ->description("Paste the API key and secret shown on that site's Network settings page.")
                ->columns(2)
                ->schema([
                    TextInput::make('api_key')->label('API key')->required()->maxLength(255),
                    TextInput::make('api_secret')->label('API secret')
                        ->password()->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText('Leave blank when editing to keep the current secret.'),
                ]),
            Section::make('Status')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')->default(true)
                        ->helperText('Inactive sites are skipped by network publishing and sync.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Site ID')->sortable()->badge()->color('gray'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('base_url')->label('URL')->limit(40)->url(fn (ConnectedSite $r) => $r->base_url, true),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'online' => 'success',
                    'error' => 'danger',
                    'offline' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('remote_version')->label('Version')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('last_seen_at')->label('Last seen')->since()->placeholder('never'),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([
                self::testAction(),
                self::updateAction(),
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()]),
            ])
            ->defaultSort('id');
    }

    /** Ping the spoke and persist the health result. */
    public static function testAction(): Action
    {
        return Action::make('test')
            ->label('Test')
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->action(function (ConnectedSite $record): void {
                [$ok, $message] = (new NetworkClient)->refreshHealth($record);

                Notification::make()
                    ->title($ok ? 'Connection OK' : 'Connection failed')
                    ->body($message)
                    ->{$ok ? 'success' : 'danger'}()
                    ->send();
            });
    }

    /** Trigger one spoke's self-update (backup → pull → migrate) via the API. */
    public static function updateAction(): Action
    {
        return Action::make('update')
            ->label('Update')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Update this site')
            ->modalDescription('Trigger blogkit:update on this site (it takes a backup first, then pulls and migrates). The site stays online during the update.')
            ->action(function (ConnectedSite $record): void {
                \App\Jobs\UpdateSite::dispatch($record->id);

                Notification::make()
                    ->title('Update triggered')
                    ->body("{$record->name} is backing up, then updating in the background.")
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnectedSites::route('/'),
            'create' => CreateConnectedSite::route('/create'),
            'edit' => EditConnectedSite::route('/{record}/edit'),
        ];
    }
}
