<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Support\AbandonedCartFlow;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class AbandonedCartSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Abandoned cart settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'abandoned';
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());

        $this->form->fill([
            'enabled' => $values['enabled'] ?? true,
            'stages' => (is_array($values['stages'] ?? null) && $values['stages'] !== [])
                ? $values['stages']
                : AbandonedCartFlow::DEFAULT_STAGES,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $templates = EmailTemplate::orderBy('name')->pluck('name', 'key')->all();

        return $schema->components([
            Section::make('Abandoned cart recovery')
                ->description('Automatically email shoppers (guests included, once they enter their email at checkout) who leave items in their cart. Each stage fires the chosen email once, measured from when the cart was abandoned. After the last stage the cart exits the flow.')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Enable abandoned cart emails')
                        ->default(true),
                    Repeater::make('stages')
                        ->label('Reminder sequence')
                        ->addActionLabel('Add a stage')
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->cloneable()
                        ->columns(4)
                        ->default(AbandonedCartFlow::DEFAULT_STAGES)
                        ->schema([
                            Toggle::make('enabled')->label('On')->default(true)->inline(false),
                            TextInput::make('delay')->label('Send after')->numeric()->minValue(1)->required()->default(1),
                            Select::make('unit')->label('Unit')->options(AbandonedCartFlow::unitOptions())->default('days')->native(false)->required(),
                            Select::make('template')->label('Email template')->options($templates)->default('abandoned_cart')->native(false)->required(),
                        ])
                        ->itemLabel(fn (array $state): ?string => isset($state['delay'], $state['unit'])
                            ? 'After '.$state['delay'].' '.$state['unit']
                            : null),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $group = $this->group();
        $data = $this->form->getState();

        $old = Setting::group($group);

        Setting::set("{$group}.enabled", (bool) ($data['enabled'] ?? true));
        Setting::set("{$group}.stages", array_values($data['stages'] ?? []));
        Cache::forget("settings.{$group}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => "settings:{$group}",
            'old_values' => $old,
            'new_values' => $data,
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('Abandoned cart settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
