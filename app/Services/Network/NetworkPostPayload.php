<?php

namespace App\Services\Network;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the wire payload for pushing a hub Post to a spoke, and a stable
 * content hash for change detection. Carries the featured-image FILE (base64,
 * with a byte fingerprint) and the author's E-E-A-T profile so a spoke copy is
 * self-contained.
 */
class NetworkPostPayload
{
    /** Skip shipping a featured image larger than this (bytes) inline. */
    public const MAX_IMAGE_BYTES = 6 * 1024 * 1024;

    public static function for(Post $post): array
    {
        $post->loadMissing(['seoMeta', 'category', 'tags', 'author', 'allFaqs']);

        $faqs = $post->allFaqs
            ->sortBy('sort_order')
            ->map(fn ($f) => ['question' => (string) $f->question, 'answer' => (string) $f->answer])
            ->values()
            ->all();

        $author = $post->author;

        return [
            // Stable identity for idempotent upserts on the spoke.
            'network_post_id' => $post->id,
            'title' => (string) $post->title,
            'slug' => (string) $post->slug,
            'excerpt' => (string) $post->excerpt,
            'content' => (string) $post->content,
            'status' => (string) $post->status,           // draft|published|scheduled
            'published_at' => $post->published_at?->toIso8601String(),
            'featured_image_alt' => $post->featured_image_alt,
            // Whether the hub post HAS a featured image (independent of whether
            // we could inline it) — lets the spoke tell "removed" from "too
            // large to ship, keep what you have".
            'has_featured_image' => (bool) $post->featured_image,
            'featured_image' => self::image($post->featured_image),
            'seo' => [
                'title' => (string) ($post->seoMeta?->title ?? ''),
                'description' => (string) ($post->seoMeta?->description ?? ''),
                'focus_keyword' => (string) ($post->seoMeta?->focus_keyword ?? ''),
                'schema_type' => $post->seoMeta?->schema_type,
            ],
            'category' => $post->category ? [
                'name' => (string) $post->category->name,
                'slug' => (string) $post->category->slug,
            ] : null,
            'tags' => $post->tags->pluck('name')->map(fn ($n) => (string) $n)->all(),
            'author' => $author ? [
                'name' => (string) ($author->name ?? ''),
                'email' => (string) ($author->email ?? ''),
                'public_slug' => $author->public_slug ?? null,
                'job_title' => $author->job_title ?? null,
                'bio' => $author->bio ?? null,
                'social_links' => (array) ($author->social_links ?? []),
            ] : null,
            'faqs' => $faqs,
        ];
    }

    /**
     * The featured image as {filename, mime, sha256, data(base64)} — or null
     * when there is none, the file is missing, or it is too large to inline.
     * sha256 is the byte fingerprint used for change detection (the heavy
     * base64 `data` is excluded from the content hash).
     */
    protected static function image(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path) || $disk->size($path) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $bytes = (string) $disk->get($path);

        if ($bytes === '') {
            return null;
        }

        return [
            'filename' => basename($path),
            'mime' => $disk->mimeType($path) ?: 'application/octet-stream',
            'sha256' => hash('sha256', $bytes),
            'data' => base64_encode($bytes),
        ];
    }

    /**
     * Canonical content hash for change detection. Computed over the CONTENT
     * only — excludes site-local artifacts that legitimately differ between
     * hub and spoke (post id, slug, image filename/url, the raw image bytes),
     * so an unchanged post hashes identically on both sides. The featured
     * image contributes via its byte fingerprint (sha256), not its filename.
     */
    public static function hash(array $payload): string
    {
        $fi = $payload['featured_image'] ?? null;

        $canonical = [
            'title' => (string) ($payload['title'] ?? ''),
            'excerpt' => (string) ($payload['excerpt'] ?? ''),
            'content' => (string) ($payload['content'] ?? ''),
            'status' => (string) ($payload['status'] ?? ''),
            'published_at' => $payload['published_at'] ?? null,
            'featured_image_alt' => $payload['featured_image_alt'] ?? null,
            'featured_image' => $fi['sha256'] ?? null,
            'seo' => $payload['seo'] ?? [],
            'category' => $payload['category']['name'] ?? null,
            'tags' => $payload['tags'] ?? [],
            'author' => $payload['author']['email'] ?? ($payload['author']['name'] ?? null),
            'faqs' => $payload['faqs'] ?? [],
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
