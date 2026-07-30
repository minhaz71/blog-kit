<?php

namespace App\Services\Seo;

use App\Models\ProductImage;

/**
 * The image SEO rulebook — one source of truth used by the AI writer's
 * prompt, upload-time helpers, and the lint shown in the admin gallery.
 *
 * Distilled from Google's image SEO guidelines and industry consensus
 * (Moz/Semrush/Yoast): descriptive alt under ~125 chars, no "image of"
 * prefixes, natural keyword use, descriptive hyphenated filenames, unique
 * text per image, captions that add buyer-relevant information.
 */
class ImageSeoRules
{
    public const ALT_MAX = 125;

    public const TITLE_MAX = 70;

    public const CAPTION_MAX = 160;

    /** Redundant openers screen readers already announce. */
    public const BANNED_PREFIXES = ['image of', 'picture of', 'photo of', 'graphic of', 'img of'];

    /** Filename patterns that scream "camera dump", not SEO. */
    public const JUNK_FILENAME = '/^(img|image|photo|dsc|screenshot|whatsapp|untitled|new|final)[-_ ]?\d*/i';

    /** The contract handed to the AI writer — keep in sync with lint(). */
    public const RULEBOOK = <<<'RULES'
IMAGE SEO RULES (mandatory):

ALT TEXT (ranks in Google Images AND is read by screen readers):
- Describe what the image actually shows, specifically: product name + the distinguishing attribute (flavor, color, angle, pack state). Example: "IQOS TEREA Amber carton of 200 heated tobacco sticks (10 packs), front view".
- 40-125 characters. Never empty for product images.
- NEVER start with "image of", "picture of", "photo of" — redundant.
- Include the product's main keyword ONCE, naturally. No keyword stuffing, no comma-separated keyword lists.
- Each image of the same product gets DIFFERENT alt text (front view / side of pack / sticks laid out), never copies.

TITLE (tooltip on hover — supplementary, not a ranking factor):
- Short human hint, max 70 characters: product name + short context ("IQOS TEREA Amber carton, 200 sticks").
- Not a duplicate of the alt text.

CAPTION (visible to buyers under/near the image):
- One sentence, max 160 characters, that adds PURCHASE-relevant information: what is shown, pack contents, or use context.
- Written for humans; keywords only where natural.

FILENAME:
- lowercase-words-separated-by-hyphens.ext, 3-6 descriptive words, product keyword included ("iqos-terea-amber-pack.jpg").
- Never camera names (IMG_2026.jpg), underscores, spaces, or meaningless numbers.
RULES;

    /**
     * Deterministic issues for one image — mirrors the rulebook.
     *
     * @return array<int, string>
     */
    public static function lint(ProductImage $image): array
    {
        $issues = [];
        $alt = trim((string) $image->alt);

        if ($alt === '') {
            $issues[] = 'Missing alt text.';
        } else {
            if (mb_strlen($alt) > self::ALT_MAX) {
                $issues[] = 'Alt text over '.self::ALT_MAX.' chars (screen readers truncate).';
            }

            foreach (self::BANNED_PREFIXES as $prefix) {
                if (str_starts_with(mb_strtolower($alt), $prefix)) {
                    $issues[] = "Alt starts with \"{$prefix}\" — redundant, describe the content directly.";
                    break;
                }
            }
        }

        if (trim((string) $image->title) === '') {
            $issues[] = 'Missing title (hover tooltip).';
        }

        $basename = pathinfo($image->path, PATHINFO_FILENAME);

        if (preg_match(self::JUNK_FILENAME, $basename)) {
            $issues[] = "Filename \"{$basename}\" is a camera/junk name — rename to a descriptive slug.";
        }

        return $issues;
    }

    /** SEO filename for an image: product slug + position, hyphenated. */
    public static function seoFilename(ProductImage $image, int $position = 0): string
    {
        $extension = strtolower(pathinfo($image->path, PATHINFO_EXTENSION)) ?: 'jpg';
        $slug = $image->product?->slug ?: 'product-'.$image->product_id;

        return $slug.($position > 0 ? '-'.($position + 1) : '').'.'.$extension;
    }

    /**
     * The permalink filename is defined ONCE at upload, from the original
     * file name: "terea kazakhstan amber.jpg" → "terea-kazakhstan-amber.jpg".
     * Never random. Collisions get deterministic -2/-3 suffixes.
     */
    public static function slugFromOriginalName(string $originalName, string $fallback = 'image'): string
    {
        $slug = \Illuminate\Support\Str::slug(pathinfo($originalName, PATHINFO_FILENAME));

        return $slug !== '' ? $slug : (\Illuminate\Support\Str::slug($fallback) ?: 'image');
    }

    /** First free "{directory}/{slug}.{ext}" path on the public disk. */
    public static function uniquePath(string $directory, string $slug, string $extension): string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $path = "{$directory}/{$slug}.{$extension}";
        $i = 2;

        while ($disk->exists($path)) {
            $path = "{$directory}/{$slug}-".$i++.".{$extension}";
        }

        return $path;
    }
}
