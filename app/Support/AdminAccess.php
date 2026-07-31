<?php

namespace App\Support;

/**
 * Single source of truth for admin screen access. Every Filament resource
 * and page maps to one "access_*" permission; a role that lacks the
 * permission cannot see the item in navigation and gets a 403 on the URL
 * (enforced by App\Filament\Concerns\GatedByPermission). Super Admin bypasses
 * everything via the Gate::before in AdminPanelProvider.
 *
 * SystemUpdates is intentionally ABSENT — it keeps its own hard-coded
 * Super-Admin-only canAccess() (running updates is too destructive to
 * delegate through the checkbox matrix).
 */
class AdminAccess
{
    /** Nav-group display order for the role matrix. */
    public const GROUP_ORDER = ['Catalog', 'Sales', 'Customers', 'Marketing', 'Content', 'SEO', 'Network', 'Security', 'System'];

    /** class => [permission key, human label, nav group]. */
    public const SCREENS = [
        // Catalog
        \App\Filament\Resources\ProductResource::class => ['access_products', 'Products', 'Catalog'],
        \App\Filament\Resources\CategoryResource::class => ['access_categories', 'Categories', 'Catalog'],
        \App\Filament\Resources\BrandResource::class => ['access_brands', 'Brands', 'Catalog'],
        \App\Filament\Resources\TagResource::class => ['access_tags', 'Tags', 'Catalog'],
        \App\Filament\Resources\AttributeResource::class => ['access_attributes', 'Attributes', 'Catalog'],
        \App\Filament\Resources\AiImportBatchResource::class => ['access_ai_product_batches', 'AI product batches', 'Catalog'],
        \App\Filament\Pages\AiUsageDashboard::class => ['access_ai_usage', 'AI usage dashboard', 'Catalog'],
        \App\Filament\Resources\AiCallQueueResource::class => ['access_ai_queue', 'AI call queue', 'Catalog'],

        // Sales
        \App\Filament\Resources\OrderResource::class => ['access_orders', 'Orders', 'Sales'],
        \App\Filament\Resources\ShippingZoneResource::class => ['access_shipping_zones', 'Shipping zones', 'Sales'],
        \App\Filament\Resources\ShippingClassResource::class => ['access_shipping_classes', 'Shipping classes', 'Sales'],
        \App\Filament\Resources\TaxRateResource::class => ['access_tax_rates', 'Tax rates', 'Sales'],
        \App\Filament\Resources\PaymentMethodResource::class => ['access_payment_methods', 'Payment methods', 'Sales'],
        \App\Filament\Resources\PaymentRuleResource::class => ['access_payment_rules', 'Payment rules', 'Sales'],
        \App\Filament\Pages\PaymentSettings::class => ['access_payment_settings', 'Payment settings', 'Sales'],
        \App\Filament\Pages\CheckoutSettings::class => ['access_checkout_settings', 'Checkout settings', 'Sales'],

        // Customers
        \App\Filament\Resources\CustomerResource::class => ['access_customers', 'Customers', 'Customers'],
        \App\Filament\Resources\ReviewResource::class => ['access_reviews', 'Reviews', 'Customers'],

        // Marketing
        \App\Filament\Resources\CouponResource::class => ['access_coupons', 'Coupons', 'Marketing'],
        \App\Filament\Resources\SubscriberResource::class => ['access_subscribers', 'Subscribers', 'Marketing'],
        \App\Filament\Pages\AbandonedCarts::class => ['access_abandoned_carts', 'Abandoned carts', 'Marketing'],
        \App\Filament\Pages\AbandonedCartSettings::class => ['access_abandoned_cart_settings', 'Abandoned cart settings', 'Marketing'],

        // Content
        \App\Filament\Resources\PostResource::class => ['access_posts', 'Blog posts', 'Content'],
        \App\Filament\Resources\PostCategoryResource::class => ['access_post_categories', 'Blog categories', 'Content'],
        \App\Filament\Resources\PageResource::class => ['access_pages', 'Pages', 'Content'],
        \App\Filament\Resources\BlogTopicIdeaResource::class => ['access_blog_ideas', 'Blog ideas', 'Content'],
        \App\Filament\Resources\AiBlogBatchResource::class => ['access_ai_blog_batches', 'AI blog batches', 'Content'],
        \App\Filament\Resources\ContentBlockResource::class => ['access_content_blocks', 'Content blocks', 'Content'],
        \App\Filament\Resources\HomepageSectionResource::class => ['access_homepage_sections', 'Homepage sections', 'Content'],
        \App\Filament\Resources\ProductTemplateResource::class => ['access_product_templates', 'Product templates', 'Content'],
        \App\Filament\Resources\ContactMessageResource::class => ['access_contact_messages', 'Contact messages', 'Content'],
        \App\Filament\Resources\EmailTemplateResource::class => ['access_email_templates', 'Email templates', 'Content'],
        \App\Filament\Resources\EmailLogResource::class => ['access_email_logs', 'Email logs', 'Content'],
        \App\Filament\Pages\MediaLibrary::class => ['access_media_library', 'Media library', 'Content'],
        \App\Filament\Pages\NavigationSettings::class => ['access_navigation_settings', 'Navigation settings', 'Content'],
        \App\Filament\Pages\AppearanceSettings::class => ['access_appearance_settings', 'Appearance settings', 'Content'],
        \App\Filament\Pages\EmailSettings::class => ['access_email_settings', 'Email settings', 'Content'],
        \App\Filament\Pages\SearchSettings::class => ['access_search_settings', 'Search settings', 'Content'],
        \App\Filament\Pages\FindReplace::class => ['access_find_replace', 'Find & Replace', 'Content'],
        \App\Filament\Pages\SearchAnalytics::class => ['access_search_analytics', 'Search analytics', 'Content'],
        \App\Filament\Pages\WhatsAppSettings::class => ['access_whatsapp_settings', 'WhatsApp settings', 'Content'],

        // SEO
        \App\Filament\Pages\SeoSettings::class => ['access_seo_settings', 'SEO settings', 'SEO'],
        \App\Filament\Pages\SeoEditor::class => ['access_seo_editor', 'SEO editor', 'SEO'],
        \App\Filament\Resources\RedirectResource::class => ['access_redirects', 'Redirects', 'SEO'],
        \App\Filament\Resources\NotFoundLogResource::class => ['access_404_monitor', '404 monitor', 'SEO'],
        \App\Filament\Resources\BrokenLinkResource::class => ['access_broken_links', 'Broken links', 'SEO'],
        \App\Filament\Resources\CustomSchemaResource::class => ['access_custom_schema', 'Custom schema', 'SEO'],
        \App\Filament\Pages\LinkAgent::class => ['access_link_agent', 'Link agent', 'SEO'],
        \App\Filament\Pages\InternalLinksReport::class => ['access_internal_links', 'Internal links report', 'SEO'],
        \App\Filament\Pages\ImageSeoTools::class => ['access_image_seo', 'Image SEO tools', 'SEO'],
        \App\Filament\Pages\PageSpeedReport::class => ['access_pagespeed', 'PageSpeed report', 'SEO'],
        \App\Filament\Pages\SearchPerformance::class => ['access_search_performance', 'Search performance', 'SEO'],

        // Network (multisite) — additionally gated by the network module + role
        // in each screen's canAccess(); the permission still governs staff access.
        \App\Filament\Resources\ConnectedSiteResource::class => ['access_connected_sites', 'Connected sites', 'Network'],
        \App\Filament\Resources\NetworkPostResource::class => ['access_network_posts', "All sites' posts", 'Network'],
        \App\Filament\Pages\NetworkSettings::class => ['access_network_settings', 'Network settings', 'Network'],

        // Security
        \App\Filament\Pages\SecurityCenter::class => ['access_security_center', 'Security center', 'Security'],
        \App\Filament\Pages\SecuritySettings::class => ['access_security_settings', 'Security settings', 'Security'],
        \App\Filament\Resources\AuditLogResource::class => ['access_audit_logs', 'Audit logs', 'Security'],
        \App\Filament\Resources\FirewallLogResource::class => ['access_firewall_logs', 'Firewall logs', 'Security'],
        \App\Filament\Resources\BlockedIpResource::class => ['access_blocked_ips', 'Blocked IPs', 'Security'],
        \App\Filament\Resources\LoginAttemptResource::class => ['access_login_attempts', 'Login attempts', 'Security'],
        \App\Filament\Resources\FileScanResultResource::class => ['access_file_scans', 'File scan results', 'Security'],

        // System
        \App\Filament\Resources\StaffUserResource::class => ['access_staff_users', 'Staff users', 'System'],
        \App\Filament\Resources\RoleResource::class => ['access_roles', 'Roles & permissions', 'System'],
        \App\Filament\Resources\BackupResource::class => ['access_backups', 'Backups', 'System'],
        \App\Filament\Pages\ModuleSettings::class => ['access_modules', 'Modules', 'System'],
        \App\Filament\Pages\GeneralSettings::class => ['access_general_settings', 'General settings', 'System'],
        \App\Filament\Pages\AiSettings::class => ['access_ai_settings', 'AI settings', 'System'],
        \App\Filament\Pages\PerformanceSettings::class => ['access_performance_settings', 'Performance settings', 'System'],
        \App\Filament\Resources\ErrorLogResource::class => ['access_error_logs', 'Error log', 'System'],
    ];

    /**
     * access_* → the coarse "manage *" permission the screen's policy checks.
     * Granting a screen implies its action permission, so a custom role that
     * ticks e.g. "Products" can actually create/edit products (the existing
     * policies + frontend preview checks stay on the coarse permissions).
     * Screens with no policy (AI tools, settings pages, etc.) have no entry —
     * reachability from the trait is enough since their actions aren't
     * policy-gated.
     */
    public const IMPLIES = [
        'access_products' => 'manage products',
        'access_categories' => 'manage products',
        'access_brands' => 'manage products',
        'access_tags' => 'manage products',
        'access_attributes' => 'manage products',
        'access_orders' => 'manage orders',
        'access_shipping_zones' => 'manage shipping',
        'access_shipping_classes' => 'manage shipping',
        'access_tax_rates' => 'manage shipping',
        'access_customers' => 'manage customers',
        'access_staff_users' => 'manage customers',
        'access_subscribers' => 'manage customers',
        'access_reviews' => 'manage reviews',
        'access_coupons' => 'manage coupons',
        'access_posts' => 'manage content',
        'access_post_categories' => 'manage content',
        'access_pages' => 'manage content',
        'access_email_templates' => 'manage emails',
        'access_email_logs' => 'manage emails',
        'access_redirects' => 'manage seo',
        'access_404_monitor' => 'manage seo',
        'access_audit_logs' => 'manage security',
        'access_firewall_logs' => 'manage security',
        'access_blocked_ips' => 'manage security',
        'access_login_attempts' => 'manage security',
        'access_file_scans' => 'manage security',
        'access_backups' => 'manage settings',
    ];

    /** Expand a set of access_* keys to include the coarse permissions they imply. @return list<string> */
    public static function expand(array $accessKeys): array
    {
        $out = $accessKeys;

        foreach ($accessKeys as $key) {
            if (isset(self::IMPLIES[$key])) {
                $out[] = self::IMPLIES[$key];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Screens belonging to the optional ecommerce (store) module. Hidden from
     * navigation AND hard-blocked (403) on direct URL whenever
     * module_enabled('ecommerce') is false — see allows(). Shared screens that
     * the blog also uses are deliberately NOT listed: Tags (blog posts are
     * tagged), Subscribers (newsletter), and the AI usage dashboard / AI call
     * queue (the blog writer logs and queues through them too).
     *
     * @var list<class-string>
     */
    public const ECOMMERCE = [
        // Catalog
        \App\Filament\Resources\ProductResource::class,
        \App\Filament\Resources\CategoryResource::class,
        \App\Filament\Resources\BrandResource::class,
        \App\Filament\Resources\AttributeResource::class,
        \App\Filament\Resources\AiImportBatchResource::class,
        // Sales
        \App\Filament\Resources\OrderResource::class,
        \App\Filament\Resources\ShippingZoneResource::class,
        \App\Filament\Resources\ShippingClassResource::class,
        \App\Filament\Resources\TaxRateResource::class,
        \App\Filament\Resources\PaymentMethodResource::class,
        \App\Filament\Resources\PaymentRuleResource::class,
        \App\Filament\Pages\PaymentSettings::class,
        \App\Filament\Pages\CheckoutSettings::class,
        // Customers
        \App\Filament\Resources\CustomerResource::class,
        \App\Filament\Resources\ReviewResource::class,
        // Marketing
        \App\Filament\Resources\CouponResource::class,
        \App\Filament\Pages\AbandonedCarts::class,
        \App\Filament\Pages\AbandonedCartSettings::class,
        // Content
        \App\Filament\Resources\ProductTemplateResource::class,
    ];

    /** True when $class is part of the ecommerce module. */
    public static function isEcommerce(string $class): bool
    {
        return in_array($class, self::ECOMMERCE, true);
    }

    /** The permission key guarding $class, or null when the screen is ungated. */
    public static function permissionFor(string $class): ?string
    {
        return self::SCREENS[$class][0] ?? null;
    }

    /** True when the current user may open $class. Ungated screens are always allowed. */
    public static function allows(string $class): bool
    {
        // Retained-but-disabled ecommerce screens vanish from the admin (nav +
        // direct URL) until an admin re-enables the module in System → Modules.
        if (self::isEcommerce($class) && ! ecommerce_enabled()) {
            return false;
        }

        $permission = self::permissionFor($class);

        return $permission === null || (auth()->user()?->can($permission) ?? false);
    }

    /** All permission keys, deduped. @return list<string> */
    public static function allKeys(): array
    {
        return array_values(array_unique(array_map(fn ($s) => $s[0], self::SCREENS)));
    }

    /**
     * Permissions grouped for the role matrix / seeder, in nav-group order.
     *
     * @return array<string, array<int, array{key: string, label: string}>>
     */
    public static function groupedForMatrix(): array
    {
        $grouped = [];

        foreach (self::SCREENS as $screen) {
            [$key, $label, $group] = $screen;
            $grouped[$group][] = ['key' => $key, 'label' => $label];
        }

        // Order groups, and dedupe keys within a group (stable).
        $ordered = [];
        foreach (self::GROUP_ORDER as $group) {
            if (! isset($grouped[$group])) {
                continue;
            }
            $seen = [];
            foreach ($grouped[$group] as $item) {
                if (! isset($seen[$item['key']])) {
                    $seen[$item['key']] = true;
                    $ordered[$group][] = $item;
                }
            }
        }

        return $ordered;
    }

    /** Permission keys belonging to any of the given nav groups (for seeder defaults). @return list<string> */
    public static function keysForGroups(array $groups): array
    {
        $keys = [];
        foreach (self::SCREENS as $screen) {
            if (in_array($screen[2], $groups, true)) {
                $keys[] = $screen[0];
            }
        }

        return array_values(array_unique($keys));
    }
}
