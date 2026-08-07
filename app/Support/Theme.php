<?php

namespace App\Support;

/**
 * Theme system. The core BlogKit codebase renders every accent through a small
 * set of CSS custom properties (--brand, --brand-dark, --brand-strong, …).
 * A "theme" is just a named set of values for those properties, so the same
 * codebase can power many sites — each install (and each spoke in the network)
 * picks its own theme in Admin → Appearance, stored as the "appearance.theme"
 * setting. Admins can further override the brand color / radius on top.
 *
 * The active theme compiles to a content-hashed static CSS file on the public
 * disk (see theme_css_href()), injected in the <head> — no inline <style>.
 */
class Theme
{
    public const DEFAULT = 'teal';

    /**
     * Built-in presets. Each maps the brand custom properties:
     *   brand       — primary accent (buttons, badges)  ≈ Tailwind 600
     *   dark        — hover / active                     ≈ 700
     *   strong      — brand text on a white background   ≈ 700/800 (readable)
     *   tint        — light brand background (badges)    ≈ 50
     *   fg          — text/icon color on a brand fill
     *   grad_from   — hero / newsletter gradient start   ≈ 800
     *   grad_to     — hero / newsletter gradient end     ≈ 500/600
     *
     * @var array<string, array<string, string>>
     */
    public const PRESETS = [
        'teal' => ['label' => 'Teal (default)', 'brand' => '#0d9488', 'dark' => '#0f766e', 'strong' => '#0f766e', 'tint' => '#f0fdfa', 'fg' => '#ffffff', 'grad_from' => '#115e59', 'grad_to' => '#059669'],
        'emerald' => ['label' => 'Emerald', 'brand' => '#059669', 'dark' => '#047857', 'strong' => '#047857', 'tint' => '#ecfdf5', 'fg' => '#ffffff', 'grad_from' => '#065f46', 'grad_to' => '#10b981'],
        'indigo' => ['label' => 'Indigo', 'brand' => '#4f46e5', 'dark' => '#4338ca', 'strong' => '#4338ca', 'tint' => '#eef2ff', 'fg' => '#ffffff', 'grad_from' => '#3730a3', 'grad_to' => '#6366f1'],
        'violet' => ['label' => 'Violet', 'brand' => '#7c3aed', 'dark' => '#6d28d9', 'strong' => '#6d28d9', 'tint' => '#f5f3ff', 'fg' => '#ffffff', 'grad_from' => '#5b21b6', 'grad_to' => '#8b5cf6'],
        'blue' => ['label' => 'Ocean blue', 'brand' => '#2563eb', 'dark' => '#1d4ed8', 'strong' => '#1d4ed8', 'tint' => '#eff6ff', 'fg' => '#ffffff', 'grad_from' => '#1e3a8a', 'grad_to' => '#3b82f6'],
        'sky' => ['label' => 'Sky', 'brand' => '#0284c7', 'dark' => '#0369a1', 'strong' => '#0369a1', 'tint' => '#f0f9ff', 'fg' => '#ffffff', 'grad_from' => '#075985', 'grad_to' => '#38bdf8'],
        'rose' => ['label' => 'Rose', 'brand' => '#e11d48', 'dark' => '#be123c', 'strong' => '#be123c', 'tint' => '#fff1f2', 'fg' => '#ffffff', 'grad_from' => '#9f1239', 'grad_to' => '#fb7185'],
        'amber' => ['label' => 'Amber', 'brand' => '#d97706', 'dark' => '#b45309', 'strong' => '#b45309', 'tint' => '#fffbeb', 'fg' => '#ffffff', 'grad_from' => '#92400e', 'grad_to' => '#f59e0b'],
        'slate' => ['label' => 'Slate (monochrome)', 'brand' => '#334155', 'dark' => '#1e293b', 'strong' => '#1e293b', 'tint' => '#f8fafc', 'fg' => '#ffffff', 'grad_from' => '#0f172a', 'grad_to' => '#475569'],
    ];

    /** The active theme key, from settings, falling back to the default. */
    public static function active(): string
    {
        try {
            $key = (string) setting('appearance.theme', self::DEFAULT);
        } catch (\Throwable) {
            $key = self::DEFAULT;
        }

        return isset(self::PRESETS[$key]) ? $key : self::DEFAULT;
    }

    /** [key => label] for a settings dropdown. */
    public static function options(): array
    {
        return array_map(fn ($p) => $p['label'], self::PRESETS);
    }

    /**
     * Resolved brand tokens for the active theme, with admin overrides applied
     * (a custom primary color / hover color from Appearance settings win).
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        $tokens = self::PRESETS[self::active()];

        try {
            if ($brand = trim((string) setting('appearance.primary_color', ''))) {
                $tokens['brand'] = $brand;
                $tokens['strong'] = $brand;
            }
            if ($hover = trim((string) setting('appearance.primary_hover_color', ''))) {
                $tokens['dark'] = $hover;
            }
        } catch (\Throwable) {
            // Settings not ready (early boot) — use the preset as-is.
        }

        return $tokens;
    }

    /** The `:root { --brand: … }` custom-property block for the active theme. */
    public static function rootVariables(): string
    {
        $t = self::tokens();

        return ':root{'
            ."--brand:{$t['brand']};"
            ."--brand-dark:{$t['dark']};"
            ."--brand-strong:{$t['strong']};"
            ."--brand-tint:{$t['tint']};"
            ."--brand-fg:{$t['fg']};"
            ."--brand-grad-from:{$t['grad_from']};"
            ."--brand-grad-to:{$t['grad_to']};"
            .'}';
    }
}
