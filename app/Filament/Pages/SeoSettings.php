<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class SeoSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'SEO settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'seo';
    }

    protected function keys(): array
    {
        return [
            'site_title', 'default_title_format', 'default_description', 'default_og_image',
            'homepage_title', 'homepage_description',
            'homepage_og_title', 'homepage_og_description', 'homepage_og_image', 'homepage_noindex',
            'organization_name', 'organization_logo',
            'social_facebook', 'social_instagram', 'social_twitter', 'social_youtube',
            'local_business_enabled', 'local_business_type', 'local_business_phone',
            'local_business_address', 'local_business_city', 'local_business_country',
            'local_business_email', 'local_business_region', 'local_business_postal_code',
            'local_business_latitude', 'local_business_longitude', 'local_business_map_url',
            'local_business_price_range', 'local_business_payment', 'local_business_description',
            'local_business_image', 'local_business_area_served', 'local_business_hours',
            'locations',
            'pagespeed_api_key',
            'indexnow_enabled', 'indexnow_key',
            'google_service_account_json', 'gsc_property', 'ga4_property_id',
            'return_policy_days', 'shipping_rate', 'shipping_handling_days', 'shipping_transit_days',
            'return_fees', 'return_method',
            'sitemap_products', 'sitemap_categories', 'sitemap_posts', 'sitemap_pages',
            'sitemap_post_categories', 'sitemap_authors',
            'sitemap_links_per_page', 'sitemap_images',
            'sitemap_exclude_product_ids', 'sitemap_exclude_post_ids',
            'feed_enabled', 'feed_exclude_product_ids',
            'robots_txt',
            'discourage_indexing',
            'product_base', 'category_base', 'blog_base',
            'verify_google', 'verify_bing', 'verify_yandex', 'verify_baidu', 'verify_pinterest',
            'google_tag_manager_id', 'google_tag_id', 'custom_head_code', 'custom_body_code',
            'markdown_for_agents', 'agents_json',
        ];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }

        // Show the effective base when never set (null → default); an explicit
        // '' (root-level) is preserved.
        foreach (['product' => 'product_base', 'category' => 'category_base', 'post' => 'blog_base'] as $type => $key) {
            if (! array_key_exists($key, $values) || $values[$key] === null) {
                $data[$key] = \App\Support\Permalinks::DEFAULTS[$type];
            }
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Global SEO defaults')->columns(2)->schema([
                TextInput::make('site_title')->helperText('Used as the {sitename} placeholder.'),
                TextInput::make('default_title_format')->helperText('E.g. "{title} | {sitename}".'),
                Textarea::make('default_description')->rows(2)->columnSpanFull(),
                FileUpload::make('default_og_image')->image()->disk('public')->directory('seo'),
            ]),
            Section::make('Search engine visibility')
                ->description('Hide the whole site from Google, Bing and other crawlers while you build or stage it — like WordPress\'s "Discourage search engines". This is separate from Maintenance mode (General settings), which hides the site from human visitors.')
                ->schema([
                    \Filament\Forms\Components\Toggle::make('discourage_indexing')
                        ->label('Discourage search engines from indexing this site')
                        ->helperText('On = every page sends noindex and robots.txt blocks everything. Turn OFF before you want to rank.'),
                ]),
            Section::make('Permalinks')
                ->description('URL bases for your pages. Leave the defaults to keep today\'s URLs. Change a base to reshape the URLs — existing links auto-redirect (301) to the new ones. The blog base must stay set.')
                ->columns(3)
                ->schema([
                    // Store-only: hidden while the ecommerce module is off.
                    TextInput::make('product_base')
                        ->label('Product base')
                        ->placeholder('product')
                        ->helperText(fn ($state): string => '/'.trim(($state ?: 'product'), '/').'/your-product')
                        ->visible(fn () => ecommerce_enabled()),
                    TextInput::make('category_base')
                        ->label('Category base')
                        ->placeholder('category')
                        ->helperText(fn ($state): string => '/'.trim(($state ?: 'category'), '/').'/your-category')
                        ->visible(fn () => ecommerce_enabled()),
                    TextInput::make('blog_base')
                        ->label('Blog base')
                        ->placeholder('blog')
                        ->helperText(fn ($state): string => '/'.trim(($state ?: 'blog'), '/').'/your-post'),
                ]),
            Section::make('Search engine verification')
                ->description('Prove site ownership in each search engine\'s webmaster console. Paste the verification code (or the whole <meta> tag — the code is extracted) and it is added to every page\'s <head>. Works even while "discourage indexing" is on.')
                ->columns(2)
                ->schema([
                    TextInput::make('verify_google')
                        ->label('Google Search Console')
                        ->helperText('Settings → Ownership verification → HTML tag.'),
                    TextInput::make('verify_bing')
                        ->label('Bing Webmaster Tools'),
                    TextInput::make('verify_yandex')
                        ->label('Yandex Webmaster'),
                    TextInput::make('verify_baidu')
                        ->label('Baidu Webmaster (百度)'),
                    TextInput::make('verify_pinterest')
                        ->label('Pinterest'),
                ]),
            Section::make('Google tag & tracking code')
                ->description('Analytics and marketing tags, placed correctly for you: Tag Manager loads high in the <head> with its <noscript> right after <body>; the Google tag (gtag.js / GA4) loads in the <head>. Use the custom boxes for any other pixel (Meta, TikTok, etc.).')
                ->columns(2)
                ->schema([
                    TextInput::make('google_tag_manager_id')
                        ->label('Google Tag Manager ID')
                        ->placeholder('GTM-XXXXXXX')
                        ->helperText('Container ID from tagmanager.google.com.'),
                    TextInput::make('google_tag_id')
                        ->label('Google tag / GA4 Measurement ID')
                        ->placeholder('G-XXXXXXXXXX')
                        ->helperText('The "G-" ID from Google Analytics 4 (also accepts AW-/UA-/GT-).'),
                    Textarea::make('custom_head_code')
                        ->label('Custom <head> code')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('Raw HTML/JS injected into every page\'s <head>. For meta pixels, verification snippets, etc. Leave blank if unused.'),
                    Textarea::make('custom_body_code')
                        ->label('Custom code after <body>')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('Raw HTML/JS injected immediately after the opening <body> tag (e.g. noscript pixels).'),
                ]),
            Section::make('AI answer engines (AEO / GEO)')
                ->description(new \Illuminate\Support\HtmlString(
                    'Help ChatGPT, Perplexity, Claude and Google AI Overviews read and cite the store accurately. '
                    .'Verify: <code>'.e(url('/llms.txt')).'</code>, <code>'.e(url('/llms-full.txt')).'</code>, '
                    .'<code>'.e(url('/.well-known/agents.json')).'</code>, and append <code>.md</code> to any product/category/blog/page URL.'
                ))
                ->schema([
                    \Filament\Forms\Components\Toggle::make('markdown_for_agents')
                        ->label('Serve Markdown to AI agents')
                        ->default(true)
                        ->helperText('Returns a clean, low-token markdown version of pages on “Accept: text/markdown” and at “.md” URLs, and advertises it via rel=alternate + llms.txt.'),
                    \Filament\Forms\Components\Toggle::make('agents_json')
                        ->label('Publish /.well-known/agents.json')
                        ->default(true)
                        ->helperText('A minimal, read-only discovery manifest (llms.txt, sitemap, product feed, search, markdown capability).'),
                ]),
            Section::make('Homepage')
                ->description('SEO for your storefront home page (/). Leave a field blank to fall back to the global defaults above.')
                ->columns(2)->schema([
                    TextInput::make('homepage_title')
                        ->label('SEO title')
                        ->maxLength(70)
                        ->helperText('Shown in the browser tab and as the Google result title. Aim for 50-60 characters.'),
                    TextInput::make('homepage_description')
                        ->label('Meta description')
                        ->maxLength(200)
                        ->helperText('The snippet under the title in search results. Aim for 150-160 characters.')
                        ->columnSpanFull(),
                    TextInput::make('homepage_og_title')
                        ->label('Social share title (optional)')
                        ->helperText('Title used on Facebook/WhatsApp/X cards. Falls back to the SEO title.'),
                    TextInput::make('homepage_og_description')
                        ->label('Social share description (optional)')
                        ->helperText('Falls back to the meta description.'),
                    FileUpload::make('homepage_og_image')
                        ->label('Social share image (optional)')
                        ->image()->disk('public')->directory('seo')
                        ->helperText('Shown when the homepage is shared. 1200×630px recommended. Falls back to the global default OG image.')
                        ->columnSpanFull(),
                    \Filament\Forms\Components\Toggle::make('homepage_noindex')
                        ->label('Discourage search engines from indexing the homepage')
                        ->helperText('Leave OFF for a live store — turning this on removes the homepage from Google.'),
                ]),
            Section::make('Organization schema')->columns(2)->schema([
                TextInput::make('organization_name'),
                FileUpload::make('organization_logo')->image()->disk('public')->directory('seo'),
            ]),
            Section::make('Social profiles')->columns(2)->schema([
                TextInput::make('social_facebook')->url()->prefix('https://'),
                TextInput::make('social_instagram')->url()->prefix('https://'),
                TextInput::make('social_twitter')->url()->prefix('https://'),
                TextInput::make('social_youtube')->url()->prefix('https://'),
            ]),
            Section::make('Local SEO')
                ->description('Google Business Profile-style data, output as LocalBusiness schema: identity, address, geo pin, opening hours, price range, payments and service area — plus one block per extra location for multi-city visibility.')
                ->columns(3)
                ->schema([
                    \Filament\Forms\Components\Toggle::make('local_business_enabled')
                        ->label('Enable LocalBusiness schema')
                        ->columnSpanFull(),
                    \Filament\Forms\Components\Select::make('local_business_type')
                        ->label('Business type')
                        ->options([
                            'Store' => 'Store (general retail)',
                            'ConvenienceStore' => 'Convenience store',
                            'DepartmentStore' => 'Department store',
                            'ElectronicsStore' => 'Electronics store',
                            'GroceryStore' => 'Grocery store',
                            'LiquorStore' => 'Liquor store',
                            'OnlineStore' => 'Online store',
                            'LocalBusiness' => 'LocalBusiness (generic)',
                        ])
                        ->native(false)
                        ->placeholder('Store'),
                    TextInput::make('local_business_phone')->label('Phone')->placeholder('+9715xxxxxxxx'),
                    TextInput::make('local_business_email')->label('Email')->email(),
                    TextInput::make('local_business_address')->label('Street address'),
                    TextInput::make('local_business_city')->label('City')->placeholder('Dubai'),
                    TextInput::make('local_business_region')->label('Region / emirate')->placeholder('Dubai'),
                    TextInput::make('local_business_postal_code')->label('Postal code'),
                    TextInput::make('local_business_country')->label('Country code')->placeholder('AE')->maxLength(2),
                    TextInput::make('local_business_map_url')
                        ->label('Google Maps / GBP link')
                        ->url()
                        ->placeholder('https://maps.google.com/?cid=…')
                        ->helperText('Your Google Business Profile listing URL.'),
                    TextInput::make('local_business_latitude')->label('Latitude')->numeric()->placeholder('25.2048'),
                    TextInput::make('local_business_longitude')->label('Longitude')->numeric()->placeholder('55.2708'),
                    TextInput::make('local_business_price_range')
                        ->label('Price range')
                        ->placeholder('AED 30 - 300 (or $$)'),
                    TextInput::make('local_business_payment')
                        ->label('Payment accepted')
                        ->placeholder('Cash on delivery, Card on delivery'),
                    TextInput::make('local_business_area_served')
                        ->label('Service area (cities)')
                        ->placeholder('Dubai, Sharjah, Ajman, Abu Dhabi')
                        ->helperText('Comma-separated — for delivery businesses this is your coverage.'),
                    FileUpload::make('local_business_image')
                        ->label('Business photo')
                        ->image()
                        ->disk('public')
                        ->directory('seo'),
                    Textarea::make('local_business_description')
                        ->label('Business description')
                        ->rows(2)
                        ->columnSpan(2),
                    \Filament\Forms\Components\Repeater::make('local_business_hours')
                        ->label('Opening hours')
                        ->columnSpanFull()
                        ->columns(3)
                        ->default([])
                        ->schema([
                            \Filament\Forms\Components\Select::make('days')
                                ->multiple()
                                ->options(array_combine(
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                                    $days,
                                ))
                                ->required(),
                            \Filament\Forms\Components\TimePicker::make('opens')->seconds(false)->required(),
                            \Filament\Forms\Components\TimePicker::make('closes')->seconds(false)->required(),
                        ])
                        ->helperText('One row per schedule, e.g. Monday-Saturday 09:00-23:00 + Sunday 14:00-22:00. Open 24/7 → all days, 00:00 to 23:59.'),
                    \Filament\Forms\Components\Repeater::make('locations')
                        ->label('Extra locations (multi-city)')
                        ->columnSpanFull()
                        ->columns(3)
                        ->default([])
                        ->schema([
                            TextInput::make('name')->required()->placeholder('Terea Hub Dubai'),
                            TextInput::make('city')->required()->placeholder('Dubai'),
                            TextInput::make('type')->placeholder('Store'),
                            TextInput::make('phone'),
                            TextInput::make('address')->placeholder('Street address'),
                            TextInput::make('postal_code')->label('Postal code'),
                            TextInput::make('country')->placeholder('AE')->maxLength(2),
                            TextInput::make('latitude')->numeric()->placeholder('25.2048'),
                            TextInput::make('longitude')->numeric()->placeholder('55.2708'),
                            TextInput::make('map_url')->label('Google Maps link')->url(),
                            TextInput::make('price_range')->label('Price range')->placeholder('AED 30 - 300'),
                            TextInput::make('url')->url()->placeholder('Location/delivery page URL (optional)'),
                            TextInput::make('opening_hours')->placeholder('Mo-Su 09:00-23:00'),
                        ]),
                ]),
            Section::make('XML sitemap')
                ->description(fn () => 'Sitemap index: '.route('sitemap.index').' — split by content type, updates automatically the moment content changes. lastmod comes from the real modification time; changefreq/priority are omitted (Google and Bing ignore them).')
                ->columns(3)
                ->schema([
                    \Filament\Forms\Components\Toggle::make('sitemap_products')->label('Products')->default(true),
                    \Filament\Forms\Components\Toggle::make('sitemap_categories')->label('Product categories')->default(true),
                    \Filament\Forms\Components\Toggle::make('sitemap_posts')->label('Blog posts')->default(true),
                    \Filament\Forms\Components\Toggle::make('sitemap_pages')->label('Pages')->default(true),
                    \Filament\Forms\Components\Toggle::make('sitemap_post_categories')->label('Blog categories')->default(true),
                    \Filament\Forms\Components\Toggle::make('sitemap_authors')->label('Author archives')->default(false),
                    TextInput::make('sitemap_links_per_page')
                        ->label('URLs per sitemap file')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(49500)
                        ->placeholder('1000')
                        ->helperText('Large types split into numbered files (sitemap-products-2.xml…). Spec limit is 50,000.'),
                    \Filament\Forms\Components\Toggle::make('sitemap_images')
                        ->label('Include images')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Adds <image:image> entries for product/post images so Google Images indexes them.'),
                    \Filament\Schemas\Components\Grid::make(1)->columnSpan(1)->schema([
                        TextInput::make('sitemap_exclude_product_ids')
                            ->label('Exclude product IDs')
                            ->placeholder('12, 34')
                            ->helperText('Comma-separated.'),
                        TextInput::make('sitemap_exclude_post_ids')
                            ->label('Exclude post IDs')
                            ->placeholder('5, 8'),
                    ]),
                ]),
            Section::make('Product feed (Google Merchant / Bing Merchant)')
                ->description(new \Illuminate\Support\HtmlString(
                    'Free organic Shopping-tab listings. Submit this URL in Merchant Center and Bing Merchant: '
                    .'<code>'.e(url('/feeds/products.xml')).'</code>. Setup guide: docs/SEARCH-ENGINES.md.'
                ))
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\Toggle::make('feed_enabled')
                        ->label('Serve the product feed')
                        ->default(true)
                        ->inline(false),
                    TextInput::make('feed_exclude_product_ids')
                        ->label('Exclude product IDs')
                        ->placeholder('12, 34')
                        ->helperText('Comma-separated product IDs to leave out of the feed.'),
                ]),

            Section::make('Product schema: shipping & returns')
                ->description('Completes the Offer schema per Google\'s product structured-data docs — delivery cost and speed shown with your products in search.')
                ->columns(2)
                ->schema([
                    TextInput::make('shipping_rate')
                        ->label('Delivery fee ('.store_currency().')')
                        ->numeric()
                        ->placeholder('0 = free delivery')
                        ->helperText('Standard delivery charge. Leave empty to omit shipping details from the schema.'),
                    TextInput::make('return_policy_days')
                        ->label('Return window (days)')
                        ->numeric()
                        ->placeholder('7'),
                    TextInput::make('shipping_handling_days')
                        ->label('Handling time (max days)')
                        ->numeric()
                        ->placeholder('0 — same-day dispatch'),
                    TextInput::make('shipping_transit_days')
                        ->label('Transit time (max days)')
                        ->numeric()
                        ->placeholder('1'),
                    \Filament\Forms\Components\Select::make('return_fees')
                        ->label('Return shipping cost')
                        ->options(['free' => 'Free returns (store pays)', 'customer' => 'Customer pays return shipping'])
                        ->native(false)
                        ->placeholder('Not specified')
                        ->helperText('Completes the merchant return policy in the product schema (returnFees).'),
                    \Filament\Forms\Components\Select::make('return_method')
                        ->label('Return method')
                        ->options(['mail' => 'Return by mail / courier', 'store' => 'Return in store'])
                        ->native(false)
                        ->placeholder('Not specified')
                        ->helperText('How customers send items back (returnMethod).'),
                ]),
            Section::make('Integrations')->columns(2)->schema([
                TextInput::make('pagespeed_api_key')
                    ->label('PageSpeed Insights API key')
                    ->password()
                    ->revealable()
                    ->helperText('Optional — free from Google Cloud Console. Gives the weekly PageSpeed tracker a reliable quota.'),
                \Filament\Forms\Components\Toggle::make('indexnow_enabled')
                    ->label('IndexNow (Bing + Yandex instant indexing)')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Pings IndexNow the moment a product, post or category is published or updated.'),
                TextInput::make('indexnow_key')
                    ->label('IndexNow key')
                    ->placeholder('auto-generated on first ping')
                    ->helperText(fn () => 'Served automatically at '.\App\Services\Seo\IndexNow::keyFileUrl().' — no file upload needed.'),
                TextInput::make('gsc_property')
                    ->label('Search Console property')
                    ->placeholder('sc-domain:tereahub.ae  (or https://tereahub.ae/)')
                    ->helperText('Domain properties use the sc-domain: prefix.'),
                TextInput::make('ga4_property_id')
                    ->label('GA4 property ID')
                    ->placeholder('123456789')
                    ->helperText('Numeric ID from GA4 Admin → Property settings. Optional.'),
                Textarea::make('google_service_account_json')
                    ->label('Google service account JSON')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Create a service account in Google Cloud Console (enable Search Console + Analytics Data APIs), download its JSON key and paste it here. Then add its email as a user on your Search Console property and GA4 property. Powers the Search performance page.'),
            ]),
            Section::make('robots.txt')->schema([
                Textarea::make('robots_txt')
                    ->rows(10)
                    ->helperText('Overrides the auto-generated robots.txt. Leave empty to use the default.'),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $group = $this->group();
        $data = $this->form->getState();

        // Normalize + validate the permalink bases before anything is written,
        // so a bad base can never break routing.
        foreach (['product_base', 'category_base', 'blog_base'] as $key) {
            $data[$key] = \App\Support\Permalinks::normalize($data[$key] ?? null);
        }
        $bases = [
            'product_base' => ['label' => 'Product base', 'value' => $data['product_base'], 'allowEmpty' => true],
            'category_base' => ['label' => 'Category base', 'value' => $data['category_base'], 'allowEmpty' => true],
            'blog_base' => ['label' => 'Blog base', 'value' => $data['blog_base'], 'allowEmpty' => false],
        ];
        foreach ($bases as $key => $meta) {
            $others = collect($bases)->except($key)->pluck('value')->filter()->all();
            if ($error = \App\Support\Permalinks::validate($meta['value'], $others, $meta['allowEmpty'])) {
                Notification::make()->title($meta['label'].': '.$error)->danger()->persistent()->send();

                return; // nothing saved — the owner fixes the value and retries
            }
        }

        // Validate tracking IDs so a typo can't emit a broken snippet.
        $data['google_tag_manager_id'] = trim((string) ($data['google_tag_manager_id'] ?? ''));
        $data['google_tag_id'] = trim((string) ($data['google_tag_id'] ?? ''));
        if ($data['google_tag_manager_id'] !== '' && ! preg_match('/^GTM-[A-Z0-9]+$/i', $data['google_tag_manager_id'])) {
            Notification::make()->title('Google Tag Manager ID looks wrong — it should be like GTM-XXXXXXX.')->danger()->persistent()->send();

            return;
        }
        if ($data['google_tag_id'] !== '' && ! preg_match('/^(G|AW|UA|GT)-[A-Z0-9-]+$/i', $data['google_tag_id'])) {
            Notification::make()->title('Google tag / GA4 ID looks wrong — it should be like G-XXXXXXXXXX.')->danger()->persistent()->send();

            return;
        }

        $permalinksChanged = collect(['product_base', 'category_base', 'blog_base'])
            ->contains(fn ($k) => ($data[$k] ?? '') !== (string) Setting::get("{$group}.{$k}", ''));

        $old = [];
        foreach ($this->keys() as $key) {
            $old[$key] = Setting::get("{$group}.{$key}");
        }
        foreach ($data as $key => $value) {
            Setting::set("{$group}.{$key}", $value);
        }
        Cache::forget("settings.{$group}");

        // Routes are built from these bases at boot; drop any cached route
        // table so the new URLs take effect immediately (prod re-caches on deploy).
        if ($permalinksChanged) {
            try {
                \Illuminate\Support\Facades\Artisan::call('route:clear');
            } catch (\Throwable) {
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => "settings:{$group}",
            'old_values' => $old,
            'new_values' => $data,
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('SEO settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
