<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Performance\Cloudflare;
use App\Services\Performance\LiteSpeedPurger;
use App\Services\Performance\PageCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use UnitEnum;

class PerformanceSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 91;

    protected static ?string $title = 'Performance & cache';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'performance';
    }

    protected function keys(): array
    {
        return [
            'litespeed_cache_enabled', 'public_cache_ttl',
            'page_cache_enabled', 'page_cache_ttl', 'critical_css_enabled',
            'cloudflare_email', 'cloudflare_api_key', 'cloudflare_domain',
            'image_webp_enabled', 'image_lazy_load', 'minify_html',
        ];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        // Runtime default is ON (see CriticalCss::enabled) — the toggle must
        // reflect that when the setting was never saved.
        $data['critical_css_enabled'] ??= true;
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $isLiteSpeed = PageCache::isLiteSpeedServer();
        $cloudflare = app(Cloudflare::class);

        return $schema->components([
            Section::make('Server')->schema([
                Placeholder::make('server_info')
                    ->label('Detected web server')
                    ->content(new HtmlString(
                        e((string) request()->server('SERVER_SOFTWARE', 'unknown')).' — '.(
                            $isLiteSpeed
                                ? '<strong>LiteSpeed detected.</strong> Guest pages are cached by the server itself (via the LiteSpeed headers below); the app-level page cache stays off in Auto mode to avoid double caching.'
                                : 'not LiteSpeed. In Auto mode the app-level page cache serves guest pages, so visitors never boot the full CMS.'
                        )
                    )),
            ]),
            Section::make('Guest page cache')
                ->description('Full-page cache for visitors. Logged-in users and dynamic pages (cart, checkout, account, search) are never cached. The cart badge and CSRF tokens hydrate via JavaScript, so add-to-cart works on cached pages.')
                ->columns(2)
                ->schema([
                    Select::make('page_cache_enabled')
                        ->label('Page cache')
                        ->options([
                            'auto' => 'Auto (recommended — off on LiteSpeed servers)',
                            'on' => 'Always on',
                            'off' => 'Off',
                        ])
                        ->default('auto')
                        ->placeholder('Auto (recommended — off on LiteSpeed servers)'),
                    TextInput::make('page_cache_ttl')->label('Page cache TTL')->numeric()->suffix('seconds')->default(3600),
                    Toggle::make('critical_css_enabled')
                        ->label('Critical CSS')
                        ->helperText('Inlines only the CSS each page actually uses and loads the full stylesheet in the background — removes render-blocking CSS for visitors. Generated per page in PHP (no external service) and regenerated automatically on content changes and Purge All.')
                        ->default(true)
                        ->inline(false)
                        ->columnSpanFull(),
                ]),
            Section::make('LiteSpeed cache')
                ->description('Cache headers for LiteSpeed/OpenLiteSpeed servers. Harmless on other servers — the headers are simply ignored.')
                ->columns(2)
                ->schema([
                    Toggle::make('litespeed_cache_enabled')->default(true)->inline(false),
                    TextInput::make('public_cache_ttl')->numeric()->suffix('seconds')->default(3600),
                ]),
            Section::make('Cloudflare')
                ->description('Connect with your Cloudflare account email, Global API Key (My Profile → API Tokens → Global API Key) and the site domain. Once connected, Purge All clears the Cloudflare edge cache together with the site cache.')
                ->columns(2)
                ->schema([
                    TextInput::make('cloudflare_email')->label('Cloudflare email')->email(),
                    TextInput::make('cloudflare_api_key')->label('Global API Key')->password()->revealable(),
                    TextInput::make('cloudflare_domain')->label('Domain')->placeholder('example.com'),
                    Placeholder::make('cloudflare_status')
                        ->label('Connection status')
                        ->content(fn () => $cloudflare->connected()
                            ? 'Connected — zone '.setting('performance.cloudflare_zone_id')
                            : ($cloudflare->configured() ? 'Not verified yet — save, then click "Check Cloudflare connection".' : 'Not configured.')),
                ]),
            Section::make('Images')->columns(2)->schema([
                Toggle::make('image_webp_enabled')->default(true)->inline(false),
                Toggle::make('image_lazy_load')->default(true)->inline(false),
            ]),
            Section::make('HTML output')->schema([
                Toggle::make('minify_html'),
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

        // Credentials changed → the stored zone id may belong to the old
        // account/domain; force a fresh connection check.
        foreach (['cloudflare_email', 'cloudflare_api_key', 'cloudflare_domain'] as $key) {
            if (($old[$key] ?? null) !== ($data[$key] ?? null)) {
                Setting::set('performance.cloudflare_zone_id', null);
                break;
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => "settings:{$group}",
            'old_values' => \Illuminate\Support\Arr::except($old, ['cloudflare_api_key']),
            'new_values' => \Illuminate\Support\Arr::except($data, ['cloudflare_api_key']),
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('Performance settings saved')->success()->send();
    }

    /** Rendered in the page header — the shared settings-form view only shows its own Save button. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('purgeAll')
                ->label('Purge All')
                ->color('warning')
                ->icon(Heroicon::OutlinedTrash)
                ->requiresConfirmation()
                ->modalDescription(fn () => app(Cloudflare::class)->connected()
                    ? 'Clears the page cache, LiteSpeed cache and the Cloudflare edge cache.'
                    : 'Clears the page cache and LiteSpeed cache. (Cloudflare is not connected.)')
                ->action(function () {
                    PageCache::flush();
                    app(LiteSpeedPurger::class)->purgeAll();
                    Cache::flush();

                    $lines = ['Site cache purged.'];

                    $cloudflare = app(Cloudflare::class);
                    if ($cloudflare->connected()) {
                        $result = $cloudflare->purgeAll();
                        $lines[] = $result['message'];
                    }

                    Notification::make()
                        ->title('Purge All complete')
                        ->body(implode(' ', $lines))
                        ->success()
                        ->send();
                }),
            Action::make('cloudflareConnect')
                ->label('Check Cloudflare connection')
                ->color('gray')
                ->icon(Heroicon::OutlinedCloud)
                ->action(function () {
                    $result = app(Cloudflare::class)->connect();

                    Notification::make()
                        ->title($result['ok'] ? 'Cloudflare connected' : 'Cloudflare connection failed')
                        ->body($result['message'])
                        ->{$result['ok'] ? 'success' : 'danger'}()
                        ->send();

                    if ($result['ok']) {
                        $this->redirect(static::getUrl());
                    }
                }),
            Action::make('cloudflareOptimize')
                ->label('Optimize Cloudflare')
                ->color('gray')
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn () => app(Cloudflare::class)->connected())
                ->requiresConfirmation()
                ->modalDescription('Applies cache settings tuned for this site: aggressive static-asset caching, browser cache TTL, Brotli, Early Hints, Always Online — and turns Rocket Loader off (it breaks the storefront JavaScript).')
                ->action(function () {
                    $result = app(Cloudflare::class)->optimize();

                    $details = collect($result['results'])
                        ->map(fn ($status, $name) => "{$name}: {$status}")
                        ->implode('; ');

                    Notification::make()
                        ->title($result['ok'] ? 'Cloudflare optimized' : 'Cloudflare optimize failed')
                        ->body($result['message'].($details ? " {$details}" : ''))
                        ->{$result['ok'] ? 'success' : 'danger'}()
                        ->send();
                }),
        ];
    }
}
