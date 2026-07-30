<?php

namespace App\Support;

/**
 * A searchable index of everything in the admin — every navigable screen plus
 * the notable individual settings buried inside the settings pages. Powers the
 * "Find a setting" finder so the owner can type "noindex", "currency" or
 * "under construction" and jump straight to the right page instead of hunting
 * through menus.
 *
 * Menu entries are derived automatically from AdminAccess::SCREENS; settings
 * entries are a curated map of the fields worth finding, each pointing at its
 * page + the section it lives in. Access is respected: a user only ever sees
 * entries for screens they can open.
 */
class SettingsCatalog
{
    /**
     * Curated individual settings: title + search keywords + the page/section
     * where they live. `page` is a class in AdminAccess::SCREENS (group + URL
     * are looked up from there).
     */
    public const SETTINGS = [
        // General
        ['General settings', 'Store identity', 'site name store title brand', \App\Filament\Pages\GeneralSettings::class],
        ['Currency & locale', 'Locale & currency', 'currency symbol aed usd decimals timezone price format', \App\Filament\Pages\GeneralSettings::class],
        ['Selling locations / countries', 'Selling locations', 'countries sell to ship regions geo', \App\Filament\Pages\GeneralSettings::class],
        ['Support contact email & phone', 'Support contact', 'contact email phone support', \App\Filament\Pages\GeneralSettings::class],
        ['Maintenance mode (site under construction)', 'Site status', 'maintenance under construction coming soon development mode offline closed hide site', \App\Filament\Pages\GeneralSettings::class],

        // SEO — visibility & permalinks
        ['Discourage search engines (noindex)', 'Search engine visibility', 'noindex robots discourage hide from google indexing crawl block search engines development', \App\Filament\Pages\SeoSettings::class],
        ['Permalinks — product / category / blog URL base', 'Permalinks', 'permalink url slug base prefix product category blog change remove /product /category rewrite structure', \App\Filament\Pages\SeoSettings::class],
        ['Global SEO title & description defaults', 'Global SEO defaults', 'meta title description default template sitename', \App\Filament\Pages\SeoSettings::class],
        ['Homepage title & description', 'Homepage', 'homepage meta title description', \App\Filament\Pages\SeoSettings::class],
        ['Organization schema (name & logo)', 'Organization schema', 'organization schema logo brand json-ld', \App\Filament\Pages\SeoSettings::class],
        ['Social profile links', 'Social profiles', 'facebook instagram twitter x youtube social links', \App\Filament\Pages\SeoSettings::class],
        ['Local business / Google Business Profile schema', 'Local SEO', 'local business gbp address hours map geo location localbusiness', \App\Filament\Pages\SeoSettings::class],
        ['XML sitemap options', 'XML sitemap', 'sitemap xml products categories posts pages exclude images', \App\Filament\Pages\SeoSettings::class],
        ['Product feed (Google/Bing Merchant)', 'Product feed', 'feed merchant google shopping bing product feed', \App\Filament\Pages\SeoSettings::class],
        ['IndexNow instant indexing', 'Integrations', 'indexnow bing yandex instant indexing key', \App\Filament\Pages\SeoSettings::class],
        ['Search Console & GA4 (Google APIs)', 'Integrations', 'search console gsc ga4 analytics google service account api', \App\Filament\Pages\SeoSettings::class],
        ['PageSpeed API key', 'Integrations', 'pagespeed insights api key core web vitals', \App\Filament\Pages\SeoSettings::class],
        ['Product shipping & returns schema', 'Product schema: shipping & returns', 'shipping returns delivery schema offer return policy', \App\Filament\Pages\SeoSettings::class],
        ['robots.txt override', 'robots.txt', 'robots txt disallow crawl', \App\Filament\Pages\SeoSettings::class],

        // AI
        ['AI providers & API keys', null, 'ai openai anthropic claude gemini api key provider model llm', \App\Filament\Pages\AiSettings::class],
        ['Google Drive API key (image matching)', null, 'google drive api key images folder product photos', \App\Filament\Pages\AiSettings::class],

        // Payments
        ['Payment gateways & keys', null, 'payment stripe paypal tap checkout gateway keys card cod cash on delivery', \App\Filament\Pages\PaymentSettings::class],

        // Content-group settings
        ['Storefront search options', null, 'search live search autocomplete suggestions results', \App\Filament\Pages\SearchSettings::class],
        ['Appearance / theme & logo', null, 'appearance theme colors logo favicon branding design', \App\Filament\Pages\AppearanceSettings::class],
        ['Navigation menus', null, 'navigation menu header footer links', \App\Filament\Pages\NavigationSettings::class],
        ['Email / mail transport', null, 'email smtp gmail mail transport from address sending', \App\Filament\Pages\EmailSettings::class],
        ['WhatsApp settings', null, 'whatsapp chat contact number', \App\Filament\Pages\WhatsAppSettings::class],

        // System / performance / security
        ['Performance & caching', null, 'performance cache litespeed cloudflare page cache minify critical css', \App\Filament\Pages\PerformanceSettings::class],
        ['Security settings', null, 'security firewall 2fa two factor password policy captcha recaptcha', \App\Filament\Pages\SecuritySettings::class],
        ['Roles & permissions', null, 'roles permissions access control staff rbac', \App\Filament\Resources\RoleResource::class],
        ['Backups', null, 'backup restore download database files', \App\Filament\Resources\BackupResource::class],
    ];

    /**
     * All catalog entries the current user can access.
     *
     * @return list<array{type:string,title:string,group:string,page:?string,section:?string,url:string,keywords:string}>
     */
    public static function forUser(): array
    {
        $entries = [];

        // 1. Every navigable screen (auto — stays in sync with the menu).
        foreach (AdminAccess::SCREENS as $class => [$key, $label, $group]) {
            if (! class_exists($class) || ! AdminAccess::allows($class)) {
                continue;
            }

            $entries[] = [
                'type' => 'menu',
                'title' => $label,
                'group' => $group,
                'page' => null,
                'section' => null,
                'url' => self::url($class),
                'keywords' => $label,
            ];
        }

        // 2. Curated individual settings.
        foreach (self::SETTINGS as [$title, $section, $keywords, $pageClass]) {
            if (! class_exists($pageClass) || ! AdminAccess::allows($pageClass)) {
                continue;
            }

            $entries[] = [
                'type' => 'setting',
                'title' => $title,
                'group' => self::groupOf($pageClass),
                'page' => self::labelOf($pageClass),
                'section' => $section,
                'url' => self::url($pageClass),
                'keywords' => $title.' '.$keywords.' '.self::labelOf($pageClass),
            ];
        }

        return $entries;
    }

    /**
     * Rank catalog entries against a query (all whitespace tokens must match
     * the title/keywords/location). Empty query returns everything.
     *
     * @return list<array<string,mixed>>
     */
    public static function search(string $query): array
    {
        $entries = self::forUser();
        $query = trim($query);

        if ($query === '') {
            return $entries;
        }

        $tokens = preg_split('/\s+/', mb_strtolower($query)) ?: [];

        return array_values(array_filter($entries, function (array $e) use ($tokens): bool {
            $haystack = mb_strtolower($e['keywords'].' '.$e['group'].' '.($e['page'] ?? '').' '.($e['section'] ?? ''));

            foreach ($tokens as $token) {
                if ($token !== '' && ! str_contains($haystack, $token)) {
                    return false;
                }
            }

            return true;
        }));
    }

    protected static function url(string $class): string
    {
        try {
            return $class::getUrl();
        } catch (\Throwable) {
            return '#';
        }
    }

    protected static function groupOf(string $class): string
    {
        return AdminAccess::SCREENS[$class][2] ?? 'System';
    }

    protected static function labelOf(string $class): string
    {
        return AdminAccess::SCREENS[$class][1] ?? class_basename($class);
    }
}
