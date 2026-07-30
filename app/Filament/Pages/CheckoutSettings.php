<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Checkout editor — full control over the storefront checkout form fields
 * (enable/disable, required/optional, custom labels) and its messaging.
 * Reads/writes the "checkout" settings group; the storefront form and the
 * PlaceOrderRequest both resolve from App\Support\CheckoutFields, so what you
 * toggle here is exactly what the customer sees AND what the server enforces.
 *
 * @property-read Schema $form
 */
class CheckoutSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 15;

    protected static ?string $title = 'Checkout settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'checkout';
    }

    protected function keys(): array
    {
        return [
            // Field toggles + required flags + label overrides
            'last_name_enabled', 'last_name_required', 'last_name_label',
            'company_enabled', 'company_label',
            'address_2_enabled', 'address_2_label',
            'city_required', 'city_label',
            'state_enabled', 'state_required', 'state_label',
            'postal_code_enabled', 'postal_code_required', 'postal_code_label',
            'phone_enabled', 'phone_required', 'phone_label',
            'first_name_label', 'address_1_label', 'country_label',
            // Order note + page messaging
            'note_enabled', 'note_label',
            'browser_title', 'heading', 'subheading', 'notice', 'security_text',
        ];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        // Sensible defaults on first load so toggles reflect current behaviour.
        $data['last_name_enabled'] ??= true;
        $data['last_name_required'] ??= true;
        $data['address_2_enabled'] ??= true;
        $data['city_required'] ??= true;
        $data['state_enabled'] ??= true;
        $data['state_required'] ??= false;
        $data['postal_code_enabled'] ??= true;
        $data['postal_code_required'] ??= false;
        $data['phone_enabled'] ??= true;
        $data['phone_required'] ??= false;
        $data['note_enabled'] ??= true;

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Address fields')
                    ->description('Turn fields on/off and choose which are required. First name, address, city and country are always on — an order needs them. City, state and postal code can each be relabelled to suit your region (e.g. "Emirate", "Postcode").')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('last_name_enabled')->label('Show last name')->live(),
                            Toggle::make('last_name_required')->label('Last name required')
                                ->visible(fn ($get) => $get('last_name_enabled')),
                            TextInput::make('last_name_label')->label('Last name label')->placeholder('Last name'),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('company_enabled')->label('Show company')->live(),
                            TextInput::make('company_label')->label('Company label')->placeholder('Company')
                                ->visible(fn ($get) => $get('company_enabled'))->columnSpan(2),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('address_2_enabled')->label('Show address line 2')->live(),
                            TextInput::make('address_2_label')->label('Address line 2 label')->placeholder('Apartment, suite')
                                ->visible(fn ($get) => $get('address_2_enabled'))->columnSpan(2),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('city_required')->label('City required')->default(true),
                            TextInput::make('city_label')->label('City label')->placeholder('City')->columnSpan(2),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('state_enabled')->label('Show state / region')->live(),
                            Toggle::make('state_required')->label('State required')
                                ->visible(fn ($get) => $get('state_enabled')),
                            TextInput::make('state_label')->label('State label')->placeholder('State / region')
                                ->helperText('e.g. "Emirate", "Province"'),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('postal_code_enabled')->label('Show postal code')->live(),
                            Toggle::make('postal_code_required')->label('Postal code required')
                                ->visible(fn ($get) => $get('postal_code_enabled')),
                            TextInput::make('postal_code_label')->label('Postal code label')->placeholder('Postal code')
                                ->helperText('e.g. "ZIP", "Postcode"'),
                        ]),
                    ]),
                Section::make('Contact & order note')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('phone_enabled')->label('Show phone')->live(),
                            Toggle::make('phone_required')->label('Phone required')
                                ->visible(fn ($get) => $get('phone_enabled')),
                            TextInput::make('phone_label')->label('Phone label')->placeholder('Phone'),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('note_enabled')->label('Show order note')->live(),
                            TextInput::make('note_label')->label('Order note label')->placeholder('Order note')
                                ->visible(fn ($get) => $get('note_enabled'))->columnSpan(2),
                        ]),
                    ]),
                Section::make('Messaging')
                    ->description('Customise the wording customers see on the checkout page. Leave blank to use the defaults.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('browser_title')->label('Browser tab title')->placeholder('Checkout')
                                ->helperText('Shown in the browser tab and bookmarks (the <title>). Defaults to “Checkout”.'),
                            TextInput::make('heading')->label('Page heading')->placeholder('Checkout'),
                            TextInput::make('subheading')->label('Sub-heading')->placeholder('Complete your order below. It only takes a minute.'),
                        ]),
                        Textarea::make('notice')->label('Notice banner (optional)')->rows(2)
                            ->helperText('Shown at the top of checkout — e.g. delivery times, cut-off, or a promo note. Leave blank to hide.'),
                        TextInput::make('security_text')->label('Security footer text')
                            ->placeholder('Your payment information is encrypted and secure.'),
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

        Notification::make()->title('Checkout settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save')->color('primary'),
        ];
    }
}
