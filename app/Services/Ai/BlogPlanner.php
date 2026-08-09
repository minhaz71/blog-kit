<?php

namespace App\Services\Ai;

use App\Jobs\StartAiImportBatch;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\Post;
use App\Models\Product;
use App\Services\Network\NetworkTargets;

/**
 * Decides WHAT the blog agent writes.
 *
 * Three modes, chosen by what the batch carries (first match wins):
 *  - CSV uploaded (csv_path): each row is one article brief — title,
 *    keywords, country, niche, details, category… every column reaches
 *    the writer as research context. The bulk-brief mode.
 *  - Title ideas given (topic_ideas): each line becomes one article item —
 *    the store owner already planned, no planning tokens spent.
 *  - Niche only: one LLM call designs a topic CLUSTER (pillar + spokes)
 *    around the niche — deduplicated against every post already on the
 *    blog and against the other items in the plan, keyword-mapped per
 *    article so the writer and the lint enforce placement automatically.
 *
 * Also builds the batch link catalog per its link_scope so the writer
 * places contextual internal links exactly like the product agent:
 *  - ecommerce: products, product categories, posts, blog categories, home
 *  - blog_only: posts + blog categories only.
 */
class BlogPlanner
{
    /** @return int number of article items created */
    public function plan(AiImportBatch $batch): int
    {
        // User-authored topics (CSV rows or pasted titles) target THIS site by
        // default and are guarded against it. When there are none, it's niche
        // mode: the AI designs a SEPARATE cluster for each selected site.
        $topics = $this->csvRows($batch) ?: $this->givenTitles($batch);

        $topics = $topics !== []
            ? $this->guardLocal($batch, $topics)
            : $this->clustersPerSite($batch);

        if ($topics === []) {
            throw new \RuntimeException('The plan produced no new topics — every title already exists on the target site(s) (or is too similar to an existing page). Add fresh title ideas or refine the niche.');
        }

        foreach ($topics as $topic) {
            $batch->items()->create([
                'row' => $topic,
                'status' => 'pending',
                // Which site this article was written for → per-site cost.
                'site_key' => NetworkTargets::siteKey($topic['site_ids'] ?? $batch->network_site_ids),
            ]);
        }

        if (empty($batch->link_catalog)) {
            // Default to the blog-only catalog unless the store module is on —
            // a pure blog must not build (or link into) a product catalog.
            $scope = $batch->link_scope ?: (ecommerce_enabled() ? 'ecommerce' : 'blog_only');
            $batch->link_catalog = $this->buildLinkCatalog($scope);
        }

        $batch->forceFill([
            'total_items' => count($topics),
            'topic_count' => count($topics),
        ])->save();

        $source = match (true) {
            $this->csvRows($batch) !== [] => 'from your CSV briefs',
            $this->givenTitles($batch) !== [] => 'from your title list',
            default => 'clustered by AI per selected site',
        };

        AiActivityLog::write($batch->id, null, 'plan',
            '🧠 Plan ready — '.count($topics)." article(s) {$source}.", 'success');

        return count($topics);
    }

    /**
     * Dedup + ranking-conflict guard for user-authored topics (CSV rows or
     * pasted titles), scoped to THIS site: drop any that already exist here,
     * or — with the store on — that would cannibalize a product/category page.
     * Drops are logged so nothing disappears silently.
     *
     * @param  array<int, array<string, string>>  $topics
     * @return array<int, array<string, string>>
     */
    protected function guardLocal(AiImportBatch $batch, array $topics): array
    {
        $existing = array_map(fn ($t) => mb_strtolower(trim($t)), Post::query()->pluck('title')->all());

        // Store style rule applies to planned titles/angles too.
        $topics = ContentReviewer::stripEmDashes($topics);

        $topics = array_values(array_filter(
            $topics,
            fn (array $t) => ! in_array(mb_strtolower(trim($t['name'])), $existing, true),
        ));

        $corpus = \App\Models\BlogTopicIdea::conflictCorpus();

        return array_values(array_filter($topics, function (array $t) use ($corpus, $batch) {
            $conflict = \App\Models\BlogTopicIdea::rankingConflict((string) $t['name'], $corpus);

            if ($conflict !== null) {
                AiActivityLog::write($batch->id, null, 'plan',
                    "✂️ Dropped \"{$t['name']}\" — too similar to \"".mb_substr($conflict, 0, 60).'" (would compete with it in search).', 'warning');

                return false;
            }

            return true;
        }));
    }

    /**
     * Niche mode, multisite: design a DISTINCT topic cluster for each selected
     * site, deduped against THAT site's existing posts, and stamp every article
     * with its target site so the writer publishes it only there. Off the
     * network (or local-only), this is a single cluster for this site.
     *
     * @return array<int, array<string, string>>
     */
    protected function clustersPerSite(AiImportBatch $batch): array
    {
        if (! trim((string) $batch->niche)) {
            throw new \RuntimeException('Give the batch a niche (or a list of title ideas) so the agent knows what to write about.');
        }

        $plan = NetworkTargets::resolve($batch->network_site_ids);

        $targets = $plan['local'] ? [NetworkTargets::LOCAL] : [];
        foreach ($plan['sites'] as $id) {
            $targets[] = (int) $id;
        }
        if ($targets === []) {
            $targets = [NetworkTargets::LOCAL];
        }

        $all = [];

        foreach ($targets as $target) {
            $isLocal = $target === NetworkTargets::LOCAL;
            $existing = $this->existingTitlesFor($target);
            $existingLower = array_map(fn ($t) => mb_strtolower(trim($t)), $existing);
            $label = $isLocal ? 'this site' : $this->siteLabel((int) $target);

            try {
                $cluster = ContentReviewer::stripEmDashes($this->clusterFromNiche($batch, $existing));
            } catch (\RuntimeException $e) {
                // One flaky site must not abort the whole batch — log and move on.
                AiActivityLog::write($batch->id, null, 'plan',
                    "⚠️ Could not plan for {$label}: ".$e->getMessage(), 'warning');

                continue;
            }

            // Dedup against this site's own posts.
            $cluster = array_values(array_filter(
                $cluster,
                fn (array $t) => ! in_array(mb_strtolower(trim($t['name'])), $existingLower, true),
            ));

            // Ranking-conflict guard only where we know the catalog (this site).
            if ($isLocal) {
                $corpus = \App\Models\BlogTopicIdea::conflictCorpus();
                $cluster = array_values(array_filter($cluster, function (array $t) use ($corpus, $batch) {
                    $conflict = \App\Models\BlogTopicIdea::rankingConflict((string) $t['name'], $corpus);

                    if ($conflict !== null) {
                        AiActivityLog::write($batch->id, null, 'plan',
                            "✂️ Dropped \"{$t['name']}\" — too similar to \"".mb_substr($conflict, 0, 60).'" (would compete with it in search).', 'warning');

                        return false;
                    }

                    return true;
                }));
            }

            foreach ($cluster as &$topic) {
                $topic['site_ids'] = (string) $target;
            }
            unset($topic);

            AiActivityLog::write($batch->id, null, 'plan',
                '🧭 Planned '.count($cluster)." article(s) for {$label}.", 'success');

            $all = array_merge($all, $cluster);
        }

        return $all;
    }

    /**
     * Existing article titles on the given target: local Posts for the `local`
     * sentinel, or the pulled mirror of a connected site's posts for a spoke ID.
     *
     * @return array<string>
     */
    protected function existingTitlesFor(int|string $target): array
    {
        if ($target === NetworkTargets::LOCAL) {
            return Post::query()->pluck('title')->all();
        }

        return \App\Models\NetworkRemotePost::query()
            ->where('site_id', (int) $target)
            ->pluck('title')->all();
    }

    protected function siteLabel(int $siteId): string
    {
        return (string) (\App\Models\ConnectedSite::query()->whereKey($siteId)->value('name') ?? "site #{$siteId}");
    }

    /**
     * CSV bulk-brief mode: one article per row. Recognized columns (aliases
     * accepted): title, keywords (comma-separated, first = primary), country,
     * city, niche, details, category. Every other column is passed through to
     * the writer as extra research context.
     *
     * @return array<int, array<string, string>>
     */
    protected function csvRows(AiImportBatch $batch): array
    {
        if (! $batch->csv_path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($batch->csv_path)) {
            return [];
        }

        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($batch->csv_path);

        $probe = fopen($path, 'r');
        $firstLine = (string) fgets($probe);
        fclose($probe);
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $aliases = [
            'name' => 'name', 'title' => 'name', 'blog_title' => 'name', 'post_title' => 'name', 'topic' => 'name',
            'keyword' => 'keywords', 'target_keyword' => 'keywords', 'target_keywords' => 'keywords', 'seo_keywords' => 'keywords', 'focus_keyword' => 'keywords',
            'detail' => 'details', 'notes' => 'details', 'brief' => 'details', 'description' => 'details', 'basic_detail' => 'details', 'basic_details' => 'details',
            'target_country' => 'country', 'target_city' => 'city', 'angle' => 'angle', 'outline' => 'outline',
            // Scheduling: date-only publishes at 00:00; a time column (or a
            // datetime in the date column) publishes at that exact time.
            'publish_date' => 'publish_date', 'publish_at' => 'publish_date', 'date' => 'publish_date',
            'publish_time' => 'publish_time', 'time' => 'publish_time',
            // Multisite: which connected sites this article also publishes to
            // ("2,5,34" or "all"). Overrides the batch-level default per row.
            'site_ids' => 'site_ids', 'sites' => 'site_ids', 'site_id' => 'site_ids', 'target_sites' => 'site_ids',
            // AI thumbnail: generate an image from the title? (yes/no). Optional
            // per-row custom prompt / style.
            'generate_image' => 'generate_image', 'gen_image' => 'generate_image', 'create_image' => 'generate_image',
            'make_image' => 'generate_image', 'thumbnail' => 'generate_image', 'ai_image' => 'generate_image',
            'image_prompt' => 'image_prompt', 'img_prompt' => 'image_prompt',
            'image_style' => 'image_style', 'img_style' => 'image_style',
            // Affiliate content: "Name | https://aff.link" entries separated by
            // ; or newlines. Presence marks the row as an affiliate article.
            'affiliate_links' => 'affiliate_links', 'affiliate' => 'affiliate_links', 'aff_links' => 'affiliate_links',
            'affiliate_products' => 'affiliate_links', 'product_links' => 'affiliate_links', 'affiliate_url' => 'affiliate_links',
            'content_type' => 'role', 'article_type' => 'role', 'type' => 'role',
        ];

        $handle = fopen($path, 'r');
        $headers = null;
        $topics = [];
        $seen = [];

        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($headers === null) {
                $headers = array_map(function ($h) use ($aliases) {
                    $key = str_replace([' ', '-'], '_', strtolower(trim(StartAiImportBatch::toUtf8(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)))));

                    return $aliases[$key] ?? $key;
                }, $cols);

                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                $value = trim(StartAiImportBatch::toUtf8((string) ($cols[$i] ?? '')));
                if ($value !== '') {
                    $row[$header] = $value;
                }
            }

            $title = trim((string) ($row['name'] ?? ''));
            $dedupe = mb_strtolower($title);

            if ($title === '' || isset($seen[$dedupe])) {
                continue;
            }

            $seen[$dedupe] = true;
            // An article with affiliate links is affiliate content unless the
            // CSV set an explicit role/type.
            if (! empty($row['affiliate_links']) && empty($row['role'])) {
                $row['role'] = 'affiliate';
            }
            $row['role'] = $row['role'] ?? 'article';
            $row['angle'] = $row['angle'] ?? 'Research the topic thoroughly and answer the search intent behind this exact title completely.';
            $topics[] = $row;
        }

        fclose($handle);

        return $topics;
    }

    /** @return array<int, array<string, string>> */
    protected function givenTitles(AiImportBatch $batch): array
    {
        return collect(preg_split('/\r?\n/', (string) $batch->topic_ideas))
            ->map(fn ($line) => trim($line, "- \t"))
            ->filter()
            ->unique(fn ($t) => mb_strtolower($t))
            ->values()
            ->map(fn (string $title) => [
                'name' => $title,
                'role' => 'article',
                'angle' => 'Answer the search intent behind this exact title completely.',
            ])
            ->all();
    }

    /**
     * One planning call: pillar-and-spoke cluster for the niche.
     *
     * @param  array<string>  $existingTitles
     * @return array<int, array<string, string>>
     */
    protected function clusterFromNiche(AiImportBatch $batch, array $existingTitles): array
    {
        if (! trim((string) $batch->niche)) {
            throw new \RuntimeException('Give the batch a niche (or a list of title ideas) so the agent knows what to write about.');
        }

        // Sensible default when the admin left the count blank (0/null) —
        // never collapse to a 1-topic plan by accident.
        $count = max(1, min(30, (int) ($batch->topic_count ?: 10)));

        $commerce = ecommerce_enabled();

        // Product context only when the store module is on — a pure blog
        // plans for topical authority, not commercial relevance.
        $productNames = $commerce
            ? Product::query()->where('status', 'published')
                ->orderByDesc('is_featured')->limit(40)->pluck('name')->implode('; ')
            : '';

        $intentMix = $commerce
            ? 'informational guides, comparisons ("X vs Y"), buying/choosing help, troubleshooting/how-to, local-intent where the targeting suggests it'
            : 'informational guides, how-tos, comparisons ("X vs Y"), beginner explainers, common questions/FAQs, and deeper "advanced" angles';

        $audience = $commerce ? "an ecommerce store's blog" : 'a content blog building topical authority to rank globally';

        $system = <<<SYS
You are an SEO content strategist designing a topic cluster (pillar-and-spoke) for {$audience}.
Rules:
- 1 PILLAR: the broad, high-volume guide covering the subject; the remaining topics are SPOKES: specific long-tail questions/comparisons/how-tos that each stand alone AND deepen one facet of the pillar.
- Every topic must have clear search intent a real person types, and must NOT duplicate or overlap another topic in the cluster or any existing article title provided.
- Mix intents across the cluster: {$intentMix}.
- Map keywords per topic: primary = the exact phrase to rank for; 2-4 secondary variations.
- Assign each topic a funnel_stage: "top" = awareness/informational, "middle" = comparison/consideration, "bottom" = decision/buying-guide. The PILLAR is usually "top"; spread spokes across all three with mostly top/middle and a few bottom.
- Working titles: specific and compelling, 70 chars or fewer, no clickbait, no colons-everywhere pattern, varied phrasing across the cluster.
Return ONLY JSON: {"topics": [{"title": "...", "role": "pillar"|"spoke", "funnel_stage": "top"|"middle"|"bottom", "primary_keyword": "...", "secondary_keywords": ["..."], "angle": "<one sentence: the specific take/promise of this article>", "outline": ["<4-7 section hints>"]}]}
SYS;

        $user = "SUBJECT AREA: ".trim($batch->niche)
            .(trim((string) $batch->prompt) !== '' ? "\n\nSITE / TOPIC BRIEF:\n".trim($batch->prompt) : '')
            .($batch->target_country || $batch->target_city
                ? "\nTARGETING: ".trim(($batch->target_city ? $batch->target_city.', ' : '').(string) $batch->target_country)
                : '')
            .($productNames ? "\n\nSTORE PRODUCTS (make spokes commercially relevant where natural):\n".$productNames : '')
            .($existingTitles !== [] ? "\n\nEXISTING ARTICLE TITLES (do not duplicate any):\n- ".implode("\n- ", array_slice($existingTitles, 0, 60)) : '')
            ."\n\nDesign the cluster now: exactly {$count} topics (1 pillar + ".($count - 1)." spokes).";

        $llm = LlmClient::for($batch->provider, $batch->model)->withContext('plan', $batch->id, null);

        $parsed = LlmClient::parseJson($llm->complete($system, $user, maxTokens: 8000));

        $topics = collect((array) ($parsed['topics'] ?? []))
            ->filter(fn ($t) => ! empty($t['title']))
            ->take($count)
            ->map(fn (array $t) => [
                'name' => trim((string) $t['title']),
                'role' => in_array($t['role'] ?? '', ['pillar', 'spoke'], true) ? $t['role'] : 'spoke',
                // A niche run IS one cluster (1 pillar + its spokes); stamp the
                // niche as the cluster name + carry the funnel stage so this
                // path is as cluster/funnel-aware as the Funnel Builder, and the
                // metadata survives onto the published Post.
                'cluster' => trim((string) $batch->niche),
                'funnel_stage' => in_array($t['funnel_stage'] ?? '', FunnelPlanner::STAGES, true) ? $t['funnel_stage'] : 'top',
                'primary_keyword' => trim((string) ($t['primary_keyword'] ?? '')),
                'keywords' => collect([(string) ($t['primary_keyword'] ?? '')])
                    ->merge((array) ($t['secondary_keywords'] ?? []))
                    ->map(fn ($k) => trim((string) $k))->filter()->unique()->implode(', '),
                'angle' => trim((string) ($t['angle'] ?? '')),
                'outline' => collect((array) ($t['outline'] ?? []))->map(fn ($s) => trim((string) $s))->filter()->implode(' | '),
            ])
            ->values()
            ->all();

        if ($topics === []) {
            throw new \RuntimeException('The planner returned no usable topics — try a more specific niche.');
        }

        return $topics;
    }

    /**
     * Everything the writer may link contextually, per site mode.
     *
     * ecommerce: products + product categories + posts + blog categories +
     * the home page (brand-anchor link). blog_only: posts + blog categories.
     * The lint rejects any URL not on this list, so the catalog IS the
     * whitelist.
     */
    /**
     * Cached: the catalog queries run once per content version, not per
     * batch. The key rides the pagecache version, which already bumps on
     * every Product/Post/Category/Page save — always fresh, zero extra
     * wiring, and every planner/writer/funnel consumer shares one entry.
     */
    /**
     * The site-scoped link catalog the writer/link-planner draws from.
     *
     * When $site is null the catalog is THIS install's own pages (hub/local).
     * When a connected spoke is given, the catalog is that SPOKE's own pages
     * with the spoke's real absolute URLs — pulled over the signed network API —
     * so an article written for a spoke links to that spoke's content, never the
     * hub's. Each entry is identity-enriched (kind/role/stage/cluster/money) so
     * {@see InternalLinkPlanner} can apply funnel rules.
     */
    public function buildLinkCatalog(string $scope = 'ecommerce', ?\App\Models\ConnectedSite $site = null): array
    {
        $version = (int) \Illuminate\Support\Facades\Cache::get('pagecache.version', 1);

        if ($site) {
            // Spoke catalogs are cached briefly and refreshed by the puller; keep
            // the TTL short so new spoke content becomes linkable quickly.
            return \Illuminate\Support\Facades\Cache::remember(
                "linkcatalog.site{$site->id}.v{$version}.{$scope}",
                now()->addHours(6),
                fn () => (new \App\Services\Network\NetworkLinkCatalogPuller)->catalog($site, $scope),
            );
        }

        return \Illuminate\Support\Facades\Cache::remember(
            "linkcatalog.v{$version}.{$scope}",
            now()->addDay(),
            fn () => $this->freshLinkCatalog($scope)
        );
    }

    protected function freshLinkCatalog(string $scope): array
    {
        $catalog = collect();

        if ($scope !== 'blog_only') {
            $catalog = $catalog
                ->concat(
                    Product::query()->where('status', 'published')
                        ->orderByDesc('is_featured')->limit(60)->get(['name', 'slug'])
                        ->map(fn (Product $p) => [
                            'name' => $p->name, 'url' => \App\Support\Permalinks::product($p->slug),
                            'kind' => 'product', 'role' => null, 'stage' => 'bottom', 'cluster' => null, 'money' => true,
                        ])
                )
                ->concat(
                    \App\Models\Category::query()->where('is_active', true)
                        ->orderBy('sort_order')->limit(20)->get(['name', 'slug'])
                        ->map(fn ($c) => [
                            'name' => $c->name.' (product category)', 'url' => \App\Support\Permalinks::category($c->slug),
                            'kind' => 'product_category', 'role' => null, 'stage' => 'bottom', 'cluster' => null, 'money' => true,
                        ])
                )
                ->push([
                    'name' => (string) setting('general.site_name', config('app.name')).' (home page)', 'url' => url('/'),
                    'kind' => 'home', 'role' => null, 'stage' => null, 'cluster' => null, 'money' => false,
                ]);
        }

        return $catalog
            ->concat(
                Post::query()->where('status', 'published')
                    ->latest('published_at')->limit(60)
                    ->get(['title', 'slug', 'content_role', 'funnel_stage', 'cluster'])
                    ->map(fn (Post $p) => [
                        'name' => $p->title, 'url' => route('blog.show', $p->slug),
                        'kind' => 'article',
                        'role' => $p->content_role ?: 'spoke',
                        'stage' => $p->funnel_stage ?: 'top',
                        'cluster' => $p->cluster,
                        'money' => false,
                    ])
            )
            ->concat(
                \App\Models\PostCategory::query()->orderBy('name')->limit(20)->get(['name', 'slug'])
                    ->map(fn ($c) => [
                        'name' => $c->name.' (blog category)', 'url' => route('blog.category', $c->slug),
                        'kind' => 'blog_category', 'role' => null, 'stage' => null, 'cluster' => null, 'money' => false,
                    ])
            )
            ->values()
            ->all();
    }
}
