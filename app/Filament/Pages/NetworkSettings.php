<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Network\NetworkIdentity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * This install's own network settings: its ROLE in the network and the
 * credentials a hub uses to address it as a spoke. Visible whenever the
 * network module is on (a site can be a spoke, a hub, or both).
 *
 * @property-read Schema $form
 */
class NetworkSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Network settings';

    protected static ?string $navigationLabel = 'Network settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return network_enabled() && \App\Support\AdminAccess::allows(static::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return network_enabled() && \App\Support\AdminAccess::allows(static::class);
    }

    public function mount(): void
    {
        [$key, $secret] = NetworkIdentity::ensure();

        $this->form->fill([
            'role' => setting('network.role', config('blogkit.network.role', 'standalone')),
            'api_key' => $key,
            'api_secret' => $secret,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role in the network')
                    ->description('Hub = this site manages others (shows "Connected sites" + network publishing). Spoke = this site accepts content from a hub. A site can be both.')
                    ->schema([
                        Select::make('role')
                            ->label('This site is a')
                            ->options([
                                'standalone' => 'Standalone (not networked)',
                                'hub' => 'Hub (control panel)',
                                'spoke' => 'Spoke (managed by a hub)',
                            ])
                            ->default('standalone')
                            ->native(false)
                            ->required(),
                    ]),
                Section::make('This site\'s credentials')
                    ->description('Paste BOTH values into your hub\'s "Add site" form so it can publish here. The secret is shown so you can copy it; keep it private. Regenerating invalidates the old pair.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('api_key')->label('API key')->disabled()->dehydrated(false),
                        TextInput::make('api_secret')->label('API secret')->password()->revealable()->disabled()->dehydrated(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $old = setting('network.role');

        Setting::set('network.role', $data['role'] ?? 'standalone');
        Cache::forget('settings.network');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => 'settings:network',
            'old_values' => ['role' => $old],
            'new_values' => ['role' => $data['role'] ?? 'standalone'],
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('Network settings saved')->success()->send();
    }

    public function regenerate(): void
    {
        [$key, $secret] = NetworkIdentity::regenerate();
        Cache::forget('settings.network');

        $this->form->fill([
            'role' => setting('network.role', 'standalone'),
            'api_key' => $key,
            'api_secret' => $secret,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'network_credentials_rotated',
            'subject' => 'settings:network',
            'ip_address' => request()->ip(),
        ]);

        Notification::make()
            ->title('Credentials regenerated')
            ->body('Update every hub that connects to this site with the new key and secret.')
            ->warning()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save')->color('primary'),
            Action::make('regenerate')->label('Regenerate credentials')->action('regenerate')
                ->color('danger')->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('This invalidates the current key and secret. Every hub connected to this site must be updated.'),
        ];
    }
}
