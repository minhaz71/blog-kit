<?php

namespace App\Services\Ai;

use App\Models\AiImportItem;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns approved blog output into a Post — transactional and idempotent
 * (re-running an item updates its own post instead of duplicating it).
 * Mirrors ProductPublisher: content + excerpt + SEO meta + FAQs + category,
 * then deterministic link hygiene (invented URLs unwrapped, internal links
 * made root-relative so copy survives a domain change).
 */
class BlogPublisher
{
    /**
     * $held = the reviewer never approved: the article is still SAVED (as a
     * draft, whatever the batch publish mode) so no work is ever lost — the
     * item keeps its "needs review" label and the admin publishes from the
     * Posts list or via Approve & publish.
     */
    public function publish(AiImportItem $item, array $output, bool $held = false): Post
    {
        $batch = $item->batch;

        return DB::transaction(function () use ($item, $batch, $output, $held) {
            $title = trim((string) ($output['title'] ?? $item->row['name'] ?? 'Untitled article'));
            $body = $this->cleanLinks((string) ($output['description_html'] ?? ''), $batch);

            // Every AI article: enforce the class vocabulary mechanically —
            // only BlogWriter::CONTENT_CLASSES survive (they have default
            // CSS in the blog stylesheet); anything else is stripped, and
            // id/style attributes never ship.
            $body = $this->enforceClassWhitelist($body);
            $excerpt = trim(strip_tags((string) ($output['short_description_html'] ?? '')));
            $words = str_word_count(strip_tags($body));

            $post = $item->post_id ? Post::withTrashed()->find($item->post_id) : null;

            [$status, $publishedAt] = $held ? ['draft', null] : $this->publishSlot($item, $batch);

            $attributes = [
                'title' => $title,
                'excerpt' => mb_substr($excerpt, 0, 500),
                'content' => $body,
                'post_category_id' => $batch->blog_category_id,
                'author_id' => $batch->user_id
                    ?? User::query()->orderBy('id')->value('id'),
                'reading_time' => max(1, (int) ceil($words / 200)),
                'status' => $status,
                'published_at' => $publishedAt,
                'featured_image_alt' => trim((string) ($output['image_alt'] ?? '')) ?: null,
                // Deterministic (from ComparisonPlanner's pairing), never
                // AI-derived — carries the compared products to the schema
                // layer without re-deriving them from the article text.
                'compared_product_ids' => $item->row['compared_product_ids'] ?? null,
            ];

            if ($post) {
                // Refresh: keep the existing title, slug/URL, publish status
                // and date — rewriting must not re-title, re-slug, or
                // unpublish a live, ranking article.
                if ($batch->refresh) {
                    unset($attributes['title'], $attributes['status'], $attributes['published_at']);
                }
                $post->update($attributes);
            } else {
                $post = Post::create($attributes + [
                    'slug' => $this->uniqueSlug($title),
                ]);
            }

            $post->seoMeta()->updateOrCreate([], [
                'title' => mb_substr((string) ($output['meta_title'] ?? $title), 0, 60),
                'description' => mb_substr((string) ($output['meta_description'] ?? ''), 0, 164),
                'focus_keyword' => trim((string) ($output['focus_keyword'] ?? ''))
                    ?: (ProductWriter::keywordsFor($item->row)[0] ?? ''),
                'schema_enabled' => true,
            ]);

            // Replace FAQs wholesale — a re-run must not stack duplicates.
            $post->allFaqs()->delete();
            foreach (array_values((array) ($output['faqs'] ?? [])) as $i => $faq) {
                if (! empty($faq['question']) && ! empty($faq['answer'])) {
                    $post->allFaqs()->create([
                        'question' => trim((string) $faq['question']),
                        'answer' => trim((string) $faq['answer']),
                        'sort_order' => $i,
                        'is_active' => true,
                    ]);
                }
            }

            $item->update(['post_id' => $post->id, 'status' => $held ? 'needs_review' : 'published']);

            // Close the loop with the waiting area: the idea that produced
            // this article is now "written" and points at its post.
            if (! $held && ! empty($item->row['idea_id'])) {
                \App\Models\BlogTopicIdea::query()
                    ->whereKey($item->row['idea_id'])
                    ->update(['status' => 'written', 'post_id' => $post->id]);
            }

            return $post;
        });
    }

    /**
     * Keep only whitelisted content classes (BlogWriter::CONTENT_CLASSES);
     * drop every other class token, and the attribute entirely when nothing
     * survives. id/style attributes never survive.
     */
    public function enforceClassWhitelist(string $html): string
    {
        // Strip executable/framing constructs (script, iframe, on* handlers,
        // javascript: URLs) first — the class/id/style pass below only handles
        // attributes, not dangerous tags.
        $html = \App\Support\HtmlSanitizer::clean($html);

        $allowed = array_flip(BlogWriter::CONTENT_CLASSES);

        $html = (string) preg_replace_callback(
            '/\sclass\s*=\s*(["\'])(.*?)\1/i',
            function (array $m) use ($allowed): string {
                $kept = array_values(array_filter(
                    preg_split('/\s+/', trim($m[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                    fn ($class) => isset($allowed[$class])
                ));

                return $kept === [] ? '' : ' class="'.implode(' ', $kept).'"';
            },
            $html
        );

        return (string) preg_replace('/\s(?:id|style)\s*=\s*(["\']).*?\1/i', '', $html);
    }

    /**
     * When does this article go live? Returns [status, published_at].
     *
     * Priority:
     *  1. draft mode → draft (the review-first workflow wins);
     *  2. a CSV publish date on the row — date-only publishes at 00:00, a
     *     time column (or datetime in the date column) publishes at that
     *     exact time; a past date publishes immediately;
     *  3. the batch's "delay between articles" — article N goes live at
     *     batch start + N × interval (deterministic by item order, so
     *     parallel writers can never double-book a slot);
     *  4. otherwise publish now (original behavior).
     *
     * Future slots get status "scheduled" — invisible on the storefront
     * until the blog:publish-scheduled cron flips them at the right time.
     */
    protected function publishSlot(AiImportItem $item, $batch): array
    {
        if ($batch->publish_mode !== 'publish') {
            return ['draft', null];
        }

        if ($at = $this->rowPublishAt($item->row)) {
            return $at->isFuture() ? ['scheduled', $at] : ['published', now()];
        }

        if ((int) $batch->publish_interval_minutes > 0) {
            $rank = $batch->items()->where('id', '<', $item->id)->count();
            $at = $batch->created_at->copy()->addMinutes($rank * (int) $batch->publish_interval_minutes);

            return $at->isFuture() ? ['scheduled', $at] : ['published', now()];
        }

        return ['published', now()];
    }

    /** Parse the row's publish_date (+ optional publish_time) columns. */
    protected function rowPublishAt(array $row): ?\Illuminate\Support\Carbon
    {
        $date = trim((string) ($row['publish_date'] ?? ''));

        if ($date === '') {
            return null;
        }

        $time = trim((string) ($row['publish_time'] ?? ''));

        try {
            $at = \Illuminate\Support\Carbon::parse($time !== '' ? "{$date} {$time}" : $date);

            // Date-only input publishes at 00:00 — Carbon::parse already
            // yields midnight when no time component is present.
            return $at;
        } catch (\Throwable) {
            return null; // unparseable date: fall through to the next rule
        }
    }

    /**
     * Deterministic link hygiene on the article body:
     *  1. unwrap internal links whose URL is not in the batch catalog
     *     (invented/altered URLs become plain text — no dead links ship);
     *  2. relativize surviving internal links (dev URL → production-safe).
     */
    protected function cleanLinks(string $html, $batch): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $allowed = array_column((array) $batch->link_catalog, 'url');

        if ($html === '' || $appUrl === '') {
            return $html;
        }

        $html = (string) preg_replace_callback(
            '~<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)</a>~is',
            function (array $m) use ($appUrl, $allowed) {
                $href = html_entity_decode($m[2]);

                if (! str_starts_with($href, $appUrl)) {
                    return $m[0]; // external link — writer's judgement stands
                }

                if ($allowed !== [] && ! in_array($href, $allowed, true)) {
                    return $m[3]; // invented internal URL — keep the text, drop the link
                }

                $relative = substr($href, strlen($appUrl)) ?: '/';

                return str_replace($m[2], e($relative), $m[0]);
            },
            $html,
        );

        return $html;
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug(mb_substr($title, 0, 90)) ?: 'article';
        $slug = $base;
        $n = 2;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
