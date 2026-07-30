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
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class PaymentSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Payment gateways';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'payments';
    }

    protected function keys(): array
    {
        return [
            'stripe_enabled', 'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret',
            'paypal_enabled', 'paypal_client_id', 'paypal_client_secret', 'paypal_mode',
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
            Section::make('Stripe')->columns(2)->schema([
                Toggle::make('stripe_enabled')->inline(false),
                TextInput::make('stripe_public_key')->password()->revealable(),
                TextInput::make('stripe_secret_key')->password()->revealable(),
                TextInput::make('stripe_webhook_secret')->password()->revealable()
                    ->helperText('Set your webhook URL in Stripe to /webhooks/stripe and paste the signing secret here.'),
            ]),
            Section::make('PayPal')->columns(2)->schema([
                Toggle::make('paypal_enabled')->inline(false),
                Select::make('paypal_mode')->options(['sandbox' => 'Sandbox', 'live' => 'Live'])->default('sandbox')->native(false),
                TextInput::make('paypal_client_id')->password()->revealable(),
                TextInput::make('paypal_client_secret')->password()->revealable(),
            ]),
            Section::make('Pay-on-delivery & manual methods')
                ->description(new \Illuminate\Support\HtmlString(
                    'Cash on delivery, card on delivery, bank transfer and any other manual method are now managed under '
                    .'<a href="/admin/payment-methods" class="text-primary-600 underline">Sales → Payment methods</a> — '
                    .'where you can rename them, set a checkout message, and add a named surcharge.'
                ))
                ->schema([]),
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
            'old_values' => array_map(fn ($v) => is_string($v) && strlen($v) > 4 ? '***' : $v, $old),
            'new_values' => array_map(fn ($v) => is_string($v) && strlen($v) > 4 ? '***' : $v, $data),
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('Payment settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
