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

    /**
     * Build the image prompt. A custom prompt wins; otherwise it is derived
     * from the title. "No text/watermark/logo" keeps AI thumbnails clean
     * (generated text is usually garbled).
     */
    public function prompt(string $title, ?string $custom = null, ?string $style = null): string
    {
        $style = trim((string) ($style ?: setting('ai.image_style', 'modern flat editorial illustration, soft lighting, tasteful color palette')));

        if (filled($custom)) {
            return trim($custom).($style !== '' ? ". Style: {$style}." : '').' No text, no watermark, no logo.';
        }

        return "A high-quality blog thumbnail image for an article titled \"".trim($title)."\". "
            .($style !== '' ? "Style: {$style}. " : '')
            .'Editorial, clean and modern, visually represents the topic. No text, no watermark, no logo.';
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

        $prompt = $this->prompt($title, $opts['custom'] ?? null, $opts['style'] ?? null);

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
