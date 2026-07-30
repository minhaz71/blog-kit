<?php

namespace App\Filament\Pages;

use App\Support\SettingsCatalog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * "Find a setting" — a search box over every admin screen and the notable
 * settings inside the settings pages. Type what you're looking for
 * ("noindex", "currency", "under construction", "permalink") and jump
 * straight to the page it lives on. Ungrouped + sorted first so it sits at the
 * very top of the sidebar. Visible to every staff member; results are already
 * filtered to what each user may access.
 */
class SettingsFinder extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = -100;

    protected static ?string $title = 'Find a setting';

    protected static ?string $navigationLabel = 'Find a setting';

    protected string $view = 'filament.pages.settings-finder';

    /** Bound to the search box (live). */
    public string $q = '';

    /** Popular starting points shown when the box is empty. */
    public const SUGGESTIONS = [
        'maintenance', 'permalink', 'noindex', 'currency', 'analytics',
        'payment', 'shipping', 'AI', 'email', 'backup',
    ];

    /** Heroicon per nav group (matches the sidebar group icons). */
    public const GROUP_ICONS = [
        'Catalog' => 'heroicon-o-shopping-bag',
        'Sales' => 'heroicon-o-banknotes',
        'Customers' => 'heroicon-o-users',
        'Marketing' => 'heroicon-o-megaphone',
        'Content' => 'heroicon-o-document-text',
        'SEO' => 'heroicon-o-chart-bar-square',
        'Security' => 'heroicon-o-shield-check',
        'System' => 'heroicon-o-cog-6-tooth',
    ];

    public static function groupIcon(string $group): string
    {
        return self::GROUP_ICONS[$group] ?? 'heroicon-o-square-3-stack-3d';
    }

    /** @return list<array<string,mixed>> */
    public function getResultsProperty(): array
    {
        return SettingsCatalog::search($this->q);
    }
}
