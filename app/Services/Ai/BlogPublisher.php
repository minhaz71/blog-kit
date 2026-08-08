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
     *
     * $localVisible = false when the multisite selection did NOT include this
     * site: the Post is still persisted (it is the source record the hub pushes
     * to spokes and tracks link status against) but stays an unpublished draft
     * so it never appears on this blog. Fan-out to the chosen spokes happens in
     * the caller regardless.
     */
    public function publish(AiImportItem $item, array $output, bool $held = false, bool $localVisible = true): Post
    {
        $batch = $item->batch;

        return DB::transaction(function () use ($item, $batch, $output, $held, $localVisible) {
            $title = trim((string) ($output['title'] ?? $item->row['name'] ?? 'Untitled article'));
            $body = $this->cleanLinks((string) ($output['description_html'] ?? ''), $batch);

            // Every AI article: enforce the class vocabulary mechanically —
            // only BlogWriter::CONTENT_CLASSES survive (they have default
            // CSS in the blog stylesheet); anything else is stripped, and
            // id/style attributes never ship.
            $body = $this->enforceClassWhitelist($body);

            // Affiliate content: every external (affiliate) link gets
            // rel="sponsored nofollow noopener" + target="_blank" — the correct
            // technique, enforced here so the writer never has to (and can't
            // forget). Internal/relative links are untouched.
            if ($this->isAffiliate($item, $batch)) {
                $body = $this->enforceAffiliateRel($body);
            }
            $excerpt = trim(strip_tags((string) ($output['short_description_html'] ?? '')));
            $words = str_word_count(strip_tags($body));

            $post = $item->post_id ? Post::withTrashed()->find($item->post_id) : null;

            // Held (failed review) OR not published to this site → keep it an
            // unpublished local draft; otherwise resolve the normal slot.
            [$status, $publishedAt] = ($held || ! $localVisible)
                ? ['draft', null]
                : $this->publishSlot($item, $batch);

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
            ] + $this->clusterAttributes($item);

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

            // Stitch the cluster graph: record the pillar on the cluster and
            // point spokes at it. Runs on every publish so it self-heals no
            // matter which order (pillar-first or spoke-first) they land in.
            $this->stitchCluster($post);

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
     * The cluster/funnel columns to persist on the Post, resolved from the
     * item row the funnel builder populated (sendToWriter copies cluster/role/
     * funnel_stage/primary_keyword into the row). Returns an empty array for a
     * plain CSV/niche batch that carries none of these — leaving the columns
     * null so nothing downstream mistakes it for planned cluster content.
     */
    protected function clusterAttributes(AiImportItem $item): array
    {
        $row = (array) $item->row;
        $clusterName = trim((string) ($row['cluster'] ?? ''));
        $role = trim((string) ($row['role'] ?? ''));
        $stage = trim((string) ($row['funnel_stage'] ?? ''));
        $primary = trim((string) ($row['primary_keyword'] ?? ''))
            ?: (ProductWriter::keywordsFor($row)[0] ?? '');

        $out = [];

        if ($clusterName !== '') {
            $cluster = \App\Models\ContentCluster::resolve($clusterName);
            $out['cluster'] = $clusterName;
            $out['content_cluster_id'] = $cluster->id;
        }
        if (in_array($role, ['pillar', 'spoke'], true)) {
            $out['content_role'] = $role;
        }
        if (in_array($stage, ['top', 'middle', 'bottom'], true)) {
            $out['funnel_stage'] = $stage;
        }
        if ($primary !== '') {
            $out['primary_keyword'] = $primary;
        }

        return $out;
    }

    /**
     * Keep the cluster graph consistent after a post is saved:
     *  - a pillar becomes (or replaces) its cluster's pillar_post_id, and every
     *    existing spoke in that cluster is pointed at it;
     *  - a spoke inherits the cluster's current pillar (if one exists yet).
     * Idempotent — re-running a batch or backfilling converges to the same state.
     */
    protected function stitchCluster(Post $post): void
    {
        if (! $post->content_cluster_id) {
            return;
        }

        $cluster = \App\Models\ContentCluster::find($post->content_cluster_id);
        if (! $cluster) {
            return;
        }

        if ($post->content_role === 'pillar') {
            if ($cluster->pillar_post_id !== $post->id) {
                $cluster->update(['pillar_post_id' => $post->id]);
            }
            // Point sibling spokes (that don't already) at this pillar.
            Post::query()
                ->where('content_cluster_id', $cluster->id)
                ->where('content_role', 'spoke')
                ->whereKeyNot($post->id)
                ->whereNull('pillar_post_id')
                ->update(['pillar_post_id' => $post->id]);

            if ($post->pillar_post_id) {
                $post->update(['pillar_post_id' => null]); // a pillar has no pillar
            }
        } elseif ($cluster->pillar_post_id && $post->pillar_post_id !== $cluster->pillar_post_id) {
            $post->update(['pillar_post_id' => $cluster->pillar_post_id]);
        }
    }

    /** Is this item affiliate content? (role, per-row links, or batch mode.) */
    protected function isAffiliate(AiImportItem $item, $batch): bool
    {
        return ($item->row['role'] ?? null) === 'affiliate'
            || ! empty($item->row['affiliate_links'])
            || (bool) ($batch->affiliate_mode ?? false);
    }

    /**
     * Add rel="sponsored nofollow noopener" + target="_blank" to every EXTERNAL
     * (http/https) link in the body — the disclosure/technique Google expects
     * for affiliate links. Existing rel/target are replaced so we never
     * double-stack. Internal root-relative links (already relativized) are
     * left alone. Runs after the class whitelist, so bd-affiliate-btn survives.
     */
    public function enforceAffiliateRel(string $html): string
    {
        return (string) preg_replace_callback('~<a\b([^>]*)>~is', function (array $m): string {
            $attrs = $m[1];

            if (! preg_match('~href\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', $attrs, $h)) {
                return $m[0];
            }

            $href = html_entity_decode(trim($h[1], "\"'"));

            if (! preg_match('~^https?://~i', $href)) {
                return $m[0]; // internal / relative — not an affiliate/outbound link
            }

            // Drop any rel/target the model added, then set the correct ones.
            $attrs = (string) preg_replace('~\s(?:rel|target)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $attrs);

            return '<a'.rtrim($attrs).' rel="sponsored nofollow noopener" target="_blank">';
        }, $html);
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
