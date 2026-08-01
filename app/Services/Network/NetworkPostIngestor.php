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

        return DB::transaction(function () use ($origin, $data) {
            $title = trim((string) ($data['title'] ?? 'Untitled article'));
            $body = (new BlogPublisher)->enforceClassWhitelist((string) ($data['content'] ?? ''));
            $excerpt = trim((string) ($data['excerpt'] ?? ''));
            $words = str_word_count(strip_tags($body));

            [$status, $publishedAt] = $this->slot($data);

            $post = Post::withTrashed()->where('network_origin', $origin)->first();

            $attributes = [
                'title' => $title,
                'excerpt' => mb_substr($excerpt, 0, 500),
                'content' => $body,
                'post_category_id' => $this->resolveCategory($data['category'] ?? null),
                'author_id' => $this->resolveAuthor($data['author'] ?? null),
                'reading_time' => max(1, (int) ceil($words / 200)),
                'status' => $status,
                'published_at' => $publishedAt,
                'featured_image_alt' => trim((string) ($data['featured_image_alt'] ?? '')) ?: null,
                'featured_image' => $this->resolveImage($data, $post?->featured_image),
                'network_origin' => $origin,
            ];

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

            return $post;
        });
    }

    protected function resolveCategory(?array $category): ?int
    {
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
            $existing = User::where('email', $author['email'])->first();
            if ($existing) {
                return $existing->id; // never overwrite a real local user's profile
            }

            return User::create([
                'name' => (string) ($author['name'] ?: 'Author'),
                'email' => (string) $author['email'],
                'password' => Hash::make(Str::random(40)),
                'is_active' => false, // attribution only — cannot log in
                'job_title' => $author['job_title'] ?? null,
                'bio' => $author['bio'] ?? null,
                'social_links' => ! empty($author['social_links']) ? (array) $author['social_links'] : null,
            ])->id;
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
    protected function resolveImage(array $data, ?string $current): ?string
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
        // SEO-friendly filename from the post slug (not a hash), matching how
        // the hub names thumbnails, so the image URL stays descriptive on every
        // site. One post → one file, replaced in place on re-push.
        $base = Str::slug((string) ($data['slug'] ?? $data['title'] ?? 'thumbnail')) ?: 'thumbnail';
        $relative = 'network/'.$base.'.'.$ext;

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
