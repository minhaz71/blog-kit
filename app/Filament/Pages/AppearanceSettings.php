<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Storefront theme controls: brand colors, corner radius, card style.
 *
 * @property-read Schema $form
 */
class AppearanceSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Appearance';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'appearance';
    }

    protected function keys(): array
    {
        return [
            'favicon',
            'primary_color', 'primary_hover_color', 'sale_badge_color',
            'border_radius', 'card_shadow', 'card_add_to_cart',
            'announcement_text', 'announcement_url',
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
            Section::make('Favicon')
                ->description('Shown in browser tabs and bookmarks. Square image, ideally 512×512 PNG or .ico — Filament auto-generates the smaller sizes browsers request.')
                ->schema([
                    FileUpload::make('favicon')
                        ->image()
                        ->disk('public')
                        ->directory('branding')
                        ->helperText('Leave empty to use the default favicon.'),
                ]),
            Section::make('Announcement bar')
                ->description('Slim bar above the header on every storefront page. Leave the text empty to hide it.')
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\TextInput::make('announcement_text')
                        ->label('Text')
                        ->placeholder('⚡ 1-hour delivery in Dubai, Sharjah & Ajman'),
                    \Filament\Forms\Components\TextInput::make('announcement_url')
                        ->label('Link (optional)')
                        ->placeholder('/shop'),
                ]),
            Section::make('Brand colors')
                ->description('Applied across buttons, links, and highlights on the storefront.')
                ->columns(3)
                ->schema([
                    ColorPicker::make('primary_color')
                        ->label('Primary color')
                        ->helperText('Default: indigo (#4f46e5)'),
                    ColorPicker::make('primary_hover_color')
                        ->label('Primary hover')
                        ->helperText('Slightly darker shade of primary.'),
                    ColorPicker::make('sale_badge_color')
                        ->label('Sale badge')
                        ->helperText('Default: red (#dc2626)'),
                ]),
            Section::make('Layout style')
                ->columns(3)
                ->schema([
                    Select::make('border_radius')
                        ->label('Corner roundness')
                        ->options([
                            'none' => 'Square corners',
                            'sm' => 'Slightly rounded',
                            'md' => 'Rounded (default)',
                            'lg' => 'Extra rounded',
                        ])
                        ->native(false),
                    Toggle::make('card_shadow')
                        ->label('Product card hover shadow')
                        ->default(true)
                        ->inline(false),
                    Toggle::make('card_add_to_cart')
                        ->label('Add-to-cart on product cards')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Quantity stepper + add button on category/shop grids.'),
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

        Notification::make()->title('Appearance saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
