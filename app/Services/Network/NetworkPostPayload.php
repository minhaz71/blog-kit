<?php

namespace App\Services\Network;

use App\Models\Post;
use Illuminate\Support\Carbon;
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

    /** Caps for in-body images bundled with the post. */
    public const MAX_INLINE_IMAGES = 20;

    public const MAX_INLINE_TOTAL_BYTES = 12 * 1024 * 1024;

    public static function for(Post $post): array
    {
        $post->loadMissing(['seoMeta', 'category.parent', 'tags', 'author', 'allFaqs']);

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
            // Full mother→…→leaf chain so the spoke rebuilds the hierarchy.
            'category_path' => self::categoryPath($post),
            // Cluster/funnel metadata so the spoke's cluster-aware linking works.
            'content_meta' => [
                'cluster' => $post->cluster,
                'content_role' => $post->content_role,
                'funnel_stage' => $post->funnel_stage,
                'primary_keyword' => $post->primary_keyword,
                // Hub post id of this post's pillar; the spoke maps it to its
                // local copy via network_origin.
                'pillar_network_post_id' => $post->pillar_post_id,
            ],
            // Images referenced inside the body, bundled so they survive the hop.
            'inline_images' => self::inlineImages((string) $post->content),
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
            'sha256' => self::imageSha($path),
            'data' => base64_encode($bytes),
        ];
    }

    /**
     * The category's ancestor chain root→leaf as [{name, slug}], so the spoke
     * can rebuild the mother→sub tree. Null when the post has no category.
     *
     * @return array<int, array{name:string, slug:string}>|null
     */
    protected static function categoryPath(Post $post): ?array
    {
        if (! $post->category) {
            return null;
        }

        return collect($post->category->breadcrumbTrail())
            ->map(fn ($c) => ['name' => (string) $c->name, 'slug' => (string) $c->slug])
            ->all();
    }

    /**
     * Images referenced by <img src> in the body that live on THIS site's public
     * disk, bundled as {src, path, filename, mime, sha256, data(base64)} so the
     * spoke can store them locally and rewrite the URLs. External images are left
     * untouched. Bounded by MAX_INLINE_IMAGES and MAX_INLINE_TOTAL_BYTES.
     *
     * @return array<int, array{src:string, path:string, filename:string, mime:string, sha256:string, data:string}>
     */
    public static function inlineImages(string $content): array
    {
        if (! str_contains($content, '<img')) {
            return [];
        }

        $disk = Storage::disk('public');
        $out = [];
        $seen = [];
        $total = 0;

        preg_match_all('/<img\b[^>]*\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $src = $m[2] !== '' ? $m[2] : ($m[3] ?? '');
            if ($src === '' || isset($seen[$src])) {
                continue;
            }
            $seen[$src] = true;

            $path = self::localDiskPath($src);
            if ($path === null || ! $disk->exists($path) || $disk->size($path) > self::MAX_IMAGE_BYTES) {
                continue; // external, missing, or too large — leave the tag as-is
            }

            $bytes = (string) $disk->get($path);
            if ($bytes === '' || @getimagesizefromstring($bytes) === false) {
                continue;
            }
            if ($total + strlen($bytes) > self::MAX_INLINE_TOTAL_BYTES) {
                break;
            }
            $total += strlen($bytes);

            $out[] = [
                'src' => $src,
                'path' => $path,
                'filename' => basename($path),
                'mime' => $disk->mimeType($path) ?: 'application/octet-stream',
                'sha256' => hash('sha256', $bytes),
                'data' => base64_encode($bytes),
            ];

            if (count($out) >= self::MAX_INLINE_IMAGES) {
                break;
            }
        }

        return $out;
    }

    /**
     * Map an <img src> to a public-disk-relative path when it points at THIS
     * site's storage (root-relative `/storage/…` or an absolute app-URL); null
     * for external/remote images.
     */
    protected static function localDiskPath(string $src): ?string
    {
        $src = trim(html_entity_decode($src));
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($src, $appUrl)) {
            $src = substr($src, strlen($appUrl));
        }

        if (preg_match('#^/?storage/(.+)$#', $src, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Sorted sha256 list of the body's local inline images (for change detection). */
    protected static function inlineImageShas(string $content): array
    {
        return collect(self::inlineImages($content))->pluck('sha256')->sort()->values()->all();
    }

    /** sha256 of the image bytes on the public disk (no base64), or null. */
    public static function imageSha(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($path) ? hash('sha256', (string) $disk->get($path)) : null;
    }

    /**
     * Canonical content hash for change detection, computed DIRECTLY from a
     * Post — no base64 (cheap on list endpoints) and identical on hub and
     * spoke. Excludes site-local artifacts (post id, slug, image filename/url,
     * raw bytes). Normalized so legitimate cross-site differences don't create
     * false conflicts: published_at in UTC (installs may have different
     * timezones), tags sorted (pivot order differs), category by name, author
     * by email. The image contributes via its byte fingerprint (sha256).
     */
    public static function contentHash(Post $post): string
    {
        $post->loadMissing(['seoMeta', 'category', 'tags', 'author', 'allFaqs']);

        $faqs = $post->allFaqs->sortBy('sort_order')
            ->map(fn ($f) => ['q' => (string) $f->question, 'a' => (string) $f->answer])->values()->all();

        $tags = $post->tags->pluck('name')->map(fn ($n) => (string) $n)->sort()->values()->all();

        return self::hashCanonical([
            'title' => (string) $post->title,
            'excerpt' => (string) $post->excerpt,
            'content' => (string) $post->content,
            'status' => (string) $post->status,
            'published_at' => $post->published_at?->clone()->utc()->toIso8601String(),
            'featured_image_alt' => $post->featured_image_alt,
            'featured_image' => self::imageSha($post->featured_image),
            'seo' => [
                'title' => (string) ($post->seoMeta?->title ?? ''),
                'description' => (string) ($post->seoMeta?->description ?? ''),
                'focus_keyword' => (string) ($post->seoMeta?->focus_keyword ?? ''),
                'schema_type' => $post->seoMeta?->schema_type,
            ],
            'category' => $post->category?->name,
            'category_path' => $post->category ? collect($post->category->breadcrumbTrail())->pluck('slug')->all() : [],
            'content_meta' => [
                'cluster' => (string) $post->cluster,
                'content_role' => (string) $post->content_role,
                'funnel_stage' => (string) $post->funnel_stage,
                'primary_keyword' => (string) $post->primary_keyword,
            ],
            'inline_images' => self::inlineImageShas((string) $post->content),
            'tags' => $tags,
            'author' => $post->author?->email ?? $post->author?->name,
            'faqs' => $faqs,
        ]);
    }

    /**
     * Hash a wire PAYLOAD (from for()) with the SAME normalization as
     * contentHash — kept so a payload received over the wire can be hashed
     * without re-loading the model. published_at → UTC; tags sorted.
     */
    public static function hash(array $payload): string
    {
        $fi = $payload['featured_image'] ?? null;
        $publishedAt = $payload['published_at'] ?? null;

        try {
            $publishedAt = $publishedAt ? Carbon::parse($publishedAt)->utc()->toIso8601String() : null;
        } catch (\Throwable) {
            // leave as-is if unparseable
        }

        $tags = collect($payload['tags'] ?? [])->map(fn ($n) => (string) $n)->sort()->values()->all();

        return self::hashCanonical([
            'title' => (string) ($payload['title'] ?? ''),
            'excerpt' => (string) ($payload['excerpt'] ?? ''),
            'content' => (string) ($payload['content'] ?? ''),
            'status' => (string) ($payload['status'] ?? ''),
            'published_at' => $publishedAt,
            'featured_image_alt' => $payload['featured_image_alt'] ?? null,
            'featured_image' => $fi['sha256'] ?? null,
            'seo' => $payload['seo'] ?? [],
            'category' => $payload['category']['name'] ?? null,
            'category_path' => collect($payload['category_path'] ?? [])->pluck('slug')->all(),
            'content_meta' => [
                'cluster' => (string) ($payload['content_meta']['cluster'] ?? ''),
                'content_role' => (string) ($payload['content_meta']['content_role'] ?? ''),
                'funnel_stage' => (string) ($payload['content_meta']['funnel_stage'] ?? ''),
                'primary_keyword' => (string) ($payload['content_meta']['primary_keyword'] ?? ''),
            ],
            'inline_images' => collect($payload['inline_images'] ?? [])->pluck('sha256')->sort()->values()->all(),
            'tags' => $tags,
            'author' => $payload['author']['email'] ?? ($payload['author']['name'] ?? null),
            'faqs' => $payload['faqs'] ?? [],
        ]);
    }

    protected static function hashCanonical(array $canonical): string
    {
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
