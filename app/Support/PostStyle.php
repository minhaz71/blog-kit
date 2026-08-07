<?php

namespace App\Support;

/**
 * Single-post layout styles. A "style" is a set of layout tokens the single
 * blog-post template renders from, so the same content can be presented in ten
 * very different ways. The admin picks a site-wide default (Admin → Appearance)
 * and can override it per post (PostResource), so different posts can use
 * different styles — "which style to show where".
 *
 * All styles read the active THEME's brand color, so a style + a theme combine
 * (e.g. "Editorial" in "Violet").
 */
class PostStyle
{
    public const DEFAULT = 'classic';

    /**
     * Layout token vocabulary:
     *   layout : centered | sidebar | heroFull | heroBand | split
     *   width  : narrow (max-w-3xl) | wide (max-w-4xl)
     *   font   : sans | serif
     *   title  : lg | xl | display
     *   toc    : inline | sidebar | none
     *   frame  : none | card
     *   dropcap: bool
     *   rules  : bool  (rule dividers between header/body — newspaper feel)
     *
     * @var array<string, array<string, mixed>>
     */
    public const STYLES = [
        'classic' => [
            'label' => 'Classic', 'description' => 'Centered column, serif headings, image on top, drop cap.',
            'layout' => 'centered', 'width' => 'narrow', 'font' => 'serif', 'title' => 'lg',
            'toc' => 'inline', 'frame' => 'none', 'dropcap' => true, 'rules' => false,
        ],
        'minimal' => [
            'label' => 'Minimal', 'description' => 'No hero image, airy sans-serif, pure focus on the words.',
            'layout' => 'centered', 'width' => 'narrow', 'font' => 'sans', 'title' => 'lg',
            'toc' => 'none', 'frame' => 'none', 'dropcap' => false, 'rules' => false,
            'noHero' => true,
        ],
        'magazine' => [
            'label' => 'Magazine', 'description' => 'Full-bleed cover image with the title overlaid, boxed body.',
            'layout' => 'heroFull', 'width' => 'narrow', 'font' => 'serif', 'title' => 'xl',
            'toc' => 'inline', 'frame' => 'none', 'dropcap' => true, 'rules' => false,
        ],
        'editorial' => [
            'label' => 'Editorial', 'description' => 'Sticky sidebar with author + contents, article on the right.',
            'layout' => 'sidebar', 'width' => 'wide', 'font' => 'sans', 'title' => 'lg',
            'toc' => 'sidebar', 'frame' => 'none', 'dropcap' => false, 'rules' => false,
        ],
        'bold' => [
            'label' => 'Bold', 'description' => 'Brand gradient banner header, oversized display title.',
            'layout' => 'heroBand', 'width' => 'narrow', 'font' => 'sans', 'title' => 'display',
            'toc' => 'inline', 'frame' => 'none', 'dropcap' => false, 'rules' => false,
        ],
        'feature' => [
            'label' => 'Feature', 'description' => 'Wide long-form layout, large cover image, generous type.',
            'layout' => 'centered', 'width' => 'wide', 'font' => 'serif', 'title' => 'xl',
            'toc' => 'inline', 'frame' => 'none', 'dropcap' => true, 'rules' => false,
        ],
        'docs' => [
            'label' => 'Documentation', 'description' => 'Sticky contents sidebar, tight sans-serif, no cover image.',
            'layout' => 'sidebar', 'width' => 'wide', 'font' => 'sans', 'title' => 'lg',
            'toc' => 'sidebar', 'frame' => 'none', 'dropcap' => false, 'rules' => false,
            'noHero' => true,
        ],
        'card' => [
            'label' => 'Card', 'description' => 'Article sits in an elevated white card on a tinted page.',
            'layout' => 'centered', 'width' => 'narrow', 'font' => 'sans', 'title' => 'lg',
            'toc' => 'inline', 'frame' => 'card', 'dropcap' => false, 'rules' => false,
        ],
        'newspaper' => [
            'label' => 'Newspaper', 'description' => 'Serif, drop cap, rule dividers and a dense byline.',
            'layout' => 'centered', 'width' => 'narrow', 'font' => 'serif', 'title' => 'lg',
            'toc' => 'none', 'frame' => 'none', 'dropcap' => true, 'rules' => true,
        ],
        'split' => [
            'label' => 'Split hero', 'description' => 'Brand panel with the title beside the cover image, then the body.',
            'layout' => 'split', 'width' => 'narrow', 'font' => 'sans', 'title' => 'xl',
            'toc' => 'inline', 'frame' => 'none', 'dropcap' => false, 'rules' => false,
        ],
    ];

    /** [key => label] for a settings/resource dropdown. */
    public static function options(): array
    {
        return array_map(fn ($s) => $s['label'], self::STYLES);
    }

    /** The site-wide default style key (Appearance settings). */
    public static function siteDefault(): string
    {
        try {
            $key = (string) setting('appearance.blog_post_style', self::DEFAULT);
        } catch (\Throwable) {
            $key = self::DEFAULT;
        }

        return isset(self::STYLES[$key]) ? $key : self::DEFAULT;
    }

    /**
     * The style key to render for a given post: the post's own override wins,
     * else the site default.
     */
    public static function resolveKey(?string $override): string
    {
        $override = trim((string) $override);

        return $override !== '' && isset(self::STYLES[$override]) ? $override : self::siteDefault();
    }

    /** Resolved tokens for a style key (falls back to the default). */
    public static function tokens(string $key): array
    {
        return self::STYLES[$key] ?? self::STYLES[self::DEFAULT];
    }
}
