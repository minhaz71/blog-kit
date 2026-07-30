<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class SecuritySettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Security settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'security';
    }

    protected function keys(): array
    {
        return [
            'firewall_enabled', 'max_login_attempts', 'lockout_minutes',
            'block_common_usernames',
            'two_factor_enabled', 'recaptcha_enabled',
            'recaptcha_site_key', 'recaptcha_secret_key',
            'threat_intel_enabled', 'blocked_countries', 'allowed_countries',
            'alerts_enabled', 'alert_emails',
        ];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Firewall')->schema([
                Toggle::make('firewall_enabled')->default(true),
            ]),
            Section::make('Login protection')
                ->description('Customer passwords are a simple 6-character minimum (owner rule — shoppers, not staff). Staff accounts always require 10+ characters, set in Staff users.')
                ->columns(2)->schema([
                TextInput::make('max_login_attempts')->numeric()->default(5),
                TextInput::make('lockout_minutes')->numeric()->default(15)->suffix('min'),
                Toggle::make('block_common_usernames')->default(true)->inline(false),
                Toggle::make('two_factor_enabled')->inline(false),
            ]),
            Section::make('reCAPTCHA')->columns(2)->schema([
                Toggle::make('recaptcha_enabled')->columnSpanFull()->inline(false),
                TextInput::make('recaptcha_site_key'),
                TextInput::make('recaptcha_secret_key')->password()->revealable(),
            ]),
            Section::make('Threat intelligence & geo-blocking')
                ->description('Block known threat-actor IPs from public feeds (updated daily) and filter traffic by country.')
                ->columns(2)
                ->schema([
                    Toggle::make('threat_intel_enabled')
                        ->label('Block IPs on the real-time threat blocklist')
                        ->default(true)->inline(false)->columnSpanFull(),
                    \Filament\Forms\Components\TagsInput::make('blocked_countries')
                        ->label('Blocked countries')
                        ->placeholder('e.g. RU, CN, KP')
                        ->helperText('Two-letter ISO codes. Requests from these countries are denied.'),
                    \Filament\Forms\Components\TagsInput::make('allowed_countries')
                        ->label('Allowed countries (allow-list)')
                        ->placeholder('e.g. AE, SA, US')
                        ->helperText('If set, ONLY these countries may access the site (overrides the blocked list).'),
                ]),
            Section::make('Intrusion alerts')
                ->description('Email a security contact the moment a high-severity event happens (auto-bans, malware, threat-IP hits).')
                ->schema([
                    Toggle::make('alerts_enabled')->label('Send intrusion-alert emails')->default(true)->inline(false),
                    TextInput::make('alert_emails')
                        ->label('Alert recipients')
                        ->placeholder('security@yourstore.com, ops@yourstore.com')
                        ->helperText('Comma-separated. Leave blank to notify all Super Admins.'),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $group = $this->group();
        $data = $this->form->getState();

        $old = [];
        foreach ($this->keys() as $key) {
            $old[$key] = Setting::get("{$group}.{$key}");
        }
        foreach ($data as $key => $value) {
            Setting::set("{$group}.{$key}", $value);
        }
        Cache::forget("settings.{$group}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => "settings:{$group}",
            'old_values' => $old,
            'new_values' => $data,
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('Security settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
