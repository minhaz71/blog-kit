<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Floating WhatsApp chat button shown on every storefront page.
 *
 * @property-read Schema $form
 */
class WhatsAppSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'WhatsApp button';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'whatsapp';
    }

    protected function keys(): array
    {
        return ['enabled', 'number', 'position', 'delay_seconds', 'greeting', 'message'];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        // Runtime defaults so the form reflects the live behavior when
        // nothing was saved yet.
        $data['position'] ??= 'left';
        $data['delay_seconds'] ??= 3;
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('WhatsApp chat button')
                ->description('A floating WhatsApp button on every storefront page. Clicking it opens a WhatsApp chat to your number. Hidden in the admin panel.')
                ->columns(2)
                ->schema([
                    Toggle::make('enabled')
                        ->label('Show the button')
                        ->default(false)
                        ->inline(false)
                        ->columnSpanFull(),
                    TextInput::make('number')
                        ->label('WhatsApp number')
                        ->tel()
                        ->placeholder('+971 50 123 4567')
                        ->helperText('Include the country code. Spaces, dashes and the + are fine — they are stripped automatically.')
                        ->required(fn (callable $get) => (bool) $get('enabled')),
                    Select::make('position')
                        ->label('Screen position')
                        ->options(['left' => 'Bottom left (default)', 'right' => 'Bottom right'])
                        ->default('left')
                        ->native(false),
                    TextInput::make('delay_seconds')
                        ->label('Appear after (seconds)')
                        ->numeric()
                        ->default(3)
                        ->minValue(0)
                        ->maxValue(60)
                        ->helperText('The button slides in this many seconds after the page loads. 0 shows it immediately.'),
                    TextInput::make('greeting')
                        ->label('Hover label (optional)')
                        ->placeholder('Chat with us')
                        ->helperText('Small tooltip shown next to the button. Leave empty for icon only.'),
                    Textarea::make('message')
                        ->label('Pre-filled message (optional)')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder('Hi! I have a question about my order.')
                        ->helperText('Text that is ready to send when the chat opens. The customer can edit it before sending.'),
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

        Notification::make()->title('WhatsApp button settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save')->color('primary'),
        ];
    }
}
