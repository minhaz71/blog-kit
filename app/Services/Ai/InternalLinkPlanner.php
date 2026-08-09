<?php

namespace App\Services\Ai;

/**
 * The internal-linking RULES engine — the "link agent".
 *
 * Every article carries a hidden identity: a funnel STAGE (top / middle /
 * bottom) and a content ROLE (pillar / spoke), inside a topic CLUSTER. Money
 * pages (products, product categories, and configured BOFU targets) are the
 * conversion destinations. Given the article being written and the enriched
 * link catalog for its target site, this class picks WHICH pages to link to and
 * WHY, following standard SEO silo/funnel rules so link equity flows correctly:
 *
 *   • Spoke → Pillar (up): every non-pillar article links up to its cluster
 *     pillar (the hub of the silo).
 *   • Pillar → Spokes (down): a pillar links down to its strongest spokes.
 *   • Informational → Money (down-funnel): top/middle articles pass equity to a
 *     bottom/money page in the same cluster — guiding the reader to convert.
 *   • Bottom/Money → up + support: a bottom page links back to its pillar and a
 *     couple of supporting middle articles, but keeps outbound links minimal.
 *   • Lateral siblings: a couple of same-cluster peers where genuinely relevant.
 *   • Cross-cluster only via pillars: never spray links across silos; reach
 *     another cluster through its pillar.
 *
 * The catalog is site-scoped (local OR a connected spoke's own pages with the
 * spoke's real URLs — see {@see BlogPlanner::buildLinkCatalog()}), so the links
 * an article gets are always for the site it is being written for.
 *
 * A catalog ENTRY is:
 *   ['name'=>string, 'url'=>string, 'kind'=>string, 'role'=>?string,
 *    'stage'=>?string, 'cluster'=>?string, 'money'=>bool]
 * kind ∈ article|blog_category|product|product_category|home
 */
class InternalLinkPlanner
{
    /** Hard ceiling on internal links suggested per article. */
    public const MAX_LINKS = 6;

    /**
     * Plan the internal links for one article.
     *
     * @param  array{role?:?string,stage?:?string,cluster?:?string,url?:?string,primary_keyword?:?string}  $subject
     * @param  array<int,array<string,mixed>>  $catalog
     * @return array{targets: array<int,array{url:string,name:string,why:string}>, brief: string}
     */
    public function plan(array $subject, array $catalog): array
    {
        $role = $subject['role'] ?? 'spoke';
        $stage = $subject['stage'] ?? 'top';
        $cluster = $subject['cluster'] ?? null;
        $selfUrl = $subject['url'] ?? null;

        // Never link an article to itself.
        $pool = array_values(array_filter($catalog, fn ($e) => ! empty($e['url']) && ($e['url'] !== $selfUrl)));

        $sameCluster = $cluster
            ? array_values(array_filter($pool, fn ($e) => ($e['cluster'] ?? null) === $cluster))
            : [];

        $chosen = [];      // url => ['url','name','why']
        $add = function (?array $e, string $why) use (&$chosen) {
            if ($e && ! isset($chosen[$e['url']]) && count($chosen) < self::MAX_LINKS) {
                $chosen[$e['url']] = ['url' => $e['url'], 'name' => (string) $e['name'], 'why' => $why];
            }
        };

        // 1. UP — link to the cluster pillar (or, if none in-cluster, the
        //    nearest cross-cluster pillar as the bridge between silos).
        if ($role !== 'pillar') {
            $pillar = $this->first($sameCluster, fn ($e) => ($e['role'] ?? null) === 'pillar')
                ?? $this->first($pool, fn ($e) => ($e['role'] ?? null) === 'pillar');
            $add($pillar, 'up_to_pillar');
        }

        // 2. DOWN — a pillar seeds links to its own spokes (top then middle).
        if ($role === 'pillar') {
            foreach ($this->byStageOrder($sameCluster, ['top', 'middle', 'bottom'], excludePillar: true) as $e) {
                $add($e, 'pillar_to_spoke');
            }
        }

        // 3. DOWN-FUNNEL — informational articles pass equity to money/bottom
        //    pages in the same cluster (fallback: any money page site-wide).
        if (in_array($stage, ['top', 'middle'], true)) {
            $money = $this->first($sameCluster, fn ($e) => $this->isMoney($e))
                ?? $this->first($pool, fn ($e) => $this->isMoney($e));
            $add($money, 'down_to_money');
        }

        // 4. Bottom/money page — link up (pillar, done) + a couple of supporting
        //    middle articles; deliberately few outbound links.
        if ($stage === 'bottom' || $this->isMoney($subject)) {
            foreach (array_slice($this->byStageOrder($sameCluster, ['middle', 'top'], excludePillar: true), 0, 2) as $e) {
                $add($e, 'support');
            }
        }

        // 5. LATERAL — a couple of same-cluster siblings for cohesion.
        foreach (array_slice($this->byStageOrder($sameCluster, ['top', 'middle', 'bottom'], excludePillar: true), 0, 3) as $e) {
            $add($e, 'sibling');
        }

        // 6. HOME — at most once, only if it exists in the catalog.
        if (count($chosen) < self::MAX_LINKS) {
            $add($this->first($pool, fn ($e) => ($e['kind'] ?? null) === 'home'), 'home');
        }

        $targets = array_values($chosen);

        return ['targets' => $targets, 'brief' => $this->brief($targets)];
    }

    /** Human-readable per-article link brief for the writer prompt. */
    public function brief(array $targets): string
    {
        if ($targets === []) {
            return '';
        }

        $why = [
            'up_to_pillar' => 'link UP to the cluster pillar (context/authority)',
            'pillar_to_spoke' => 'link DOWN to this supporting article',
            'down_to_money' => 'guide the reader DOWN-FUNNEL to this conversion page',
            'support' => 'reference this supporting article',
            'sibling' => 'cross-link this related article where it fits',
            'home' => 'a single brand/site mention may link home',
        ];

        return collect($targets)
            ->map(fn ($t) => '- '.$t['name'].' — '.$t['url'].'  ['.($why[$t['why']] ?? 'link where relevant').']')
            ->implode("\n");
    }

    // ── helpers ──────────────────────────────────────────────────────────

    protected function isMoney(array $e): bool
    {
        return ! empty($e['money'])
            || in_array($e['kind'] ?? '', ['product', 'product_category'], true)
            || ($e['stage'] ?? null) === 'bottom';
    }

    protected function first(array $list, callable $pred): ?array
    {
        foreach ($list as $e) {
            if ($pred($e)) {
                return $e;
            }
        }

        return null;
    }

    /**
     * Entries ordered by the given stage priority (articles only), optionally
     * dropping the pillar.
     *
     * @param  array<int,string>  $order
     * @return array<int,array<string,mixed>>
     */
    protected function byStageOrder(array $list, array $order, bool $excludePillar = false): array
    {
        $articles = array_filter($list, function ($e) use ($excludePillar) {
            if (($e['kind'] ?? 'article') !== 'article') {
                return false;
            }

            return ! ($excludePillar && ($e['role'] ?? null) === 'pillar');
        });

        usort($articles, function ($a, $b) use ($order) {
            $ai = array_search($a['stage'] ?? '', $order, true);
            $bi = array_search($b['stage'] ?? '', $order, true);
            $ai = $ai === false ? 99 : $ai;
            $bi = $bi === false ? 99 : $bi;

            return $ai <=> $bi;
        });

        return array_values($articles);
    }
}
