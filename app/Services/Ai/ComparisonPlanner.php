<?php

namespace App\Services\Ai;

use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\Attribute;
use App\Models\BlogTopicIdea;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Semantic-SEO comparison content ("X vs Y"). Unlike FunnelPlanner, the
 * PAIRING here is fully deterministic — two products qualify only when they
 * share a category and differ on a real, structured facet (flavor family,
 * cooling level, tobacco strength). The LLM is used for exactly one thing:
 * writing a title/angle/outline for a pair already chosen by this class. It
 * never picks which products to compare.
 *
 * Survivors land in the SAME waiting area as FunnelPlanner
 * (blog_topic_ideas), with role=comparison and funnel_stage=middle
 * (comparisons are consideration-stage by definition) — the admin sends
 * them to the existing blog writer exactly like a funnel idea.
 */
class ComparisonPlanner
{
    /** Default facets that differentiate two products (overridable per store). */
    public const DIFFERENTIATOR_SLUGS = ['flavor-family', 'cooling-level', 'tobacco-strength'];

    /** Max comparison pairs proposed per category (avoids combinatorial blow-up). */
    public const MAX_PAIRS_PER_CATEGORY = 4;

    /**
     * The attribute slugs used to pair products for comparison articles.
     * Configurable in Content Strategy settings (comma or newline separated) so
     * the facets aren't hardcoded to one catalog; falls back to the defaults.
     *
     * @return array<int, string>
     */
    public static function differentiatorSlugs(): array
    {
        $raw = trim((string) setting('funnel.comparison_facets', ''));

        if ($raw === '') {
            return self::DIFFERENTIATOR_SLUGS;
        }

        $slugs = collect(preg_split('/[,\n]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($s) => \Illuminate\Support\Str::slug(trim($s)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $slugs !== [] ? $slugs : self::DIFFERENTIATOR_SLUGS;
    }

    public function run(AiImportBatch $batch): int
    {
        $pairs = $this->choosePairs();

        if ($pairs->isEmpty()) {
            AiActivityLog::write($batch->id, null, 'plan',
                'No comparison pairs found — need at least 2 published products in the same category differing on flavor family, cooling level, or tobacco strength.', 'warning');
            $batch->forceFill(['status' => 'completed', 'total_items' => 0, 'done_items' => 0])->save();

            return 0;
        }

        AiActivityLog::write($batch->id, null, 'plan', "🔍 Found {$pairs->count()} deterministic comparison pair(s) — writing angles…");

        $llm = LlmClient::for($batch->provider, $batch->model)->withContext('plan', $batch->id);
        $ideas = $this->writeAngles($llm, $pairs);

        $existing = Post::query()->pluck('title')->map(fn ($t) => trim((string) $t))->filter()->values()->all();
        $conflictCorpus = BlogTopicIdea::conflictCorpus(includePosts: false);

        $saved = 0;

        foreach ($ideas as $idea) {
            $title = trim((string) ($idea['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            if ($conflict = BlogTopicIdea::rankingConflict($title, array_merge($existing, $conflictCorpus))) {
                AiActivityLog::write($batch->id, null, 'plan',
                    "✂️ Dropped \"{$title}\" — too similar to \"".mb_substr($conflict, 0, 60).'" (would compete with it in search).', 'warning');

                continue;
            }

            $fingerprint = BlogTopicIdea::fingerprint($title);

            if (BlogTopicIdea::query()->where('fingerprint', $fingerprint)->exists()) {
                continue; // parked by an earlier run
            }

            BlogTopicIdea::create([
                'batch_id' => $batch->id,
                'title' => $title,
                'fingerprint' => $fingerprint,
                'cluster' => 'Comparisons',
                'role' => 'comparison',
                'funnel_stage' => 'middle',
                'primary_keyword' => $idea['primary_keyword'] ?? null,
                'secondary_keywords' => array_values(array_filter((array) ($idea['secondary_keywords'] ?? []))),
                'pain_point' => $idea['pain_point'] ?? null,
                'search_query' => $idea['search_query'] ?? null,
                'angle' => $idea['angle'] ?? null,
                'outline' => array_values((array) ($idea['outline'] ?? [])),
                // A comparison MUST link both compared product pages —
                // link_targets feeds sendToWriter's required_links field.
                'link_targets' => $idea['product_urls'],
                'compared_product_ids' => $idea['product_ids'],
                'verified_rounds' => 1,
                'status' => 'waiting',
            ]);
            $saved++;
        }

        $batch->forceFill(['status' => 'completed', 'total_items' => $saved, 'done_items' => $saved])->save();

        AiActivityLog::write($batch->id, null, 'plan',
            "🎉 Comparison research done — {$saved} pair(s) added to the waiting area.", 'success');

        return $saved;
    }

    /** @return Collection<int, array{products: array<int, Product>, facet: string}> */
    public function choosePairs(): Collection
    {
        $facetAttributeIds = Attribute::query()->whereIn('slug', self::differentiatorSlugs())->pluck('id');

        if ($facetAttributeIds->isEmpty()) {
            return collect();
        }

        $products = Product::query()->where('status', 'published')
            ->with(['categories:id,name', 'attributeValues.attribute'])
            ->orderByDesc('is_featured')->orderByDesc('is_best_seller')
            ->get();

        $byCategory = [];
        foreach ($products as $product) {
            foreach ($product->categories as $category) {
                $byCategory[$category->id]['products'][] = $product;
            }
        }

        $pairs = collect();
        $seenPairKeys = [];

        foreach ($byCategory as $group) {
            $groupProducts = $group['products'];

            if (count($groupProducts) < 2) {
                continue;
            }

            $countThisCategory = 0;

            foreach (self::differentiatorSlugs() as $slug) {
                if ($countThisCategory >= self::MAX_PAIRS_PER_CATEGORY) {
                    break;
                }

                $buckets = [];

                foreach ($groupProducts as $product) {
                    $value = $product->attributeValues->first(fn ($v) => $v->attribute?->slug === $slug);

                    if ($value) {
                        $buckets[$value->id][] = $product;
                    }
                }

                $bucketList = array_values($buckets);

                for ($i = 0; $i < count($bucketList) && $countThisCategory < self::MAX_PAIRS_PER_CATEGORY; $i++) {
                    for ($j = $i + 1; $j < count($bucketList) && $countThisCategory < self::MAX_PAIRS_PER_CATEGORY; $j++) {
                        $a = $bucketList[$i][0];
                        $b = $bucketList[$j][0];

                        $pairKey = collect([$a->id, $b->id])->sort()->implode('-');

                        if (isset($seenPairKeys[$pairKey])) {
                            continue;
                        }

                        $seenPairKeys[$pairKey] = true;
                        $pairs->push(['products' => [$a, $b], 'facet' => $slug]);
                        $countThisCategory++;
                    }
                }
            }
        }

        return $pairs->values();
    }

    /** @param  Collection<int, array{products: array<int, Product>, facet: string}>  $pairs */
    protected function writeAngles(LlmClient $llm, Collection $pairs): array
    {
        $system = <<<'SYS'
You are an SEO content strategist writing comparison-article briefs for an ecommerce blog. For EACH given product pair, propose ONE comparison article.
Return ONLY JSON:
{"ideas": [{"pair_index": <int, matches the input order, 0-based>,
 "title": "<e.g. \"TEREA Yellow vs Bronze: Which Should You Buy?\", <=70 chars, no em dashes>",
 "primary_keyword": "<exact phrase a buyer searches, e.g. \"terea yellow vs bronze\">",
 "secondary_keywords": ["<2-4 related phrasings buyers also search>"],
 "pain_point": "<the specific buyer hesitation this comparison resolves>",
 "search_query": "<the real phrase a person types>",
 "angle": "<one sentence: what this article helps the reader decide>",
 "outline": ["<4-6 section hints: e.g. flavor comparison, cooling and strength, who should pick which, verdict>"]}]}
Rules: ground every idea in the ACTUAL facet difference given for that pair — never invent a fact not given. Titles must be distinct from each other.
SYS;

        $user = "PRODUCT PAIRS (compare \"a\" vs \"b\" on the given differing facet):\n".$pairs->map(function (array $pair, int $i) {
            [$a, $b] = $pair['products'];
            $facetName = str_replace('-', ' ', $pair['facet']);
            $aValue = $a->attributeValues->first(fn ($v) => $v->attribute?->slug === $pair['facet'])?->value ?? '?';
            $bValue = $b->attributeValues->first(fn ($v) => $v->attribute?->slug === $pair['facet'])?->value ?? '?';

            return "{$i}. a=\"{$a->name}\" ({$facetName}: {$aValue}) vs b=\"{$b->name}\" ({$facetName}: {$bValue})";
        })->implode("\n");

        $parsed = LlmClient::parseJson($llm->complete($system, $user, maxTokens: 6000, cacheStatic: true));

        $ideas = [];

        foreach ((array) ($parsed['ideas'] ?? []) as $idea) {
            $index = (int) ($idea['pair_index'] ?? -1);

            if (! $pairs->has($index)) {
                continue;
            }

            $idea['title'] = str_replace(['—', '–'], ',', trim((string) ($idea['title'] ?? '')));
            $idea['product_ids'] = array_map(fn (Product $p) => $p->id, $pairs[$index]['products']);
            $idea['product_urls'] = array_map(fn (Product $p) => $p->url(), $pairs[$index]['products']);
            $ideas[] = $idea;
        }

        return $ideas;
    }
}
