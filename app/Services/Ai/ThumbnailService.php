<?php

namespace App\Services\Ai;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates a blog thumbnail from the article title (or a custom prompt) with
 * ONE image request, stores it on the public disk (content-addressed), and
 * sets it as the post's featured image + alt. No revision pass.
 */
class ThumbnailService
{
    public function __construct(protected ImageGenerator $images = new ImageGenerator) {}

    /** Named look-and-feel presets the admin can pick in AI settings. */
    public const STYLE_PRESETS = [
        'editorial' => 'modern flat editorial illustration, clean vector shapes, soft lighting, tasteful limited color palette',
        'photographic' => 'photorealistic, natural lighting, shallow depth of field, high detail, editorial photography',
        '3d' => 'soft 3D render, rounded shapes, gentle studio lighting, pastel gradients, playful and clean',
        'minimal' => 'minimalist, lots of negative space, single focal subject, muted palette, elegant and calm',
        'isometric' => 'isometric illustration, clean geometry, subtle shadows, cohesive modern color palette',
    ];

    /** The one instruction that keeps AI thumbnails clean (generated text is usually garbled). */
    protected const NEGATIVE = 'Do not render any text, letters, words, numbers, captions, watermark, logo, signature or UI elements.';

    /**
     * Build the image prompt. A custom prompt wins; otherwise a rich prompt is
     * composed from the primary keyword, title, excerpt, category and — when
     * the post belongs to a content cluster — the cluster theme and a shared
     * style/brand hint, so every article in a cluster looks like a set.
     *
     * @param  array{custom?:?string,style?:?string,excerpt?:?string,category?:?string,keyword?:?string,cluster_theme?:?string,brand?:?string}  $ctx
     */
    public function prompt(string $title, array $ctx = []): string
    {
        $styleKey = trim((string) ($ctx['style'] ?? setting('ai.image_style', 'editorial')));
        $style = self::STYLE_PRESETS[$styleKey] ?? ($styleKey !== '' ? $styleKey : self::STYLE_PRESETS['editorial']);
        $brand = trim((string) ($ctx['brand'] ?? ''));

        if (filled($ctx['custom'] ?? null)) {
            return trim((string) $ctx['custom'])." Style: {$style}.".($brand !== '' ? " Color/brand cue: {$brand}." : '').' '.self::NEGATIVE;
        }

        $topic = trim((string) $title);
        $keyword = trim((string) ($ctx['keyword'] ?? ''));
        $excerpt = trim((string) ($ctx['excerpt'] ?? ''));
        $category = trim((string) ($ctx['category'] ?? ''));
        $clusterTheme = trim((string) ($ctx['cluster_theme'] ?? ''));

        // Lead with the concrete subject (primary keyword) so the picture is
        // ABOUT the thing, not a literal illustration of the headline text.
        $subject = $keyword !== '' ? $keyword : $topic;

        $lines = [
            "A high-quality, wide 16:9 blog header illustration about {$subject}.",
        ];
        if ($keyword !== '' && $topic !== '' && mb_strtolower($keyword) !== mb_strtolower($topic)) {
            $lines[] = "It headers an article titled \"{$topic}\".";
        }
        if ($excerpt !== '') {
            $lines[] = 'The article covers: '.\Illuminate\Support\Str::limit($excerpt, 240, '').'.';
        }
        if ($clusterTheme !== '') {
            $lines[] = "Part of a content series on \"{$clusterTheme}\" — keep it visually consistent with that theme.";
        } elseif ($category !== '') {
            $lines[] = "Theme/category: {$category}.";
        }
        $lines[] = "Style: {$style}.";
        if ($brand !== '') {
            $lines[] = "Lean on this color/brand cue: {$brand}.";
        }
        $lines[] = 'Strong single focal subject, balanced composition, professional, uncluttered, suitable as a hero image on a modern blog.';
        $lines[] = self::NEGATIVE;

        return implode(' ', $lines);
    }

    /**
     * Generate a thumbnail for a post and attach it. Returns the stored path,
     * or null on failure (caller treats failure as non-fatal). Skips silently
     * when the provider is not configured.
     *
     * @param  array{custom?:?string,style?:?string,provider?:string,model?:string,size?:string}  $opts
     */
    public function generateForPost(Post $post, string $title, array $opts = []): ?string
    {
        if (! ImageGenerator::isConfigured()) {
            return null;
        }

        // Cluster identity: a shared style + theme + brand cue so every article
        // in a cluster reads as one visual set on the catalogue.
        $cluster = $post->relationLoaded('cluster') ? $post->cluster : $post->cluster()->first();

        $prompt = $this->prompt($title, [
            'custom' => $opts['custom'] ?? null,
            'style' => $opts['style'] ?? $cluster?->thumbnail_style ?? null,
            'excerpt' => $opts['excerpt'] ?? $post->excerpt,
            'category' => $opts['category'] ?? $post->category?->name,
            'keyword' => $opts['keyword'] ?? $post->primary_keyword,
            'cluster_theme' => $cluster?->name,
            'brand' => $opts['brand'] ?? $cluster?->brand_hint,
        ]);

        $image = $this->images->generate($prompt, array_filter([
            'provider' => $opts['provider'] ?? null,
            'model' => $opts['model'] ?? null,
            'size' => $opts['size'] ?? null,
        ]));

        // SEO-friendly, human-readable filename derived from the post slug
        // (not a hash), e.g. thumbnails/how-to-compost-at-home.png. One post
        // has one thumbnail, so regenerating replaces it in place.
        $base = Str::slug($post->slug ?: $title) ?: 'thumbnail';
        $relative = 'thumbnails/'.$base.'.'.$image['ext'];
        Storage::disk('public')->put($relative, $image['bytes']);

        // Free OG/social crop (1200×630) derived locally from the SAME render —
        // no second API call. Set as the post's og_image when none is set.
        if ($og = $this->ogVariant($image['bytes'], $base)) {
            if (blank($post->seoMeta?->og_image)) {
                $post->seoMeta()->updateOrCreate([], ['og_image' => $og]);
            }
        }

        $post->update([
            'featured_image' => $relative,
            // Descriptive alt text (the title) for accessibility + image SEO.
            'featured_image_alt' => $post->featured_image_alt ?: Str::limit(trim($title), 120, ''),
        ]);

        return $relative;
    }

    /**
     * Derive a 1200×630 social/OG image from the generated hero bytes with GD
     * (center-cropped to the exact ratio) and store it. Returns the stored path
     * or null when GD is unavailable or the source is unreadable — never fatal.
     */
    protected function ogVariant(string $bytes, string $base): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }

        [$targetW, $targetH] = [1200, 630];
        $sw = imagesx($src);
        $sh = imagesy($src);

        // Scale to cover, then center-crop to the exact 1200×630 frame.
        $scale = max($targetW / $sw, $targetH / $sh);
        $rw = (int) round($sw * $scale);
        $rh = (int) round($sh * $scale);
        $resized = imagecreatetruecolor($rw, $rh);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $rw, $rh, $sw, $sh);

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagecopy($canvas, $resized, 0, 0, (int) (($rw - $targetW) / 2), (int) (($rh - $targetH) / 2), $targetW, $targetH);

        ob_start();
        imagejpeg($canvas, null, 82);
        $out = (string) ob_get_clean();

        imagedestroy($src);
        imagedestroy($resized);
        imagedestroy($canvas);

        if ($out === '') {
            return null;
        }

        $path = 'thumbnails/'.$base.'-og.jpg';
        Storage::disk('public')->put($path, $out);

        return $path;
    }
}
