<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'general.site_name' => config('app.name', 'Hemdox Blog Kit'),
            'general.site_tagline' => 'Fast, secure, SEO-friendly publishing.',
            'general.currency' => 'USD',
            'general.currency_symbol' => '$',
            'general.timezone' => 'UTC',
            'general.contact_email' => 'support@example.com',
            'general.contact_phone' => '+1-000-000-0000',

            'seo.site_title' => 'Hemdox Blog Kit',
            'seo.title_separator' => '|',
            'seo.default_title_format' => '{title} | Hemdox Blog Kit',
            'seo.default_description' => 'Fresh articles, guides and stories — published fast and optimized for search and AI.',
            'seo.default_og_image' => null,
            'seo.homepage_title' => 'Hemdox Blog Kit | Fast, SEO-friendly blogging',
            'seo.homepage_description' => 'A modern, fast blogging platform built for speed, SEO and AI answer engines.',
            'seo.organization_name' => 'Hemdox Blog Kit',
            'seo.organization_logo' => null,
            'seo.social_facebook' => null,
            'seo.social_instagram' => null,
            'seo.social_twitter' => null,
            'seo.social_youtube' => null,
            'seo.robots_txt' => null,

            // NOTE: the ecommerce module default is intentionally NOT seeded
            // here. module_enabled('ecommerce') falls back to
            // config/blogkit.php → BLOGKIT_ECOMMERCE_ENABLED (OFF for a blog),
            // and an admin's save in Admin → System → Modules writes the
            // setting only when they explicitly toggle it. Seeding a row would
            // pin the value and shadow the config/env default.

            'security.firewall_enabled' => true,
            'security.max_login_attempts' => 5,
            'security.lockout_minutes' => 15,
            'security.require_strong_password' => true,
            'security.block_common_usernames' => true,
            'security.two_factor_enabled' => false,
            'security.recaptcha_enabled' => false,
            'security.recaptcha_site_key' => null,
            'security.recaptcha_secret_key' => null,

            'payments.stripe_enabled' => false,
            'payments.stripe_public_key' => null,
            'payments.stripe_secret_key' => null,
            'payments.stripe_webhook_secret' => null,
            'payments.paypal_enabled' => false,
            'payments.paypal_client_id' => null,
            'payments.paypal_client_secret' => null,
            'payments.paypal_mode' => 'sandbox',
            'payments.cod_enabled' => true,
            'payments.bank_transfer_enabled' => true,
            'payments.bank_transfer_instructions' => 'Please transfer to Bank XYZ, account 000-000-000.',

            'shipping.free_shipping_threshold' => 100,
            'shipping.default_country' => 'US',

            'emails.from_name' => config('app.name', 'Hemdox Blog Kit'),
            'emails.from_email' => config('mail.from.address', 'hello@example.com'),
            'emails.admin_recipient' => 'admin@example.com',
            'emails.smtp_host' => null,
            'emails.smtp_port' => 587,
            'emails.smtp_username' => null,
            'emails.smtp_password' => null,
            'emails.smtp_encryption' => 'tls',

            'performance.litespeed_cache_enabled' => true,
            'performance.public_cache_ttl' => 3600,
            'performance.image_webp_enabled' => true,
            'performance.image_lazy_load' => true,
            'performance.minify_html' => false,
        ];

        foreach ($defaults as $dot => $value) {
            [$group, $key] = explode('.', $dot, 2);
            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
