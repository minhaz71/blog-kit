<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Storefront search controls: the live AJAX dropdown on/off and its tuning.
 *
 * @property-read Schema $form
 */
class SearchSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Search';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'search';
    }

    protected function keys(): array
    {
        return ['ajax_enabled', 'min_chars', 'max_results'];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        $data['ajax_enabled'] ??= true;
        $data['min_chars'] ??= 2;
        $data['max_results'] ??= 8;
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Live search')
                ->description('The AJAX dropdown shows matching products as the customer types in the header search bar. The JavaScript loads after the page finishes, so it never slows the site down. Turn it off to fall back to the plain search-results page.')
                ->columns(3)
                ->schema([
                    Toggle::make('ajax_enabled')
                        ->label('Live search dropdown')
                        ->default(true)
                        ->inline(false)
                        ->columnSpanFull(),
                    TextInput::make('min_chars')
                        ->label('Start after (characters)')
                        ->numeric()->default(2)->minValue(1)->maxValue(5)
                        ->helperText('How many letters before results appear.'),
                    TextInput::make('max_results')
                        ->label('Results shown')
                        ->numeric()->default(8)->minValue(1)->maxValue(20)
                        ->helperText('Products listed in the dropdown (the full page shows all).'),
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

        Notification::make()->title('Search settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save')->color('primary'),
        ];
    }
}
