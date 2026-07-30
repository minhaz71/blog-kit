<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * One source of truth for the store's brand identity (name, logo, colours,
 * contact, social links) shared by the order emails and the PDF invoice, so
 * both look consistent and pick up admin settings automatically.
 *
 * Reads the SAME setting keys the storefront uses:
 *   - name:    general.site_name → general.store_name → app.name
 *   - logo:    navigation.logo (public disk, branding/ dir)
 *   - colour:  appearance.primary_color / primary_hover_color
 *   - contact: general.contact_email/phone, navigation.footer_*
 *   - social:  seo.social_*
 */
class StoreBranding
{
    /** Sensible teal fallback matching the storefront when no brand colour is set. */
    public const DEFAULT_BRAND = '#0d9488';

    protected static ?array $cache = null;

    /** Everything the templates need, memoised for the request. */
    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $brand = self::color();

        return static::$cache = [
            'name' => self::name(),
            'url' => rtrim((string) config('app.url'), '/'),
            'logo_url' => self::logoUrl(),
            'logo_data_uri' => self::logoDataUri(),
            'brand' => $brand,
            'brand_dark' => self::colorDark($brand),
            'phone' => self::phone(),
            'email' => self::email(),
            'address_lines' => self::addressLines(),
            'hours' => (string) setting('navigation.footer_hours', ''),
            'socials' => self::socials(),
        ];
    }

    /**
     * Branding for the ORDER EMAILS, layering the admin's email-specific
     * overrides (Email settings → Email branding) on top of the store
     * defaults: a dedicated email logo, header colour, header text colour and
     * footer text. Anything left blank falls back to the site branding.
     */
    public static function emailBranding(): array
    {
        $out = self::all();

        $logo = trim((string) setting('emails.email_logo', ''));
        if ($logo !== '') {
            $out['logo_url'] = Storage::disk('public')->url(ltrim($logo, '/'));
        }

        $header = trim((string) setting('emails.email_header_color', ''));
        if (self::isHex($header)) {
            $out['brand'] = $header;
            $out['brand_dark'] = self::darken($header, 0.16);
        }

        $textColor = trim((string) setting('emails.email_header_text_color', ''));
        $out['header_text_color'] = self::isHex($textColor) ? $textColor : '#ffffff';

        $out['footer_text'] = trim((string) setting('emails.email_footer_text', ''));

        return $out;
    }

    public static function name(): string
    {
        return (string) setting('general.site_name', setting('general.store_name', config('app.name')));
    }

    /** Raw stored logo path on the public disk, or null. */
    public static function logoPath(): ?string
    {
        $path = setting('navigation.logo');

        return $path ? ltrim((string) $path, '/') : null;
    }

    /** Absolute logo URL for the email (remote-fetched by mail clients). */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Custom favicon URL (Admin → Appearance), or null to use the static default. */
    public static function faviconUrl(): ?string
    {
        $path = setting('appearance.favicon');

        return $path ? Storage::disk('public')->url(ltrim((string) $path, '/')) : null;
    }

    /**
     * Logo as a base64 data URI for the PDF. dompdf can't reliably fetch
     * remote images, so we embed the local file directly. Null if missing
     * or unreadable (the invoice then falls back to the store name).
     */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                return null;
            }
            $mime = $disk->mimeType($path) ?: 'image/png';
            // dompdf renders PNG/JPEG/GIF, not WebP/SVG.
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'], true)) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function color(): string
    {
        $c = trim((string) setting('appearance.primary_color', ''));

        return self::isHex($c) ? $c : self::DEFAULT_BRAND;
    }

    /** A darker shade for gradients/hovers — admin value if set, else computed. */
    public static function colorDark(?string $brand = null): string
    {
        $brand ??= self::color();
        $hover = trim((string) setting('appearance.primary_hover_color', ''));

        return self::isHex($hover) ? $hover : self::darken($brand, 0.16);
    }

    public static function phone(): string
    {
        return (string) (setting('navigation.footer_phone') ?: setting('general.contact_phone', ''));
    }

    /** Best customer-facing support email. */
    public static function email(): string
    {
        return (string) (
            setting('navigation.footer_email')
            ?: setting('general.contact_email')
            ?: setting('emails.from_email', '')
        );
    }

    /** Address as trimmed, non-empty lines (footer block first, then SEO local business). */
    public static function addressLines(): array
    {
        $footer = trim((string) setting('navigation.footer_address', ''));
        if ($footer !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $footer))));
        }

        $parts = array_filter([
            setting('seo.local_business_address'),
            trim(implode(', ', array_filter([
                setting('seo.local_business_city'),
                setting('seo.local_business_region'),
                setting('seo.local_business_postal_code'),
            ])), ', '),
            setting('seo.local_business_country'),
        ], fn ($v) => trim((string) $v) !== '');

        return array_values(array_map(fn ($v) => trim((string) $v), $parts));
    }

    /** [['label','url','initial'], …] for the social icons row. */
    public static function socials(): array
    {
        $map = [
            'Facebook' => 'seo.social_facebook',
            'Instagram' => 'seo.social_instagram',
            'X' => 'seo.social_twitter',
            'YouTube' => 'seo.social_youtube',
        ];

        $out = [];
        foreach ($map as $label => $key) {
            $url = trim((string) setting($key, ''));
            if ($url !== '') {
                $out[] = ['label' => $label, 'url' => $url, 'initial' => $label[0]];
            }
        }

        return $out;
    }

    // ── helpers ────────────────────────────────────────────────────

    protected static function isHex(string $c): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c);
    }

    /** Darken a #rrggbb hex by $amount (0–1). */
    protected static function darken(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#'.$hex;
        }
        $rgb = array_map(
            fn ($p) => max(0, min(255, (int) round(hexdec($p) * (1 - $amount)))),
            str_split($hex, 2),
        );

        return sprintf('#%02x%02x%02x', ...$rgb);
    }
}
