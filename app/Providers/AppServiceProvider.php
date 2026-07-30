<?php

namespace App\Providers;

use App\Filament\Support\RichEditorTableEditingPlugin;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Observers\CategoryObserver;
use App\Observers\PostObserver;
use App\Observers\ProductImageObserver;
use App\Observers\ProductObserver;
use App\Payments\PaymentManager;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Running artisan as root plants root-owned files in storage/ and
        // .git/ that the site user then cannot write — the repeated cause of
        // "product save spins forever" (broken cache writes) and failed git
        // pulls on the VPS. Warn loudly every time; the fix is always
        // `su - <site-user> -c '...'`.
        if ($this->app->runningInConsole()
            && $this->app->environment('production')
            && function_exists('posix_geteuid')
            && posix_geteuid() === 0) {
            fwrite(STDERR, "\n\033[41;97m  WARNING: artisan is running as ROOT.  \033[0m\n"
                . "Files it creates in storage/ and bootstrap/cache will be root-owned and will\n"
                . "break the site user's cache writes (the classic save-button-spinner cause).\n"
                . "Run instead:  su - <site-user> -c 'cd " . base_path() . " && php artisan ...'\n\n");
        }

        // Force a backup before any schema wipe (migrate:fresh, db:wipe, …),
        // whoever/whatever runs it. See app/Support/DatabaseSafetyGuard.php.
        \App\Support\DatabaseSafetyGuard::register();

        // Admin panel polish stylesheet (cross-page refinements the theme
        // can't express). Guarded: missing pre-build manifest must not
        // break artisan commands.
        try {
            \Filament\Support\Facades\FilamentAsset::register([
                \Filament\Support\Assets\Css::make('blogkit-admin', \Illuminate\Support\Facades\Vite::asset('resources/css/filament-admin.css')),
            ]);
        } catch (\Throwable) {
            // Assets not built yet — the admin simply loads without polish.
        }

        // Make table editing discoverable in every Filament rich editor.
        RichEditor::configureUsing(function (RichEditor $editor): void {
            $editor->plugins([new RichEditorTableEditingPlugin]);

            $editor->enableToolbarButtons([
                [
                    ToolbarButtonGroup::make('Table editing', [
                        'tableAddColumnBefore',
                        'tableAddColumnAfter',
                        'tableDeleteColumn',
                        'tableAddRowBefore',
                        'tableAddRowAfter',
                        'tableDeleteRow',
                        'tableMergeCells',
                        'tableSplitCell',
                        'tableToggleHeaderRow',
                        'tableToggleHeaderCell',
                        'tableDelete',
                    ])
                        ->icon('fi-o-table')
                        ->textualButtons(),
                ],
            ]);
        });

        // Global list pagination: default 20 rows, selectable up to 999 in a
        // single page. Applies to every admin table; a resource can still
        // override with its own ->paginationPageOptions()/->paginated().
        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table->paginationPageOptions([20, 50, 100, 250, 500, 999])
                ->defaultPaginationPageOption(20);
        });

        // NOTE: OrderPlaced/OrderStatusChanged listeners are NOT registered
        // here. Laravel auto-discovers listeners in app/Listeners from their
        // handle() type-hint, so registering them explicitly as well made each
        // one fire TWICE — customers received duplicate order-confirmation
        // emails and admins got doubled notifications. Discovery is the single
        // source of truth: SendOrderPlacedEmails, NotifyLowStock (OrderPlaced)
        // and SendOrderStatusEmail (OrderStatusChanged).

        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);
        Post::observe(PostObserver::class);
        ProductImage::observe(ProductImageObserver::class);
        \App\Models\CustomLinkTarget::observe(\App\Observers\CustomLinkTargetObserver::class);
        // The SeoMeta row computes its own score on save — parent observers
        // only update an existing row, so form + observer never both insert.
        \App\Models\SeoMeta::observe(\App\Observers\SeoMetaObserver::class);

        // Bust content-block render cache whenever a block is saved/deleted.
        \App\Models\ContentBlock::saved(fn () => \Illuminate\Support\Facades\Cache::forget('content_blocks.rendered'));
        \App\Models\ContentBlock::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('content_blocks.rendered'));

        // Sitemaps auto-update: any change to sitemap-relevant content bumps
        // the cache version, so new/edited products and posts appear in the
        // sitemap immediately (one cheap cache write per save).
        foreach ([Product::class, Post::class, Category::class, \App\Models\Page::class, \App\Models\PostCategory::class] as $model) {
            $model::saved(fn () => \App\Services\Seo\SitemapGenerator::flush());
            $model::deleted(fn () => \App\Services\Seo\SitemapGenerator::flush());

            // Guest page cache: content changed → every cached page regenerates.
            $model::saved(fn () => \App\Services\Performance\PageCache::flush());
            $model::deleted(fn () => \App\Services\Performance\PageCache::flush());
        }

        // Settings drive the theme, header, currency, etc. — cached guest
        // pages must never show stale settings.
        \App\Models\Setting::saved(fn () => \App\Services\Performance\PageCache::flush());

        // ── Mail: settings-driven configuration ──────────────────────
        // The admin picks Log / SMTP / Gmail in Email settings; SMTP
        // credentials and the sender identity come from settings too, so
        // mail works without touching .env. The gmail transport delivers
        // via the Gmail API using the 1-click OAuth connection.
        \Illuminate\Support\Facades\Mail::extend('gmail', fn () => new \App\Services\Mail\GmailTransport(app(\App\Services\Mail\GmailOAuth::class)));

        try {
            $emails = \App\Models\Setting::group('emails');

            config(['mail.mailers.gmail' => ['transport' => 'gmail']]);

            if (! empty($emails['from_email'])) {
                config([
                    'mail.from.address' => $emails['from_email'],
                    'mail.from.name' => $emails['from_name'] ?? config('app.name'),
                ]);
            }

            if (! empty($emails['smtp_host'])) {
                config([
                    'mail.mailers.smtp.host' => $emails['smtp_host'],
                    'mail.mailers.smtp.port' => (int) ($emails['smtp_port'] ?? 587),
                    'mail.mailers.smtp.username' => $emails['smtp_username'] ?? null,
                    'mail.mailers.smtp.password' => $emails['smtp_password'] ?? null,
                    'mail.mailers.smtp.scheme' => ($emails['smtp_encryption'] ?? 'tls') === 'ssl' ? 'smtps' : null,
                ]);
            }

            if (in_array($emails['mailer'] ?? null, ['log', 'smtp', 'gmail'], true)) {
                config(['mail.default' => $emails['mailer']]);
            }
        } catch (\Throwable) {
            // Settings table missing (fresh install) — keep .env defaults.
        }

        // IndexNow: push published/updated URLs to Bing+Yandex instantly.
        // Fire-and-forget with a 5-minute per-URL dedupe; failures only log.
        Product::saved(function (Product $product) {
            if ($product->status === 'published') {
                try {
                    app(\App\Services\Seo\IndexNow::class)->ping($product->url());
                } catch (\Throwable) {
                }
            }
        });
        Post::saved(function (Post $post) {
            if ($post->status === 'published') {
                try {
                    app(\App\Services\Seo\IndexNow::class)->ping($post->url());
                } catch (\Throwable) {
                }
            }
        });
        Category::saved(function (Category $category) {
            try {
                app(\App\Services\Seo\IndexNow::class)->ping($category->url());
            } catch (\Throwable) {
            }
        });
        \App\Models\Page::saved(function (\App\Models\Page $page) {
            if ($page->status === 'published') {
                try {
                    app(\App\Services\Seo\IndexNow::class)->ping($page->url());
                } catch (\Throwable) {
                }
            }
        });

        // Note: deletions do NOT ping IndexNow. Removing content is not a
        // signal worth an outbound submit — engines re-crawl and drop dead
        // URLs on their own, and it keeps bulk deletes clean (no per-record
        // ping storm). Only publishes/updates ping (above).

        // {!! shortcodes($html) !!} — expand {{block:key}} references inside HTML content.
        \Illuminate\Support\Facades\Blade::directive('shortcodes', fn ($expression) => "<?php echo parse_shortcodes({$expression}); ?>");

        $this->configureRateLimiting();
    }

    /** Redis-backed (via default cache store) rate limits for sensitive endpoints. */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        // Commerce + search limits are PER SHOPPER (login id, else the session
        // cookie) so many genuine customers sharing one public IP — UAE mobile
        // carrier-grade NAT, mall/office/hotel Wi-Fi — never collectively trip
        // the limit. A high per-IP backstop still stops a single cookie-less
        // bot from hammering the origin. A real bulk shopper adding many
        // flavors is comfortably under these ceilings (and the −/qty/+ stepper
        // uses a different, unthrottled endpoint).
        $perShopper = fn (Request $r) => $r->user()?->id ?? 'sid:'.$r->session()->getId();

        RateLimiter::for('checkout', fn (Request $r) => [
            Limit::perMinute(12)->by($perShopper($r)),
            Limit::perMinute(60)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('add-to-cart', fn (Request $r) => [
            Limit::perMinute(60)->by($perShopper($r)),
            Limit::perMinute(300)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('coupon', fn (Request $r) => [
            Limit::perMinute(10)->by($perShopper($r)),
            Limit::perMinute(60)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('search', fn (Request $r) => [
            Limit::perMinute(60)->by($perShopper($r)),
            Limit::perMinute(240)->by('ip:'.$r->ip()),
        ]);
        // Live suggestions fire per settled keystroke — a higher ceiling.
        RateLimiter::for('search-suggest', fn (Request $r) => [
            Limit::perMinute(150)->by($perShopper($r)),
            Limit::perMinute(600)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('contact', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('reviews', fn (Request $r) => Limit::perMinute(3)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('newsletter', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
    }
}
