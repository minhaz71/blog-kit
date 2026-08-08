<?php

namespace App\Providers\Filament;

use App\Models\User;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (): string => setting('general.site_name', 'Hemdox Blog Kit').' Admin')
            // Brand-matched theme: the storefront's deep teal as primary,
            // slate neutrals (cooler alongside teal), semantic colors tuned.
            ->colors([
                'primary' => Color::generateV3Palette('#0f766e'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->sidebarCollapsibleOnDesktop()
            // Grouped by area and ordered by day-to-day importance, each with
            // an icon so the sidebar scans quickly. "Find a setting" sits
            // ungrouped at the very top (navigationSort -100).
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Catalog')->icon('heroicon-o-shopping-bag'),
                \Filament\Navigation\NavigationGroup::make('Sales')->icon('heroicon-o-banknotes'),
                \Filament\Navigation\NavigationGroup::make('Customers')->icon('heroicon-o-users'),
                \Filament\Navigation\NavigationGroup::make('Marketing')->icon('heroicon-o-megaphone'),
                \Filament\Navigation\NavigationGroup::make('Content')->icon('heroicon-o-document-text'),
                \Filament\Navigation\NavigationGroup::make('Research')->icon('heroicon-o-magnifying-glass'),
                \Filament\Navigation\NavigationGroup::make('SEO')->icon('heroicon-o-chart-bar-square'),
                \Filament\Navigation\NavigationGroup::make('Network')->icon('heroicon-o-globe-alt'),
                \Filament\Navigation\NavigationGroup::make('Security')->icon('heroicon-o-shield-check'),
                \Filament\Navigation\NavigationGroup::make('System')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->databaseNotifications(fn (): bool => Schema::hasTable('notifications'))
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => view('filament.autosave')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // No AccountWidget: the "Welcome / Sign out" card wasted the
            // dashboard's first row — stats lead; sign-out lives in the
            // user menu.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        // Super Admin bypasses every ability check.
        Gate::before(function ($user, string $ability) {
            return $user instanceof User && $user->hasRole('Super Admin') ? true : null;
        });
    }
}
