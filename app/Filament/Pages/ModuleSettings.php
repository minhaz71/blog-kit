<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Enable / disable optional modules.
 *
 * Hemdox Blog Kit is blog-first: the full ecommerce store (catalog, cart,
 * checkout, payments, shipping, tax, the AI product writer, product
 * templates) is retained in the codebase but ships DISABLED. Flipping the
 * toggle here persists "modules.ecommerce_enabled"; every navigation guard
 * and route gate reads it via module_enabled('ecommerce'), so the store
 * reappears (or disappears) on the next page load — no data is lost either
 * way. config/blogkit.php + BLOGKIT_ECOMMERCE_ENABLED is the fallback.
 *
 * @property-read Schema $form
 */
class ModuleSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'Modules';

    protected static ?string $navigationLabel = 'Modules';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'modules';
    }

    /** Concrete keys for this settings page. */
    protected function keys(): array
    {
        return ['ecommerce_enabled', 'network_enabled'];
    }

    public function mount(): void
    {
        // Per-module effective default (config/.env fallback) so each toggle
        // reflects the real state even before it has ever been saved.
        $defaults = [
            'ecommerce_enabled' => ecommerce_enabled(),
            'network_enabled' => network_enabled(),
        ];

        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = Setting::get("modules.{$key}", $defaults[$key] ?? false);
        }
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ecommerce (store)')
                    ->description('The complete online store — product catalog, cart, checkout, payments, shipping, tax, reviews, wishlist, the AI product writer and product-template builder. Off by default in Hemdox Blog Kit. Turning it on restores the store menus and storefront pages instantly; turning it off hides them again. No products or orders are ever deleted by this switch.')
                    ->schema([
                        Toggle::make('ecommerce_enabled')
                            ->label('Enable ecommerce store')
                            ->helperText('Leave off for a pure blog site. Enable to run a full store alongside (or instead of) the blog.'),
                    ]),
                Section::make('Multisite network')
                    ->description('Connect several Hemdox Blog Kit installs into one network so a single dashboard can publish to, and view posts from, all of them. After enabling, set this site\'s role (Hub or Spoke) under Network → Network settings.')
                    ->schema([
                        Toggle::make('network_enabled')
                            ->label('Enable multisite network')
                            ->helperText('Off = a normal standalone site. On = this install can act as a hub and/or a spoke.'),
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
            Setting::set("{$group}.{$key}", (bool) $value);
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

        Notification::make()->title('Module settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->action('save')->color('primary'),
        ];
    }
}
