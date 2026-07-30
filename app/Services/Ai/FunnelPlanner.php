<?php

namespace App\Services\Ai;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\BlogTopicIdea;
use App\Models\Post;
use App\Models\Product;

/**
 * The Content Cluster & Funnel Builder's research brain.
 *
 * Premise: on an ecommerce site the products and category pages ARE the
 * bottom of the funnel. This service researches those products, mines
 * customer pain points / real search queries / needs, designs topic
 * clusters, and generates top+middle funnel title ideas — each carrying
 * its full brief (keywords, angle, ToC outline, pain point, researched
 * internal link targets) into the waiting area (blog_topic_ideas).
 *
 * Nothing is written here. Writing happens later, when the admin selects
 * ideas and sends them to the existing blog writer engine.
 *
 * Verification: 3-5 rounds (admin-chosen). Each round runs the
 * DETERMINISTIC gate first (house rule: the LLM critic never blocks) —
 * fingerprint + Jaccard-similarity dedupe against existing posts, the
 * waiting area, and the round's own set (the canonical guard: a title too
 * close to an existing article is dropped, never suggested) — then an LLM
 * critique pass drops weak intents, and a regeneration call refills the
 * deficit with "avoid these" context. Only survivors are saved.
 */
class FunnelPlanner
{
    /** Titles at or above this token similarity to an existing post are canonical risks — dropped. */
    public const SIMILARITY_LIMIT = 0.6;

    /**
     * How many ideas one generation call asks for. Each idea is a rich object
     * (title, keywords, pain point, angle, 4-7 outline hints, link targets),
     * so 20 easily blew past the output budget and truncated the JSON
     * (stop_reason: max_tokens → unparseable → whole run failed). 10 per call
     * stays comfortably inside the budget.
     */
    public const CHUNK = 10;

    public function run(AiImportBatch $batch): int
    {
        $target = max(10, min(200, (int) $batch->topic_count ?: 100));
        $rounds = max(3, min(5, (int) $batch->funnel_rounds ?: 3));

        $llm = LlmClient::for($batch->provider, $batch->model)->withContext('plan', $batch->id);

        // ── Pass 1: store research + customer insight mining ─────────
        AiActivityLog::write($batch->id, null, 'plan', '🔎 Researching products and mining customer pain points…');
        $insights = $this->research($llm, $batch);

        // ── Pass 2: cluster & funnel design ──────────────────────────
        AiActivityLog::write($batch->id, null, 'plan', '🧩 Designing topic clusters and funnel stages…');
        $clusters = $this->designClusters($llm, $batch, $insights, $target);

        // ── Pass 3+: generate → verify (deterministic + LLM) → refill ─
        $existing = $this->existingTitles();
        $accepted = [];
        $rejectedTitles = [];

        for ($round = 1; $round <= $rounds; $round++) {
            $needed = $target - count($accepted);

            if ($needed > 0) {
                AiActivityLog::write($batch->id, null, 'plan',
                    "✍️ Round {$round}/{$rounds}: generating ".min($needed + 10, $needed + 20).' candidate titles…');

                $candidates = $this->generateCandidates($llm, $batch, $insights, $clusters, $needed, $accepted, $rejectedTitles, $existing);
            } else {
                $candidates = [];
            }

            // Deterministic gate — canonical guard + structural checks.
            [$passed, $rejected] = $this->deterministicGate(array_merge($accepted, $candidates), $existing, $batch);
            $rejectedTitles = array_merge($rejectedTitles, array_column($rejected, 'title'));

            // LLM critique: intent real? funnel stage right? overlaps a sibling?
            $passed = $this->critique($llm, $batch, $passed, $existing, $round);

            $accepted = array_slice($passed, 0, $target);

            foreach ($accepted as &$idea) {
                $idea['verified_rounds'] = ($idea['verified_rounds'] ?? 0) + 1;
            }
            unset($idea);

            AiActivityLog::write($batch->id, null, 'plan',
                "✅ Round {$round}/{$rounds}: ".count($accepted)."/{$target} titles verified.");

            $batch->forceFill(['done_items' => count($accepted)])->save();

            // Enough verified ideas in hand — stop instead of burning more
            // generation + critic passes on rounds we no longer need. The
            // rounds setting is an UPPER bound: a run that CANNOT reach the
            // target still uses every round trying to refill.
            if (count($accepted) >= $target) {
                if ($round < $rounds) {
                    AiActivityLog::write($batch->id, null, 'plan',
                        "🎯 Target of {$target} reached in {$round} round(s) — stopping early to save tokens.", 'success');
                }

                break;
            }
        }

        if ($accepted === []) {
            throw new \RuntimeException('Research produced no titles that survived verification — every candidate was too close to existing content. Refine the niche or grow the catalog.');
        }

        // ── Save survivors into the waiting area ─────────────────────
        $saved = 0;
        foreach ($accepted as $idea) {
            $fingerprint = BlogTopicIdea::fingerprint($idea['title']);

            if (BlogTopicIdea::query()->where('fingerprint', $fingerprint)->exists()) {
                continue; // parked by an earlier research run
            }

            BlogTopicIdea::create([
                'batch_id' => $batch->id,
                'title' => $idea['title'],
                'fingerprint' => $fingerprint,
                'cluster' => $idea['cluster'] ?? 'General',
                'role' => in_array($idea['role'] ?? '', ['pillar', 'spoke'], true) ? $idea['role'] : 'spoke',
                'funnel_stage' => in_array($idea['funnel_stage'] ?? '', ['top', 'middle'], true) ? $idea['funnel_stage'] : 'top',
                'primary_keyword' => $idea['primary_keyword'] ?? null,
                'secondary_keywords' => array_values((array) ($idea['secondary_keywords'] ?? [])),
                'pain_point' => $idea['pain_point'] ?? null,
                'search_query' => $idea['search_query'] ?? null,
                'audience_need' => $idea['audience_need'] ?? null,
                'angle' => $idea['angle'] ?? null,
                'outline' => array_values((array) ($idea['outline'] ?? [])),
                'link_targets' => array_values((array) ($idea['link_targets'] ?? [])),
                'verified_rounds' => (int) ($idea['verified_rounds'] ?? $rounds),
                'status' => 'waiting',
            ]);
            $saved++;
        }

        $batch->forceFill(['status' => 'completed', 'total_items' => $saved, 'done_items' => $saved])->save();

        AiActivityLog::write($batch->id, null, 'plan',
            "🎉 Funnel research done — {$saved} verified title idea(s) added to the waiting area.", 'success');

        return $saved;
    }

    // ── Pass 1: research ─────────────────────────────────────────────

    protected function research(LlmClient $llm, AiImportBatch $batch): array
    {
        $products = Product::query()->where('status', 'published')
            ->orderByDesc('is_featured')->limit(80)
            ->get(['name', 'price'])
            ->map(fn (Product $p) => $p->name.($p->price ? ' ('.$p->price.')' : ''))
            ->implode('; ');

        $categories = \App\Models\Category::query()->where('is_active', true)
            ->orderBy('sort_order')->limit(25)->pluck('name')->implode('; ');

        $system = <<<'SYS'
You are an ecommerce customer-research analyst. From the store's catalog and brief, produce a compact research dossier.
Return ONLY JSON:
{"store_summary": "<2-3 sentences: what is sold, to whom, differentiators>",
 "audiences": ["<distinct buyer types>"],
 "pain_points": [{"pain": "<specific problem/doubt/confusion a real customer has BEFORE or WHILE buying>", "affects": "<who>", "product_area": "<which products it relates to>"}],
 "queries": ["<real search phrases people type at TOP and MIDDLE of the funnel — questions, comparisons, how-tos, NOT product names alone>"],
 "needs": ["<underlying customer needs and jobs-to-be-done>"]}
Rules: 10-20 pain points, 15-30 queries, grounded strictly in the actual catalog given. Specific beats generic. No invented products.
SYS;

        $user = 'STORE BRIEF:\n'.trim((string) $batch->prompt)
            .($batch->niche ? "\n\nFOCUS NICHE:\n".trim($batch->niche) : '')
            ."\n\nPUBLISHED PRODUCTS:\n".$products
            ."\n\nPRODUCT CATEGORIES:\n".$categories
            .($batch->target_country ? "\n\nTARGET MARKET: ".trim(($batch->target_city ? $batch->target_city.', ' : '').$batch->target_country) : '');

        return LlmClient::parseJson($llm->complete($system, $user, maxTokens: 6000, cacheStatic: true));
    }

    // ── Pass 2: clusters ─────────────────────────────────────────────

    protected function designClusters(LlmClient $llm, AiImportBatch $batch, array $insights, int $target): array
    {
        $clusterCount = max(3, min(12, (int) ceil($target / 12)));

        $bofu = collect((new BlogPlanner)->buildLinkCatalog('ecommerce'))
            ->filter(fn ($l) => ! str_contains($l['name'], '(blog category)'))
            ->take(60)
            ->map(fn ($l) => $l['name'].' => '.$l['url'])
            ->implode("\n");

        $system = <<<'SYS'
You are an SEO content strategist. Design topic CLUSTERS for an ecommerce blog. The store's product and category pages are the BOTTOM of the funnel — the clusters must generate TOP funnel (educational, awareness) and MIDDLE funnel (comparison, consideration, choosing help) articles that funnel readers toward those pages.
Return ONLY JSON:
{"clusters": [{"name": "<short cluster name>", "theme": "<what this cluster covers and for whom>", "pillar_focus": "<the middle-funnel pillar topic anchoring the cluster>", "bofu_targets": ["<urls from the provided list this cluster should funnel into>"]}]}
Rules: each cluster maps to real pain points and queries from the dossier; clusters must not overlap each other; every cluster names 2-5 bofu_targets chosen ONLY from the provided URL list. When a PRODUCT FACET TAXONOMY is provided, prefer anchoring clusters on real facet axes (e.g. a cooling-level guide cluster, a strength-tier cluster, an origin-comparison cluster) — facet-driven clusters map to how buyers actually narrow their choice.
SYS;

        // The structured facet taxonomy is the store's real semantic map —
        // giving it to the cluster designer yields facet-driven clusters
        // (strength tiers, cooling levels, origins) instead of clusters
        // guessed from product names alone.
        $taxonomy = ProductWriter::attributeVocabulary();

        $user = 'RESEARCH DOSSIER:\n'.json_encode($insights, JSON_UNESCAPED_UNICODE)
            ."\n\nBOTTOM-FUNNEL PAGES (choose bofu_targets from these URLs only):\n".$bofu
            .($taxonomy !== '' ? "\n\nPRODUCT FACET TAXONOMY (candidate cluster axes — attribute: values):\n".$taxonomy : '')
            ."\n\nDesign exactly {$clusterCount} clusters now.";

        $parsed = LlmClient::parseJson($llm->complete($system, $user, maxTokens: 6000, cacheStatic: true));

        $clusters = array_values(array_filter((array) ($parsed['clusters'] ?? []), fn ($c) => ! empty($c['name'])));

        if ($clusters === []) {
            throw new \RuntimeException('Cluster design returned nothing usable.');
        }

        return $clusters;
    }

    // ── Pass 3: candidate generation ─────────────────────────────────

    protected function generateCandidates(LlmClient $llm, AiImportBatch $batch, array $insights, array $clusters, int $needed, array $accepted, array $rejectedTitles, array $existing): array
    {
        $candidates = [];
        $ask = $needed + (int) ceil($needed * 0.25); // overshoot: the gates will cut

        $catalogUrls = collect((new BlogPlanner)->buildLinkCatalog('ecommerce'))->pluck('url')->all();

        $system = <<<'SYS'
You are an SEO content strategist generating blog TITLE IDEAS with full briefs for an ecommerce content funnel.
Return ONLY JSON:
{"ideas": [{"title": "<specific, compelling, <=70 chars, no clickbait, varied phrasing>",
 "cluster": "<one of the given cluster names>",
 "role": "pillar"|"spoke",
 "funnel_stage": "top"|"middle",
 "primary_keyword": "<exact phrase to rank for>",
 "secondary_keywords": ["<2-4 variations>"],
 "pain_point": "<the specific customer pain this answers>",
 "search_query": "<the real phrase a person types>",
 "audience_need": "<what the reader is trying to achieve>",
 "angle": "<one sentence: this article's specific take/promise>",
 "outline": ["<4-7 section hints (the table-of-contents idea)>"],
 "link_targets": ["<1-4 URLs from the provided list this article should link to>"]}]}
Rules:
- TOP funnel = educate/answer/awareness (no selling). MIDDLE funnel = compare/choose/evaluate. Products themselves are bottom funnel — never write a title that is just a product pitch.
- Every idea grounded in a real pain point or query from the dossier. Search intent must be something a real person types.
- CANONICAL GUARD: never propose a title whose topic/intent is the same as, similar to, or overlapping ANY title in the EXISTING or REJECTED lists — even reworded. If in doubt, skip the topic entirely.
- link_targets: choose ONLY from the provided URL list.
- Titles must be distinct from each other in topic, not just wording. No em dashes anywhere.
SYS;

        $user = 'RESEARCH DOSSIER:\n'.json_encode($insights, JSON_UNESCAPED_UNICODE)
            ."\n\nCLUSTERS:\n".json_encode($clusters, JSON_UNESCAPED_UNICODE)
            ."\n\nLINKABLE URLS:\n".implode("\n", array_slice($catalogUrls, 0, 80))
            ."\n\nEXISTING ARTICLE TITLES (canonical guard — never overlap these):\n- ".implode("\n- ", array_slice($existing, 0, 80))
            .($accepted !== [] ? "\n\nALREADY ACCEPTED THIS RUN (do not repeat or overlap):\n- ".implode("\n- ", array_column($accepted, 'title')) : '')
            .($rejectedTitles !== [] ? "\n\nREJECTED EARLIER (do not retry these topics):\n- ".implode("\n- ", array_slice($rejectedTitles, -40)) : '');

        foreach (array_chunk(range(1, $ask), self::CHUNK) as $chunk) {
            // A single truncated/invalid chunk must NOT fail the whole run —
            // log it and keep the ideas already gathered; later rounds refill
            // any deficit. Only a total wipe-out (zero candidates) surfaces.
            try {
                $parsed = LlmClient::parseJson($llm->complete(
                    $system,
                    $user."\n\nGenerate exactly ".count($chunk).' ideas now, spread across the clusters and funnel stages (roughly half top, half middle).',
                    maxTokens: 16000,
                    cacheStatic: true,
                ));
            } catch (\Throwable $e) {
                AiActivityLog::write($batch->id, null, 'plan',
                    '⚠️ A batch of candidate titles came back unusable ('.mb_substr($e->getMessage(), 0, 120).') — keeping the rest and continuing.', 'warning');

                continue;
            }

            foreach ((array) ($parsed['ideas'] ?? []) as $idea) {
                if (! empty($idea['title'])) {
                    $idea['title'] = str_replace(['—', '–'], ',', trim((string) $idea['title']));
                    $candidates[] = $idea;
                }
            }

            if (count($candidates) >= $ask) {
                break;
            }
        }

        return $candidates;
    }

    // ── Deterministic gate (the blocking one) ────────────────────────

    /**
     * @return array{0: array<int, array>, 1: array<int, array>} [passed, rejected]
     */
    public function deterministicGate(array $candidates, array $existing, ?AiImportBatch $batch = null): array
    {
        $passed = [];
        $rejected = [];
        $seenFingerprints = [];

        // Normalized path => canonical catalog URL. Matching on the path
        // (origin + trailing slash stripped) recovers the URLs a model
        // routinely returns in a slightly different shape ("/terea-amber"
        // vs "https://site/terea-amber/") instead of discarding them.
        $catalogByPath = [];
        foreach (collect((new BlogPlanner)->buildLinkCatalog('ecommerce'))->pluck('url') as $url) {
            $catalogByPath[$this->normalizeUrlPath((string) $url)] = (string) $url;
        }

        // Waiting-area ideas + product names + category names — a blog title
        // must never compete with an existing article OR a money page.
        $conflictCorpus = BlogTopicIdea::conflictCorpus(includePosts: false);

        foreach ($candidates as $idea) {
            $title = trim((string) ($idea['title'] ?? ''));

            $reject = function (string $reason) use (&$rejected, $idea, $title, $batch): void {
                $rejected[] = $idea + ['reject_reason' => $reason];
                if ($batch) {
                    AiActivityLog::write($batch->id, null, 'plan', "✂️ Dropped \"{$title}\" — {$reason}.", 'warning');
                }
            };

            if ($title === '' || mb_strlen($title) > 75) {
                $reject('empty or over 75 characters');

                continue;
            }

            // Canonical guard #1: exact/reshuffled duplicate (fingerprint).
            $fingerprint = BlogTopicIdea::fingerprint($title);
            if (isset($seenFingerprints[$fingerprint])) {
                $reject('duplicate of another candidate this run');

                continue;
            }

            // Canonical guard #2: near-duplicate of an existing post, a
            // parked idea, or a PRODUCT/CATEGORY page — similar titles
            // cannibalize articles and compete with money pages; the
            // existing page stays canonical and this title is never suggested.
            if ($conflict = BlogTopicIdea::rankingConflict($title, array_merge($existing, $conflictCorpus), self::SIMILARITY_LIMIT)) {
                $reject('too similar to "'.mb_substr($conflict, 0, 60).'" (would compete with it in search)');

                continue;
            }

            if (trim((string) ($idea['primary_keyword'] ?? '')) === '') {
                $reject('no primary keyword');

                continue;
            }

            if (! in_array($idea['funnel_stage'] ?? '', ['top', 'middle'], true)) {
                $reject('funnel stage must be top or middle');

                continue;
            }

            // Map each suggested target to a real catalog URL by path.
            // Invalid guesses are dropped, but a missing target NEVER kills
            // the idea — the link agent attaches internal links when the
            // article is actually written, so a good title is still worth
            // parking. (Previously an empty list rejected the whole idea,
            // which silently discarded almost every candidate on a large
            // catalog whose URL shapes the model couldn't reproduce.)
            $mapped = [];
            foreach ((array) ($idea['link_targets'] ?? []) as $url) {
                $canonical = $catalogByPath[$this->normalizeUrlPath((string) $url)] ?? null;
                if ($canonical !== null) {
                    $mapped[$canonical] = true;
                }
            }
            $idea['link_targets'] = array_keys($mapped);

            if (count((array) ($idea['outline'] ?? [])) < 3) {
                $reject('outline too thin (need 3+ sections)');

                continue;
            }

            $seenFingerprints[$fingerprint] = true;
            $idea['title'] = $title;
            $passed[] = $idea;
        }

        return [$passed, $rejected];
    }

    // ── LLM critique (advisory second layer) ─────────────────────────

    protected function critique(LlmClient $llm, AiImportBatch $batch, array $ideas, array $existing, int $round): array
    {
        if ($ideas === []) {
            return [];
        }

        $system = <<<'SYS'
You are a ruthless SEO editor reviewing blog title ideas before they enter the writing queue.
For each idea judge: (1) is the search intent REAL (a person actually types this)? (2) is the funnel stage correct? (3) does it overlap/compete with ANY existing article title or another idea in this list (canonical risk — even a similar topic in different words fails)?
Return ONLY JSON: {"verdicts": [{"title": "<exact title>", "keep": true|false, "reason": "<short>"}]}
Judge every title. When uncertain about overlap, keep=false.
SYS;

        $user = "EXISTING ARTICLE TITLES:\n- ".implode("\n- ", array_slice($existing, 0, 80))
            ."\n\nIDEAS TO JUDGE:\n".json_encode(
                array_map(fn ($i) => ['title' => $i['title'], 'funnel_stage' => $i['funnel_stage'] ?? '', 'search_query' => $i['search_query'] ?? ''], $ideas),
                JSON_UNESCAPED_UNICODE
            );

        try {
            $parsed = LlmClient::parseJson($llm->complete($system, $user, maxTokens: 6000));
        } catch (\Throwable $e) {
            // Critic failure never blocks — deterministic gate already ran.
            AiActivityLog::write($batch->id, null, 'plan', '⚠️ Critique pass failed ('.mb_substr($e->getMessage(), 0, 120).') — keeping deterministic survivors.', 'warning');

            return $ideas;
        }

        $drops = collect((array) ($parsed['verdicts'] ?? []))
            ->filter(fn ($v) => ($v['keep'] ?? true) === false)
            ->keyBy(fn ($v) => mb_strtolower(trim((string) ($v['title'] ?? ''))));

        return array_values(array_filter($ideas, function ($idea) use ($drops, $batch) {
            $verdict = $drops->get(mb_strtolower(trim($idea['title'])));
            if ($verdict) {
                AiActivityLog::write($batch->id, null, 'plan',
                    "🧐 Critic dropped \"{$idea['title']}\" — ".($verdict['reason'] ?? 'weak intent').'.', 'warning');

                return false;
            }

            return true;
        }));
    }

    /** Path portion of a URL, lowercased, no origin, no trailing slash — for tolerant catalog matching. */
    protected function normalizeUrlPath(string $url): string
    {
        $url = trim($url);
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $url; // already a bare path, or unparseable
        }

        return '/'.mb_strtolower(trim($path, '/'));
    }

    protected function existingTitles(): array
    {
        return Post::query()->pluck('title')->map(fn ($t) => trim((string) $t))->filter()->values()->all();
    }
}
