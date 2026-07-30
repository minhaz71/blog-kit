<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class GeneralSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'General settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'general';
    }

    /** Concrete keys for this settings page. */
    protected function keys(): array
    {
        return [
            'site_name', 'site_tagline', 'currency', 'currency_symbol',
            'currency_decimals', 'currency_position',
            'sell_to_mode', 'sell_to_countries',
            'timezone', 'contact_email', 'contact_phone',
            'maintenance_mode',
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
        return $schema
            ->components([
                Section::make('Store identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_name')->required()->maxLength(255),
                        TextInput::make('site_tagline'),
                        Textarea::make('site_tagline')->rows(2)->columnSpanFull()->hidden(),
                    ]),
                Section::make('Selling locations')
                    ->description('WooCommerce-style: choose where you sell. Checkout only offers these countries — with a single country, customers see it locked and cannot change it.')
                    ->columns(2)
                    ->schema([
                        Select::make('sell_to_mode')
                            ->label('Sell to')
                            ->options([
                                'all' => 'All countries (global)',
                                'specific' => 'Specific countries only',
                            ])
                            ->default('all')
                            ->native(false)
                            ->live()
                            ->placeholder('All countries (global)'),
                        Select::make('sell_to_countries')
                            ->label('Countries')
                            ->options(config('countries.list', []))
                            ->multiple()
                            ->searchable()
                            ->visible(fn ($get) => $get('sell_to_mode') === 'specific')
                            ->helperText('Pick one country (e.g. United Arab Emirates) to lock checkout to it, or several to offer a short list.'),
                    ]),
                Section::make('Locale & currency')
                    ->columns(2)
                    ->schema([
                        Select::make('currency')
                            ->options([
                                'AED' => 'UAE Dirham (AED)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)',
                                'GBP' => 'British Pound (GBP)', 'SAR' => 'Saudi Riyal (SAR)', 'KWD' => 'Kuwaiti Dinar (KWD)',
                                'QAR' => 'Qatari Riyal (QAR)', 'OMR' => 'Omani Rial (OMR)', 'BHD' => 'Bahraini Dinar (BHD)',
                                'INR' => 'Indian Rupee (INR)', 'PKR' => 'Pakistani Rupee (PKR)', 'BDT' => 'Bangladeshi Taka (BDT)',
                                'AUD' => 'Australian Dollar (AUD)', 'CAD' => 'Canadian Dollar (CAD)', 'JPY' => 'Japanese Yen (JPY)',
                                'CNY' => 'Chinese Yuan (CNY)', 'TRY' => 'Turkish Lira (TRY)', 'RUB' => 'Russian Ruble (RUB)',
                            ])
                            ->searchable()
                            ->required(),
                        TextInput::make('currency_symbol')
                            ->label('Currency display text')
                            ->helperText('Exactly what customers see next to prices — write anything: "AED", "د.إ", "$"…'),
                        Select::make('currency_decimals')
                            ->label('Price decimals')
                            ->options([
                                0 => '0 — flat prices (e.g. 30)',
                                1 => '1 (e.g. 30.5)',
                                2 => '2 (e.g. 30.00)',
                                3 => '3 (e.g. 30.000)',
                            ])
                            ->default(2)
                            ->native(false),
                        Select::make('currency_position')
                            ->label('Symbol position')
                            ->options(['left' => 'Before the amount (AED 30)', 'right' => 'After the amount (30 AED)'])
                            ->default('left')
                            ->native(false),
                        TextInput::make('timezone')->default('UTC'),
                    ]),
                Section::make('Support contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_email')->email(),
                        TextInput::make('contact_phone'),
                    ]),
                Section::make('Site status')
                    ->description('Development / "coming soon" mode. While on, visitors who are not signed in see a branded "under construction" page. You and your team (any signed-in user) still see the full site, and the admin panel stays open.')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Maintenance mode (site under construction)')
                            ->helperText('Turn on while building or updating the store. Remember to turn it off to go live.'),
                    ]),
            ])
            ->statePath('data');
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

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save')->color('primary'),
        ];
    }
}
