<?php

namespace App\Services\Network;

use App\Models\Post;

/**
 * Builds the wire payload for pushing a hub Post to a spoke, and a stable
 * content hash for change detection. Media files are NOT sent in this phase
 * (only the featured-image alt text); full media sync arrives later.
 */
class NetworkPostPayload
{
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
            ] : null,
            'faqs' => $faqs,
        ];
    }

    /** Content hash for change detection (excludes volatile identity fields). */
    public static function hash(array $payload): string
    {
        $subset = collect($payload)->except(['network_post_id'])->all();

        return hash('sha256', json_encode($subset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
