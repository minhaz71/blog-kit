<?php

namespace App\Support;

/**
 * Table-of-contents visual styles for single blog posts. Applied as a
 * `bd-toc--{key}` modifier class on the TOC nav (styled in resources/css/app.css).
 * The admin picks one in Admin → Appearance → Table of contents style.
 * Theme-aware: brand accents read the active color theme.
 */
class TocStyle
{
    public const DEFAULT = 'boxed';

    /** @var array<string, string> key => label */
    public const STYLES = [
        'boxed' => 'Boxed card (default)',
        'plain' => 'Plain list',
        'bordered' => 'Left accent bar',
        'numbered' => 'Numbered',
        'pills' => 'Pills',
        'brand' => 'Brand card',
        'underline' => 'Underlined rows',
        'compact' => 'Compact',
        'callout' => 'Callout',
        'ruled' => 'Top & bottom rules',
    ];

    public static function options(): array
    {
        return self::STYLES;
    }

    public static function active(): string
    {
        try {
            $key = (string) setting('appearance.toc_style', self::DEFAULT);
        } catch (\Throwable) {
            $key = self::DEFAULT;
        }

        return isset(self::STYLES[$key]) ? $key : self::DEFAULT;
    }
}
