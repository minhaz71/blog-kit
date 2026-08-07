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
     * composed from the title, excerpt and category so the picture actually
     * represents the article, not just its headline.
     *
     * @param  array{custom?:?string,style?:?string,excerpt?:?string,category?:?string}  $ctx
     */
    public function prompt(string $title, array $ctx = []): string
    {
        $styleKey = trim((string) ($ctx['style'] ?? setting('ai.image_style', 'editorial')));
        $style = self::STYLE_PRESETS[$styleKey] ?? ($styleKey !== '' ? $styleKey : self::STYLE_PRESETS['editorial']);

        if (filled($ctx['custom'] ?? null)) {
            return trim((string) $ctx['custom'])." Style: {$style}. ".self::NEGATIVE;
        }

        $topic = trim((string) $title);
        $excerpt = trim((string) ($ctx['excerpt'] ?? ''));
        $category = trim((string) ($ctx['category'] ?? ''));

        $lines = [
            "A high-quality, wide 16:9 blog header illustration that visually represents the topic of an article titled \"{$topic}\".",
        ];
        if ($excerpt !== '') {
            $lines[] = 'The article is about: '.\Illuminate\Support\Str::limit($excerpt, 240, '').'.';
        }
        if ($category !== '') {
            $lines[] = "Theme/category: {$category}.";
        }
        $lines[] = "Style: {$style}.";
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

        $prompt = $this->prompt($title, [
            'custom' => $opts['custom'] ?? null,
            'style' => $opts['style'] ?? null,
            'excerpt' => $opts['excerpt'] ?? $post->excerpt,
            'category' => $opts['category'] ?? $post->category?->name,
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

        $post->update([
            'featured_image' => $relative,
            // Descriptive alt text (the title) for accessibility + image SEO.
            'featured_image_alt' => $post->featured_image_alt ?: Str::limit(trim($title), 120, ''),
        ]);

        return $relative;
    }
}
