<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
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
 * Header & footer builder: logo, menu items (with dropdowns), footer
 * link columns, and copyright text.
 *
 * @property-read Schema $form
 */
class NavigationSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Header & Footer';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'navigation';
    }

    protected function keys(): array
    {
        return ['logo', 'header_menu', 'footer_columns', 'footer_text', 'show_newsletter',
            'footer_address', 'footer_phone', 'footer_email', 'footer_hours'];
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
            Section::make('Logo')
                ->description('Shown in the header instead of the store name. Leave empty to display the store name as text.')
                ->schema([
                    FileUpload::make('logo')
                        ->image()
                        ->disk('public')
                        ->directory('branding')
                        ->imageEditor()
                        ->helperText('Recommended: transparent PNG or SVG, about 40px tall.'),
                ]),
            Section::make('Header menu')
                ->description('Menu items in order. Leave empty to auto-build the menu from your top categories. Add sub-items to create a dropdown.')
                ->schema([
                    Repeater::make('header_menu')
                        ->hiddenLabel()
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('url')->required()->helperText('E.g. /shop, /category/electronics, /blog, or a full https:// URL.'),
                            ]),
                            Repeater::make('children')
                                ->label('Dropdown items')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('label')->required(),
                                        TextInput::make('url')->required(),
                                    ]),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add dropdown item')
                                ->collapsible(),
                        ])
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->addActionLabel('Add menu item'),
                ]),
            Section::make('Footer columns')
                ->description('Link columns shown in the footer. Leave empty to use the default Shop / Support columns.')
                ->schema([
                    Repeater::make('footer_columns')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('title')->required(),
                            Repeater::make('links')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('label')->required(),
                                        TextInput::make('url')->required(),
                                    ]),
                                ])
                                ->defaultItems(1)
                                ->addActionLabel('Add link')
                                ->collapsible(),
                        ])
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->addActionLabel('Add column')
                        ->maxItems(3),
                ]),
            Section::make('Footer contact block')
                ->description('Shown under the store name in the footer. Leave a field empty to hide that line.')
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\Textarea::make('footer_address')
                        ->label('Address')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder('Shop 12, Marina Walk, Dubai, UAE'),
                    TextInput::make('footer_phone')
                        ->label('Phone')
                        ->tel()
                        ->placeholder('+971 50 123 4567'),
                    TextInput::make('footer_email')
                        ->label('Email')
                        ->email()
                        ->placeholder('hello@yourstore.ae'),
                    \Filament\Forms\Components\Textarea::make('footer_hours')
                        ->label('Opening hours')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder("Mon–Sat: 9am – 11pm\nSun: 10am – 10pm"),
                ]),

            Section::make('Footer options')
                ->columns(2)
                ->schema([
                    TextInput::make('footer_text')
                        ->label('Copyright text')
                        ->helperText('Leave empty for "© year Store name. All rights reserved."'),
                    Toggle::make('show_newsletter')
                        ->label('Show newsletter signup')
                        ->default(true)
                        ->inline(false),
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

        Notification::make()->title('Header & footer saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
