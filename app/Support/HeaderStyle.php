<?php

namespace App\Support;

/**
 * Header (top menu) design presets. Tokens drive the top-bar chrome, logo
 * placement, how the desktop nav is laid out and its active/hover accent.
 * The header partial renders from these while keeping ONE mobile drawer and
 * the retained (gated) store search/cart. Admin picks one in
 * Admin → Appearance → Header design. Theme-aware.
 */
class HeaderStyle
{
    public const DEFAULT = 'classic';

    /**
     * Token vocabulary:
     *   bar    : plain | shadow | bordered | brandstrip | dark
     *   logo   : left | center
     *   nav    : row2 (own full-width row) | inline (in the top bar) | drawer (hamburger only)
     *   accent : underline | pill | plain
     *   cta    : bool  (show the Subscribe button)
     *
     * @var array<string, array<string, mixed>>
     */
    public const STYLES = [
        'classic' => ['label' => 'Classic', 'description' => 'Logo left, centered nav on its own row, underline accent.', 'bar' => 'plain', 'logo' => 'left', 'nav' => 'row2', 'accent' => 'underline', 'cta' => true],
        'inline' => ['label' => 'Inline', 'description' => 'Logo left, nav inline on the right of the bar.', 'bar' => 'plain', 'logo' => 'left', 'nav' => 'inline', 'accent' => 'underline', 'cta' => true],
        'centered' => ['label' => 'Centered', 'description' => 'Logo centered, nav centered on a row below.', 'bar' => 'plain', 'logo' => 'center', 'nav' => 'row2', 'accent' => 'underline', 'cta' => false],
        'minimal' => ['label' => 'Minimal', 'description' => 'Just logo + a menu button; nav lives in the drawer.', 'bar' => 'plain', 'logo' => 'left', 'nav' => 'drawer', 'accent' => 'plain', 'cta' => true],
        'shadow' => ['label' => 'Floating', 'description' => 'White bar with a soft shadow, inline nav.', 'bar' => 'shadow', 'logo' => 'left', 'nav' => 'inline', 'accent' => 'plain', 'cta' => true],
        'bordered' => ['label' => 'Bordered', 'description' => 'Nav row framed by top and bottom rules.', 'bar' => 'bordered', 'logo' => 'left', 'nav' => 'row2', 'accent' => 'underline', 'cta' => true],
        'brandstrip' => ['label' => 'Brand strip', 'description' => 'Thin brand bar above a white header.', 'bar' => 'brandstrip', 'logo' => 'left', 'nav' => 'row2', 'accent' => 'underline', 'cta' => true],
        'pill' => ['label' => 'Pill nav', 'description' => 'Nav items as rounded pills.', 'bar' => 'plain', 'logo' => 'left', 'nav' => 'row2', 'accent' => 'pill', 'cta' => true],
        'split' => ['label' => 'Split', 'description' => 'Logo centered, nav inline to the left, actions right.', 'bar' => 'plain', 'logo' => 'center', 'nav' => 'inline', 'accent' => 'underline', 'cta' => false],
        'dark' => ['label' => 'Dark', 'description' => 'Dark header bar with a luminous brand CTA.', 'bar' => 'dark', 'logo' => 'left', 'nav' => 'inline', 'accent' => 'plain', 'cta' => true],
    ];

    public static function options(): array
    {
        return array_map(fn ($s) => $s['label'], self::STYLES);
    }

    public static function active(): string
    {
        try {
            $key = (string) setting('appearance.header_style', self::DEFAULT);
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
