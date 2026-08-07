<?php

namespace App\Support;

/**
 * Footer design presets. Tokens drive the overall layout, background tone and
 * whether the newsletter block shows. The footer partial renders from these
 * while keeping the admin-configurable columns/contact/newsletter. Admin picks
 * one in Admin → Appearance → Footer design. Theme-aware.
 */
class FooterStyle
{
    public const DEFAULT = 'columns';

    /**
     * Token vocabulary:
     *   layout : columns | minimal | centered | cta | mega
     *   bg     : light | soft | dark | brand
     *   rule   : none | brand   (top accent rule)
     *   newsletter : bool  (show the signup block; cta layout always shows it big)
     *
     * @var array<string, array<string, mixed>>
     */
    public const STYLES = [
        'columns' => ['label' => 'Columns', 'description' => 'Classic multi-column footer on a light background.', 'layout' => 'columns', 'bg' => 'light', 'rule' => 'none', 'newsletter' => true],
        'minimal' => ['label' => 'Minimal', 'description' => 'One slim row: brand, a few links, copyright.', 'layout' => 'minimal', 'bg' => 'light', 'rule' => 'none', 'newsletter' => false],
        'centered' => ['label' => 'Centered', 'description' => 'Everything stacked and centered.', 'layout' => 'centered', 'bg' => 'light', 'rule' => 'none', 'newsletter' => true],
        'soft' => ['label' => 'Soft tint', 'description' => 'Columns on a soft brand-tinted background.', 'layout' => 'columns', 'bg' => 'soft', 'rule' => 'brand', 'newsletter' => true],
        'bordered' => ['label' => 'Bordered', 'description' => 'Light columns with a brand accent rule on top.', 'layout' => 'columns', 'bg' => 'light', 'rule' => 'brand', 'newsletter' => true],
        'dark' => ['label' => 'Dark', 'description' => 'Dark footer with light text and brand links.', 'layout' => 'columns', 'bg' => 'dark', 'rule' => 'none', 'newsletter' => true],
        'brand' => ['label' => 'Brand', 'description' => 'Full brand-colored footer.', 'layout' => 'columns', 'bg' => 'brand', 'rule' => 'none', 'newsletter' => true],
        'cta' => ['label' => 'Big CTA', 'description' => 'Large brand newsletter band above a slim link row.', 'layout' => 'cta', 'bg' => 'light', 'rule' => 'none', 'newsletter' => true],
        'compact' => ['label' => 'Compact', 'description' => 'Two tight columns, minimal chrome.', 'layout' => 'minimal', 'bg' => 'soft', 'rule' => 'none', 'newsletter' => false],
        'mega' => ['label' => 'Mega', 'description' => 'Wide multi-column footer with a big brand wordmark.', 'layout' => 'mega', 'bg' => 'dark', 'rule' => 'none', 'newsletter' => true],
    ];

    public static function options(): array
    {
        return array_map(fn ($s) => $s['label'], self::STYLES);
    }

    public static function active(): string
    {
        try {
            $key = (string) setting('appearance.footer_style', self::DEFAULT);
        } catch (\Throwable) {
            $key = self::DEFAULT;
        }

        return isset(self::STYLES[$key]) ? $key : self::DEFAULT;
    }

    public static function tokens(): array
    {
        return self::STYLES[self::active()] ?? self::STYLES[self::DEFAULT];
    }
}
