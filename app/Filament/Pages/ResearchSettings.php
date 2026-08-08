<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Keyword-research data source: which provider to use and the DataForSEO
 * credentials. Stored in the `research` settings group, read by the research
 * driver chain.
 *
 * @property-read Schema $form
 */
class ResearchSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Research settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'research';
    }

    protected function keys(): array
    {
        return ['provider', 'dataforseo_login', 'dataforseo_password', 'location_code', 'language'];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        $data['provider'] ??= 'auto';
        $data['location_code'] ??= 2840;
        $data['language'] ??= 'en';

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Keyword data source')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->iconColor('primary')
                ->description(new HtmlString('How <strong>Keyword Research</strong> gets real data. <strong>Auto</strong> uses DataForSEO when credentials are set, falls back to free Google Autocomplete, then the LLM. Free Google needs no key but has no volume numbers.'))
                ->columns(2)
                ->schema([
                    Select::make('provider')
                        ->options([
                            'auto' => 'Auto (DataForSEO → Google → LLM)',
                            'dataforseo' => 'DataForSEO only',
                            'google' => 'Free Google Autocomplete only',
                            'llm' => 'LLM only (no real data)',
                        ])
                        ->default('auto')->native(false)->columnSpanFull(),
                    TextInput::make('dataforseo_login')
                        ->label('DataForSEO login (email)')
                        ->helperText('From your DataForSEO dashboard. Leave blank to use the free path.'),
                    TextInput::make('dataforseo_password')
                        ->label('DataForSEO password')
                        ->password()->revealable()
                        ->helperText('Stored in settings; used for HTTP Basic auth.'),
                    TextInput::make('location_code')
                        ->label('DataForSEO location code')
                        ->numeric()->default(2840)
                        ->helperText('e.g. 2840 = US, 2826 = UK, 2784 = UAE, 2356 = India.'),
                    TextInput::make('language')
                        ->label('Language code')->default('en')
                        ->helperText('e.g. en, ar, hi.'),
                ]),
        ]);
    }

    public function save(): void
    {
        $group = $this->group();
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set("{$group}.{$key}", $value);
        }
        Cache::forget("settings.{$group}");

        // Never log the raw password.
        $safe = $data;
        $safe['dataforseo_password'] = filled($data['dataforseo_password'] ?? null) ? '••••••' : '';

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => "settings:{$group}",
            'old_values' => null,
            'new_values' => $safe,
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('Research settings saved')->success()->send();
    }
}
