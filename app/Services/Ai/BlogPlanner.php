<?php

namespace App\Services\Ai;

use App\Jobs\StartAiImportBatch;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\Post;
use App\Models\Product;

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
        $existingTitles = Post::query()->pluck('title')->map(fn ($t) => mb_strtolower(trim($t)))->all();

        $topics = $this->csvRows($batch)
            ?: $this->givenTitles($batch)
            ?: $this->clusterFromNiche($batch, $existingTitles);

        // Store style rule applies to planned titles/angles too — they feed
        // straight into article titles and prompts.
        $topics = ContentReviewer::stripEmDashes($topics);

        // Never write an article the blog already has.
        $topics = array_values(array_filter(
            $topics,
            fn (array $t) => ! in_array(mb_strtolower(trim($t['name'])), $existingTitles, true),
        ));

        // Ranking-conflict guard (all modes, including CSV and pasted
        // titles): a reworded near-duplicate of an existing article
        // cannibalizes it, and a title too close to a PRODUCT or CATEGORY
        // page would compete with your own money pages. Drops are logged so
        // nothing disappears silently.
        $corpus = \App\Models\BlogTopicIdea::conflictCorpus();
        $topics = array_values(array_filter($topics, function (array $t) use ($corpus, $batch) {
            $conflict = \App\Models\BlogTopicIdea::rankingConflict((string) $t['name'], $corpus);

            if ($conflict !== null) {
                AiActivityLog::write($batch->id, null, 'plan',
                    "✂️ Dropped \"{$t['name']}\" — too similar to \"".mb_substr($conflict, 0, 60).'" (would compete with it in search).', 'warning');

                return false;
            }

            return true;
        }));

        if ($topics === []) {
            throw new \RuntimeException('The plan produced no new topics — every title already exists on the blog (or is too similar to an existing page). Add fresh title ideas or refine the niche.');
        }

        foreach ($topics as $topic) {
            $batch->items()->create(['row' => $topic, 'status' => 'pending']);
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
            default => 'clustered by AI around the niche',
        };

        AiActivityLog::write($batch->id, null, 'plan',
            '🧠 Plan ready — '.count($topics)." article(s) {$source}.", 'success');

        return count($topics);
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
- Working titles: specific and compelling, 70 chars or fewer, no clickbait, no colons-everywhere pattern, varied phrasing across the cluster.
Return ONLY JSON: {"topics": [{"title": "...", "role": "pillar"|"spoke", "primary_keyword": "...", "secondary_keywords": ["..."], "angle": "<one sentence: the specific take/promise of this article>", "outline": ["<4-7 section hints>"]}]}
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
    public function buildLinkCatalog(string $scope = 'ecommerce'): array
    {
        $version = (int) \Illuminate\Support\Facades\Cache::get('pagecache.version', 1);

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
                        ->map(fn (Product $p) => ['name' => $p->name, 'url' => \App\Support\Permalinks::product($p->slug)])
                )
                ->concat(
                    \App\Models\Category::query()->where('is_active', true)
                        ->orderBy('sort_order')->limit(20)->get(['name', 'slug'])
                        ->map(fn ($c) => ['name' => $c->name.' (product category)', 'url' => \App\Support\Permalinks::category($c->slug)])
                )
                ->push(['name' => (string) setting('general.site_name', config('app.name')).' (home page)', 'url' => url('/')]);
        }

        return $catalog
            ->concat(
                Post::query()->where('status', 'published')
                    ->latest('published_at')->limit(40)->get(['title', 'slug'])
                    ->map(fn (Post $p) => ['name' => $p->title, 'url' => route('blog.show', $p->slug)])
            )
            ->concat(
                \App\Models\PostCategory::query()->orderBy('name')->limit(20)->get(['name', 'slug'])
                    ->map(fn ($c) => ['name' => $c->name.' (blog category)', 'url' => route('blog.category', $c->slug)])
            )
            ->values()
            ->all();
    }
}
