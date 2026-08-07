<?php

namespace App\Support;

/**
 * Blog catalogue (index / listing / archive) design presets. The blog index
 * view renders the post list from these tokens, so the admin can switch the
 * whole catalogue look in Admin → Appearance → Blog catalogue design.
 * Theme-aware: accents read the active color theme.
 */
class BlogIndexStyle
{
    public const DEFAULT = 'grid';

    /**
     * Token vocabulary:
     *   layout   : grid | list | magazine | masonry | cards | minimal | compact | overlay | timeline | featured
     *   columns  : 2 | 3 | 4        (grid-family column count on desktop)
     *   feature  : bool             (spotlight the newest post at the top)
     *   image    : top | side | none | overlay
     *   filter   : pills | bar | none  (category filter presentation)
     *
     * @var array<string, array<string, mixed>>
     */
    public const STYLES = [
        'grid' => ['label' => 'Grid', 'description' => 'Even 3-column card grid with cover images.', 'layout' => 'grid', 'columns' => 3, 'feature' => false, 'image' => 'top', 'filter' => 'pills'],
        'featured' => ['label' => 'Featured + grid', 'description' => 'A large spotlight post over a grid of the rest.', 'layout' => 'featured', 'columns' => 3, 'feature' => true, 'image' => 'top', 'filter' => 'pills'],
        'list' => ['label' => 'List', 'description' => 'Single-column rows with a side thumbnail.', 'layout' => 'list', 'columns' => 1, 'feature' => false, 'image' => 'side', 'filter' => 'bar'],
        'magazine' => ['label' => 'Magazine', 'description' => 'Asymmetric editorial grid, mixed card sizes.', 'layout' => 'magazine', 'columns' => 3, 'feature' => true, 'image' => 'top', 'filter' => 'bar'],
        'cards' => ['label' => 'Bold cards', 'description' => 'Four-column compact cards with hover lift.', 'layout' => 'cards', 'columns' => 4, 'feature' => false, 'image' => 'top', 'filter' => 'pills'],
        'minimal' => ['label' => 'Minimal', 'description' => 'Text-only list, generous whitespace, no images.', 'layout' => 'minimal', 'columns' => 1, 'feature' => false, 'image' => 'none', 'filter' => 'pills'],
        'compact' => ['label' => 'Compact index', 'description' => 'Dense two-column rows for high-volume blogs.', 'layout' => 'compact', 'columns' => 2, 'feature' => false, 'image' => 'none', 'filter' => 'bar'],
        'overlay' => ['label' => 'Overlay', 'description' => 'Image cards with the title overlaid on the photo.', 'layout' => 'overlay', 'columns' => 3, 'feature' => false, 'image' => 'overlay', 'filter' => 'pills'],
        'timeline' => ['label' => 'Timeline', 'description' => 'Chronological single column with a brand rail.', 'layout' => 'timeline', 'columns' => 1, 'feature' => false, 'image' => 'side', 'filter' => 'bar'],
        'masonry' => ['label' => 'Masonry', 'description' => 'Staggered multi-column cards (CSS columns).', 'layout' => 'masonry', 'columns' => 3, 'feature' => false, 'image' => 'top', 'filter' => 'pills'],
    ];

    public static function options(): array
    {
        return array_map(fn ($s) => $s['label'], self::STYLES);
    }

    public static function active(): string
    {
        try {
            $key = (string) setting('appearance.blog_index_style', self::DEFAULT);
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
