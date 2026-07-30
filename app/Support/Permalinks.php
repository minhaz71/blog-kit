<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Configurable storefront URL bases for products and categories.
 *
 * Defaults preserve the original hard-coded structure exactly
 * (/product/{slug}, /category/{slug}). The owner may rename a base
 * (/product → /shop) or clear it for root-level URLs (/{slug}) from
 * Admin → SEO settings. URL generation goes through here — never through a
 * named route — so it always reflects the current base, even at root where no
 * prefixed route exists.
 */
class Permalinks
{
    public const DEFAULTS = [
        'product' => 'product',
        'category' => 'category',
        'post' => 'blog',
    ];

    /** Types whose base must stay non-empty (root-level would break sub-routes). */
    public const REQUIRES_BASE = ['post'];

    /**
     * First path segments that must never be used as a base — they would
     * shadow real routes/endpoints. (The type defaults product/category/blog
     * are allowed for their own field; cross-collisions are caught by the
     * uniqueness check.)
     */
    public const RESERVED = [
        'admin', 'api', 'livewire', 'cart', 'checkout', 'my-account', 'account',
        'search', 'shop', 'feeds', 'robots.txt', 'sitemap', 'storage',
        'build', 'wishlist', 'products', 'reviews', 'login', 'register',
        'password', 'forgot-password', 'reset-password', 'logout', 'two-factor',
        'verify-email', 'newsletter', 'contact', 'hmmail', 'webhooks',
        '.well-known', 'custom-css', 'llms.txt',
    ];

    /** Setting key per type — the blog type is stored as "blog_base". */
    protected const KEYS = [
        'product' => 'product_base',
        'category' => 'category_base',
        'post' => 'blog_base',
    ];

    /** Current base segment for a type ('product'|'category'|'post'), '' = root-level. */
    public static function base(string $type): string
    {
        $default = self::DEFAULTS[$type] ?? '';
        $key = self::KEYS[$type] ?? $type.'_base';
        $value = setting("seo.{$key}", $default);

        // A missing setting falls back to the default; an explicit '' is honored.
        return self::normalize($value === null ? $default : (string) $value);
    }

    public static function product(string $slug): string
    {
        return self::build(self::base('product'), $slug);
    }

    public static function category(string $slug): string
    {
        return self::build(self::base('category'), $slug);
    }

    /** Blog base always resolves to a non-empty prefix (default "blog"). */
    public static function blogBase(): string
    {
        return self::base('post') ?: self::DEFAULTS['post'];
    }

    /** Build an absolute URL from a base + slug ('' base → /{slug}). */
    protected static function build(string $base, string $slug): string
    {
        $slug = trim($slug, '/');

        return url($base === '' ? "/{$slug}" : "/{$base}/{$slug}");
    }

    /** Lowercase, trimmed single segment (strips slashes/spaces). */
    public static function normalize(?string $value): string
    {
        return trim(Str::lower(trim((string) $value)), '/');
    }

    /**
     * Validate a candidate base. Returns an error string, or null when valid.
     *
     * @param  array<int,string>  $others  other types' bases, for uniqueness
     */
    public static function validate(string $value, array $others = [], bool $allowEmpty = true): ?string
    {
        $value = self::normalize($value);

        if ($value === '') {
            return $allowEmpty ? null : 'This base can’t be empty — enter a word like “blog”.';
        }

        if (! preg_match('/^[a-z0-9-]+$/', $value)) {
            return 'Use only lowercase letters, numbers and hyphens (a single URL segment, no slashes).';
        }

        if (in_array($value, self::RESERVED, true)) {
            return "\"{$value}\" is reserved by another part of the site — pick a different word.";
        }

        foreach ($others as $other) {
            if ($value === self::normalize($other)) {
                return 'Each section (product, category, blog) needs a different base.';
            }
        }

        return null;
    }
}
