<?php

namespace App\Services\Network;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\Services\Ai\BlogPublisher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Spoke side: turns an inbound network payload into a local Post. Idempotent
 * on `network_origin` ("<hub_key>:<hub_post_id>"), so a re-push updates the
 * same post. Runs the SAME sanitizer + class whitelist as the AI publisher, so
 * network posts render identically to locally authored ones.
 */
class NetworkPostIngestor
{
    public function apply(string $hubKey, array $data): Post
    {
        $origin = $hubKey.':'.($data['network_post_id'] ?? '');

        return DB::transaction(function () use ($hubKey, $origin, $data) {
            $title = trim((string) ($data['title'] ?? 'Untitled article'));
            // Store any bundled in-body images locally and rewrite their URLs
            // BEFORE sanitizing, so the spoke serves them from its own disk.
            $rawContent = $this->ingestInlineImages($hubKey, $data, (string) ($data['content'] ?? ''));
            $body = (new BlogPublisher)->enforceClassWhitelist($rawContent);
            $excerpt = trim((string) ($data['excerpt'] ?? ''));
            $words = str_word_count(strip_tags($body));

            [$status, $publishedAt] = $this->slot($data);

            $post = Post::withTrashed()->where('network_origin', $origin)->first();

            $attributes = [
                'title' => $title,
                'excerpt' => mb_substr($excerpt, 0, 500),
                'content' => $body,
                'post_category_id' => $this->resolveCategory($data['category'] ?? null, $data['category_path'] ?? null),
                'author_id' => $this->resolveAuthor($data['author'] ?? null),
                'reading_time' => max(1, (int) ceil($words / 200)),
                'status' => $status,
                'published_at' => $publishedAt,
                'featured_image_alt' => trim((string) ($data['featured_image_alt'] ?? '')) ?: null,
                'featured_image' => $this->resolveImage($hubKey, $data, $post?->featured_image),
                'network_origin' => $origin,
            ] + $this->clusterAttributes($data);

            if ($post) {
                if ($post->trashed()) {
                    $post->restore();
                }
                $post->update($attributes);
            } else {
                $post = Post::create($attributes + ['slug' => $this->uniqueSlug($data['slug'] ?? $title)]);
            }

            // SEO meta.
            $seo = (array) ($data['seo'] ?? []);
            $post->seoMeta()->updateOrCreate([], [
                'title' => mb_substr((string) ($seo['title'] ?? $title), 0, 60),
                'description' => mb_substr((string) ($seo['description'] ?? ''), 0, 164),
                'focus_keyword' => trim((string) ($seo['focus_keyword'] ?? '')),
                'schema_type' => $seo['schema_type'] ?? null,
                'schema_enabled' => true,
            ]);

            // FAQs — replace wholesale so a re-push never stacks duplicates.
            $post->allFaqs()->delete();
            foreach (array_values((array) ($data['faqs'] ?? [])) as $i => $faq) {
                if (! empty($faq['question']) && ! empty($faq['answer'])) {
                    $post->allFaqs()->create([
                        'question' => trim((string) $faq['question']),
                        'answer' => trim((string) $faq['answer']),
                        'sort_order' => $i,
                        'is_active' => true,
                    ]);
                }
            }

            // Tags — map/create by name, then sync.
            $tagIds = collect((array) ($data['tags'] ?? []))
                ->filter(fn ($n) => trim((string) $n) !== '')
                ->map(fn ($n) => Tag::firstOrCreate(['name' => trim((string) $n)])->id)
                ->all();
            $post->tags()->sync($tagIds);

            // Wire the cluster graph: point this post at its pillar (mapped from
            // the hub's pillar id) and, if it IS a pillar, adopt it on its cluster.
            $this->stitchCluster($hubKey, $post, $data);

            return $post;
        });
    }

    /**
     * Cluster/funnel columns from the payload's content_meta, resolving the
     * cluster name to a LOCAL ContentCluster. pillar_post_id is set later by
     * stitchCluster (it needs the pillar's local id).
     */
    protected function clusterAttributes(array $data): array
    {
        $meta = (array) ($data['content_meta'] ?? []);
        $out = [];

        $clusterName = trim((string) ($meta['cluster'] ?? ''));
        if ($clusterName !== '') {
            $out['cluster'] = $clusterName;
            $out['content_cluster_id'] = \App\Models\ContentCluster::resolve($clusterName)->id;
        }
        if (in_array($meta['content_role'] ?? '', ['pillar', 'spoke'], true)) {
            $out['content_role'] = $meta['content_role'];
        }
        if (in_array($meta['funnel_stage'] ?? '', ['top', 'middle', 'bottom'], true)) {
            $out['funnel_stage'] = $meta['funnel_stage'];
        }
        if (trim((string) ($meta['primary_keyword'] ?? '')) !== '') {
            $out['primary_keyword'] = (string) $meta['primary_keyword'];
        }

        return $out;
    }

    /**
     * Resolve pillar linkage using the hub's pillar post id mapped to the local
     * copy via network_origin; and when this post is a pillar, record it on its
     * cluster + point sibling spokes at it. Self-heals across push order.
     */
    protected function stitchCluster(string $hubKey, Post $post, array $data): void
    {
        $pillarHubId = $data['content_meta']['pillar_network_post_id'] ?? null;

        if ($pillarHubId) {
            $pillar = Post::where('network_origin', $hubKey.':'.$pillarHubId)->first();
            if ($pillar && $post->pillar_post_id !== $pillar->id) {
                $post->update(['pillar_post_id' => $pillar->id]);
            }
        }

        if ($post->content_role === 'pillar' && $post->content_cluster_id) {
            $cluster = \App\Models\ContentCluster::find($post->content_cluster_id);
            if ($cluster && $cluster->pillar_post_id !== $post->id) {
                $cluster->update(['pillar_post_id' => $post->id]);
            }
            Post::where('content_cluster_id', $post->content_cluster_id)
                ->where('content_role', 'spoke')
                ->whereNull('pillar_post_id')
                ->update(['pillar_post_id' => $post->id]);
        }
    }

    /**
     * Store bundled in-body images on the local public disk and rewrite their
     * <img src> to the local URL, so the spoke serves them itself (never hot-
     * links back to the hub). External/unbundled images are left untouched.
     */
    protected function ingestInlineImages(string $hubKey, array $data, string $content): string
    {
        $images = (array) ($data['inline_images'] ?? []);
        if ($images === []) {
            return $content;
        }

        $disk = Storage::disk('public');
        $hubDir = Str::slug($hubKey) ?: 'hub';

        foreach ($images as $img) {
            $src = (string) ($img['src'] ?? '');
            $b64 = (string) ($img['data'] ?? '');
            if ($src === '' || $b64 === '') {
                continue;
            }

            $bytes = base64_decode($b64, true);
            if ($bytes === false || $bytes === '') {
                continue;
            }
            $info = @getimagesizefromstring($bytes);
            if ($info === false) {
                continue; // never write non-image bytes
            }

            $ext = image_type_to_extension($info[2], false) ?: 'img';
            $name = Str::slug(pathinfo((string) ($img['filename'] ?? 'image'), PATHINFO_FILENAME)) ?: 'image';
            $fingerprint = substr((string) ($img['sha256'] ?? hash('sha256', $bytes)), 0, 12);
            $relative = 'network/'.$hubDir.'/inline/'.$name.'-'.$fingerprint.'.'.$ext;

            $disk->put($relative, $bytes);

            // Root-relative so it resolves on the spoke's own domain.
            $content = str_replace($src, '/storage/'.$relative, $content);
        }

        return $content;
    }

    /**
     * File the post under its category, rebuilding the mother→sub tree from the
     * ancestor chain when the hub sent one. Parent is set only when a level is
     * first created — an existing spoke category is never reparented. Falls back
     * to the flat name/slug for older hubs that don't send a path.
     *
     * @param  array<int, array{name?:string, slug?:string}>|null  $path  root→leaf
     */
    protected function resolveCategory(?array $category, ?array $path = null): ?int
    {
        if (is_array($path) && $path !== []) {
            $parentId = null;
            $leafId = null;

            foreach ($path as $node) {
                $name = trim((string) ($node['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $slug = (string) ($node['slug'] ?? Str::slug($name));

                $cat = PostCategory::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'parent_id' => $parentId, 'is_active' => true, 'show_in_menu' => true],
                );

                $parentId = $cat->id;
                $leafId = $cat->id;
            }

            if ($leafId !== null) {
                return $leafId;
            }
        }

        if (! $category || empty($category['name'])) {
            return null;
        }

        $slug = (string) ($category['slug'] ?? Str::slug((string) $category['name']));

        return PostCategory::firstOrCreate(
            ['slug' => $slug],
            ['name' => (string) $category['name']],
        )->id;
    }

    /**
     * Map author by email to a local user. When none exists, create an
     * ATTRIBUTION-ONLY user (is_active=false, unusable random password, no
     * roles) carrying the E-E-A-T profile (name, job title, bio, social links)
     * so the author box + Person schema render on the spoke without granting
     * any login/admin access. Falls back to the lowest-id user when the
     * payload has no author email.
     */
    protected function resolveAuthor(?array $author): ?int
    {
        if ($author && ! empty($author['email'])) {
            // firstOrCreate is atomic on the unique email, so concurrent
            // fan-out pushes by the same new author never race into a
            // duplicate-key failure. An existing local user is returned as-is
            // (its profile is never overwritten).
            $user = User::firstOrCreate(
                ['email' => (string) $author['email']],
                [
                    'name' => (string) ($author['name'] ?: 'Author'),
                    'password' => Hash::make(Str::random(40)),
                    'is_active' => false, // attribution only — cannot log in
                    'job_title' => $author['job_title'] ?? null,
                    'bio' => $author['bio'] ?? null,
                    'social_links' => ! empty($author['social_links']) ? (array) $author['social_links'] : null,
                ],
            );

            // Mirror the hub's author URL slug on first creation (guarded, so
            // set explicitly) when it is free — keeps /author/<slug> consistent
            // across sites. Never touch an existing user's slug.
            if ($user->wasRecentlyCreated && ! empty($author['public_slug'])
                && ! User::where('public_slug', $author['public_slug'])->where('id', '!=', $user->id)->exists()) {
                $user->forceFill(['public_slug' => (string) $author['public_slug']])->save();
            }

            return $user->id;
        }

        return User::query()->orderBy('id')->value('id');
    }

    /**
     * Decide the local featured_image path from the payload:
     *  - hub has no image  → null (clear it);
     *  - image shipped     → decode, validate it IS an image, store on the
     *    public disk under a content-addressed path (idempotent), return it;
     *  - present but not shipped (too large / decode fails) → keep $current.
     */
    protected function resolveImage(string $hubKey, array $data, ?string $current): ?string
    {
        if (! ($data['has_featured_image'] ?? false)) {
            return null;
        }

        $img = $data['featured_image'] ?? null;
        if (! is_array($img) || empty($img['data'])) {
            return $current; // too large to inline / not sent — leave existing
        }

        $bytes = base64_decode((string) $img['data'], true);
        if ($bytes === false || $bytes === '') {
            return $current;
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return $current; // not a real image — never write arbitrary bytes
        }

        $ext = image_type_to_extension($info[2], false) ?: 'img';
        // SEO-friendly filename from the post slug (not a hash), namespaced by
        // the hub key so two hubs sharing a slug can't overwrite each other's
        // image on one spoke. One post → one file, replaced in place on re-push.
        $base = Str::slug((string) ($data['slug'] ?? $data['title'] ?? 'thumbnail')) ?: 'thumbnail';
        $hubDir = Str::slug($hubKey) ?: 'hub';
        $relative = 'network/'.$hubDir.'/'.$base.'.'.$ext;

        Storage::disk('public')->put($relative, $bytes);

        return $relative;
    }

    /** [status, published_at]. Future published_at with published status becomes scheduled. */
    protected function slot(array $data): array
    {
        $status = in_array($data['status'] ?? '', ['draft', 'published', 'scheduled'], true) ? $data['status'] : 'draft';
        $at = null;

        if (! empty($data['published_at'])) {
            try {
                $at = Carbon::parse((string) $data['published_at']);
            } catch (\Throwable) {
                $at = null;
            }
        }

        if ($status === 'published' && $at && $at->isFuture()) {
            $status = 'scheduled';
        }

        if ($status === 'published' && ! $at) {
            $at = now();
        }

        return [$status, $at];
    }

    protected function uniqueSlug(string $slugOrTitle): string
    {
        $base = Str::slug(mb_substr($slugOrTitle, 0, 90)) ?: 'article';
        $slug = $base;
        $n = 2;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
