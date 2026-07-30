<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class EmailSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 22;

    protected static ?string $title = 'Email settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'emails';
    }

    protected function keys(): array
    {
        return [
            'mailer',
            'from_name', 'from_email', 'admin_recipient',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
            'gmail_client_id', 'gmail_client_secret',
            // Premium customer email extras
            'email_show_tracker', 'email_show_invoice_button', 'email_show_related',
            'email_show_promo', 'promo_heading', 'promo_text', 'promo_cta',
            // Email branding
            'email_logo', 'email_header_color', 'email_header_text_color', 'email_footer_text',
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

        // Flash results from the /hmmail OAuth round-trip.
        if ($message = session('hmmail_success')) {
            Notification::make()->title($message)->success()->send();
        }

        if ($message = session('hmmail_error')) {
            Notification::make()->title('Gmail connection failed')->body($message)->danger()->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mailer')
                ->description('How the store sends order and customer email.')
                ->schema([
                    Select::make('mailer')
                        ->label('Send email via')
                        ->options([
                            'log' => 'Log only (no real email — testing)',
                            'smtp' => 'SMTP server',
                            'gmail' => 'Gmail (1-click OAuth connection)',
                        ])
                        ->default('log')
                        ->native(false)
                        ->live(),
                ]),
            Section::make('Sender identity')->columns(2)->schema([
                TextInput::make('from_name')->required(),
                TextInput::make('from_email')->email()->required()
                    ->helperText('With Gmail, Google may rewrite this to the connected account address.'),
                TextInput::make('admin_recipient')
                    ->label('Admin notification recipients')
                    ->helperText('Where new-order and stock alerts go. Add several addresses separated by commas (up to 20) — every address gets a copy of each new-order email.')
                    ->placeholder('sales@store.com, owner@store.com, warehouse@store.com')
                    ->rule(static function (): \Closure {
                        return static function (string $attribute, $value, \Closure $fail): void {
                            $parts = preg_split('/[,;\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            if (count($parts) > 20) {
                                $fail('Please enter at most 20 recipient addresses.');
                            }
                            foreach ($parts as $addr) {
                                if (! filter_var(trim($addr), FILTER_VALIDATE_EMAIL)) {
                                    $fail("\"{$addr}\" is not a valid email address.");
                                }
                            }
                        };
                    }),
            ]),
            Section::make('Gmail connection')
                ->description('Like WP Mail SMTP: create an OAuth Client ID in Google Cloud Console (APIs & Services → Credentials → OAuth client ID → Web application), enable the Gmail API, paste the credentials here, save, then click "Connect Gmail" above.')
                ->columns(2)
                ->visible(fn ($get) => $get('mailer') === 'gmail')
                ->schema([
                    TextInput::make('gmail_client_id')
                        ->label('Client ID')
                        ->placeholder('xxxxxxxx.apps.googleusercontent.com'),
                    TextInput::make('gmail_client_secret')
                        ->label('Client secret')
                        ->password()
                        ->revealable(),
                    \Filament\Forms\Components\Placeholder::make('redirect_uri')
                        ->label('Authorized redirect URI (paste into Google Cloud Console)')
                        ->content(fn () => \App\Services\Mail\GmailOAuth::redirectUri())
                        ->columnSpanFull(),
                    \Filament\Forms\Components\Placeholder::make('gmail_status')
                        ->label('Status')
                        ->content(fn () => \App\Services\Mail\GmailOAuth::connected()
                            ? '✅ Connected as '.\App\Services\Mail\GmailOAuth::connectedEmail()
                            : '⚪ Not connected — save your Client ID/Secret, then click "Connect Gmail" in the header.')
                        ->columnSpanFull(),
                ]),
            Section::make('Email branding')
                ->description('Control the look of every order email. Leave a field blank to fall back to your site logo / brand colour. Edit the wording of each email under Email templates (with a live Preview).')
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\FileUpload::make('email_logo')
                        ->label('Email header logo')
                        ->image()
                        ->disk('public')
                        ->directory('branding')
                        ->imageEditor()
                        ->helperText('Shown at the top of every email. Leave empty to use your site logo. PNG/JPG works best in email clients.')
                        ->columnSpanFull(),
                    \Filament\Forms\Components\ColorPicker::make('email_header_color')
                        ->label('Header background colour')
                        ->helperText('Defaults to your brand colour.'),
                    \Filament\Forms\Components\ColorPicker::make('email_header_text_color')
                        ->label('Header text colour')
                        ->helperText('Used for the store name when no logo is set (default white).'),
                    \Filament\Forms\Components\Textarea::make('email_footer_text')
                        ->label('Footer text')
                        ->rows(2)
                        ->placeholder('e.g. Genuine IQOS TEREA — delivered fast across the UAE.')
                        ->helperText('A short line shown in the email footer above your address.')
                        ->columnSpanFull(),
                ]),
            Section::make('Customer order email')
                ->description('Toggle the extra sections of the premium order email below.')
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\Toggle::make('email_show_tracker')
                        ->label('Show order progress tracker')->default(true),
                    \Filament\Forms\Components\Toggle::make('email_show_invoice_button')
                        ->label('Show "Download PDF invoice" button')->default(true),
                    \Filament\Forms\Components\Toggle::make('email_show_related')
                        ->label('Show "You may also like" products')->default(true),
                    \Filament\Forms\Components\Toggle::make('email_show_promo')
                        ->label('Show promo banner')->default(true),
                    TextInput::make('promo_heading')->label('Promo heading')
                        ->placeholder('Free delivery on your next order')->columnSpanFull(),
                    TextInput::make('promo_text')->label('Promo text')
                        ->placeholder('Come back soon — genuine IQOS TEREA, delivered fast across the UAE.')->columnSpanFull(),
                    TextInput::make('promo_cta')->label('Promo button label')->placeholder('Shop Now'),
                ]),
            Section::make('SMTP')
                ->columns(2)
                ->visible(fn ($get) => $get('mailer') !== 'gmail')
                ->schema([
                    TextInput::make('smtp_host'),
                    TextInput::make('smtp_port')->numeric()->default(587),
                    TextInput::make('smtp_username'),
                    TextInput::make('smtp_password')->password()->revealable(),
                    Select::make('smtp_encryption')->options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'])->default('tls')->native(false),
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

        Notification::make()->title('Email settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connectGmail')
                ->label(fn () => \App\Services\Mail\GmailOAuth::connected() ? 'Reconnect Gmail' : 'Connect Gmail')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('success')
                ->visible(fn () => \App\Services\Mail\GmailOAuth::configured())
                ->url(route('hmmail.connect')),
            Action::make('disconnectGmail')
                ->label('Disconnect')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn () => \App\Services\Mail\GmailOAuth::connected())
                ->requiresConfirmation()
                ->action(function () {
                    \App\Services\Mail\GmailOAuth::disconnect();
                    Notification::make()->title('Gmail disconnected.')->success()->send();
                }),
            Action::make('testEmail')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->action(function () {
                    // admin_recipient may be a comma-separated list — send to
                    // every valid address (falls back to sender / current user).
                    $to = \App\Services\Email\EmailService::normalizeRecipients(
                        (string) (setting('emails.admin_recipient') ?: setting('emails.from_email') ?: auth()->user()->email)
                    );

                    if ($to === []) {
                        Notification::make()->title('No valid recipient address configured.')->warning()->send();

                        return;
                    }

                    try {
                        \Illuminate\Support\Facades\Mail::raw(
                            'This is a test email from '.config('app.name').' — your mail configuration works. Sent via the "'.config('mail.default').'" mailer at '.now()->toDateTimeString().'.',
                            fn ($message) => $message->to($to)->subject('✅ Test email — '.config('app.name')),
                        );

                        Notification::make()
                            ->title('Test email sent to '.implode(', ', $to).' via "'.config('mail.default').'"')
                            ->body(config('mail.default') === 'log' ? 'Mailer is "Log only" — check storage/logs/laravel.log.' : null)
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Test email failed')
                            ->body(mb_substr($e->getMessage(), 0, 300))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
