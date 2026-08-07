<?php

namespace App\Support;

/**
 * Home page design presets. Each preset picks a HERO treatment plus page-level
 * treatment (background, container width, section spacing/rhythm, whether
 * section cards get chrome). The home view and hero partial render from these
 * tokens, so the admin can switch the whole homepage look in
 * Admin → Appearance → Home page design. Theme-aware.
 */
class HomeStyle
{
    public const DEFAULT = 'editorial';

    /**
     * Token vocabulary:
     *   hero    : gradient | image | split | minimal | centered | bold | side | boxed | banner | classic
     *   bg      : white | tint | soft | dark
     *   width   : contained (max-w-7xl) | wide (max-w-screen-2xl) | narrow (max-w-5xl)
     *   gap     : cozy | roomy | tight
     *   cards   : plain | framed | soft
     *   heroAlign : left | center
     *
     * @var array<string, array<string, mixed>>
     */
    public const STYLES = [
        'editorial' => ['label' => 'Editorial', 'description' => 'Rounded brand gradient hero, roomy contained layout.', 'hero' => 'gradient', 'bg' => 'white', 'width' => 'contained', 'gap' => 'roomy', 'cards' => 'plain', 'heroAlign' => 'left'],
        'centered' => ['label' => 'Centered', 'description' => 'Centered hero headline, symmetrical layout.', 'hero' => 'centered', 'bg' => 'white', 'width' => 'contained', 'gap' => 'roomy', 'cards' => 'plain', 'heroAlign' => 'center'],
        'cover' => ['label' => 'Cover image', 'description' => 'Full-width cover photo hero with overlaid title.', 'hero' => 'image', 'bg' => 'white', 'width' => 'contained', 'gap' => 'roomy', 'cards' => 'plain', 'heroAlign' => 'left'],
        'split' => ['label' => 'Split', 'description' => 'Hero text beside an image / brand panel.', 'hero' => 'split', 'bg' => 'white', 'width' => 'contained', 'gap' => 'roomy', 'cards' => 'plain', 'heroAlign' => 'left'],
        'minimal' => ['label' => 'Minimal', 'description' => 'Plain text hero, lots of whitespace, no chrome.', 'hero' => 'minimal', 'bg' => 'white', 'width' => 'narrow', 'gap' => 'roomy', 'cards' => 'plain', 'heroAlign' => 'left'],
        'bold' => ['label' => 'Bold', 'description' => 'Oversized display hero on a full brand band.', 'hero' => 'bold', 'bg' => 'white', 'width' => 'contained', 'gap' => 'roomy', 'cards' => 'plain', 'heroAlign' => 'left'],
        'magazine' => ['label' => 'Magazine', 'description' => 'Wide grid, framed section cards, tinted page.', 'hero' => 'banner', 'bg' => 'tint', 'width' => 'wide', 'gap' => 'cozy', 'cards' => 'framed', 'heroAlign' => 'left'],
        'sidebar' => ['label' => 'Side hero', 'description' => 'Hero sits to one side with a stat/brand strip.', 'hero' => 'side', 'bg' => 'white', 'width' => 'contained', 'gap' => 'roomy', 'cards' => 'plain', 'heroAlign' => 'left'],
        'boxed' => ['label' => 'Boxed', 'description' => 'Everything inside soft rounded cards on a tinted page.', 'hero' => 'boxed', 'bg' => 'soft', 'width' => 'contained', 'gap' => 'cozy', 'cards' => 'soft', 'heroAlign' => 'left'],
        'dark' => ['label' => 'Dark', 'description' => 'Dark page with a luminous brand hero.', 'hero' => 'gradient', 'bg' => 'dark', 'width' => 'contained', 'gap' => 'roomy', 'cards' => 'framed', 'heroAlign' => 'left'],
    ];

    public static function options(): array
    {
        return array_map(fn ($s) => $s['label'], self::STYLES);
    }

    public static function active(): string
    {
        try {
            $key = (string) setting('appearance.home_style', self::DEFAULT);
        } catch (\Throwable) {
            $key = self::DEFAULT;
        }

        return isset(self::STYLES[$key]) ? $key : self::DEFAULT;
    }

    public static function tokens(?string $key = null): array
    {
        $key = $key && isset(self::STYLES[$key]) ? $key : self::active();

        return self::STYLES[$key] ?? self::STYLES[self::DEFAULT];
    }
}
