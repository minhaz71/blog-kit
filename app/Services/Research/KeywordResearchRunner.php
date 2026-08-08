<?php

namespace App\Services\Research;

use App\Models\KeywordResearchRun;
use App\Models\KeywordResearchTerm;
use Illuminate\Support\Str;

/**
 * Turns a research run's seeds into a scored, clustered, funnel-staged keyword
 * plan:
 *   discover → store terms → classify intent + funnel stage → cluster
 *   (SERP-overlap when DataForSEO is set, else topic-token heuristic) → assign
 *   pillar/spoke roles → score opportunity.
 *
 * Everything downstream (Create plan → blog_topic_ideas → writer → linker →
 * category) consumes the terms this produces.
 */
class KeywordResearchRunner
{
    /** How many top terms to pull live SERPs for (cost guard on the paid path). */
    protected const SERP_BUDGET = 40;

    /** Tokens too generic to define a cluster. */
    protected const STOP = ['the', 'a', 'an', 'to', 'for', 'of', 'in', 'on', 'and', 'or', 'with', 'your', 'you', 'how', 'what', 'why', 'when', 'where', 'which', 'is', 'are', 'can', 'do', 'does', 'best', 'top', 'vs', 'near', 'me', 'my', 'i', 'guide', 'tips'];

    public function __construct(protected KeywordResearch $research = new KeywordResearch) {}

    public function run(KeywordResearchRun $run): KeywordResearchRun
    {
        $run->update(['status' => 'researching', 'notes' => null]);

        try {
            $seeds = array_values(array_filter((array) $run->seeds));
            $opts = array_filter([
                'language' => $run->target_language,
                'country' => $run->target_country,
                'location_code' => $run->location_code,
                'target' => 400,
            ], fn ($v) => $v !== null && $v !== '');

            $terms = $this->research->discover($seeds, $opts);

            if ($terms === []) {
                $run->update(['status' => 'failed', 'notes' => 'No keywords discovered. Check the provider/credentials or try broader seeds.']);

                return $run;
            }

            // Persist (fresh set per run) with intent + funnel stage.
            $run->terms()->delete();
            $rows = [];
            foreach ($terms as $t) {
                $intent = $t['intent'] ?: $this->classifyIntent($t['keyword']);
                $rows[] = [
                    'keyword' => Str::limit((string) $t['keyword'], 190, ''),
                    'normalized' => KeywordResearchTerm::normalize((string) $t['keyword']),
                    'source' => $t['source'] ?? 'related',
                    'volume' => $t['volume'],
                    'difficulty' => $t['difficulty'],
                    'cpc' => $t['cpc'],
                    'intent' => $intent,
                    'funnel_stage' => $this->funnelStage($intent),
                    'opportunity' => $this->opportunity($t, $intent),
                    'chosen' => true,
                    'status' => 'new',
                ];
            }
            // Highest opportunity first, then keep the model tidy.
            usort($rows, fn ($a, $b) => ($b['opportunity'] ?? 0) <=> ($a['opportunity'] ?? 0));
            foreach (array_chunk($rows, 200) as $chunk) {
                $run->terms()->createMany($chunk);
            }

            // Cluster + roles.
            $this->clusterAndRole($run);

            $clusters = $run->terms()->whereNotNull('cluster')->distinct()->count('cluster');
            $run->update([
                'status' => 'clustered',
                'terms_count' => $run->terms()->count(),
                'clusters_count' => $clusters,
                'notes' => 'Discovered '.$run->terms()->count().' terms in '.$clusters.' clusters via '
                    .implode(' + ', array_map(fn ($d) => $d->name(), $this->research->drivers())).'.',
            ]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'notes' => 'Research error: '.Str::limit($e->getMessage(), 300)]);
        }

        return $run->refresh();
    }

    // ── Intent + funnel ──────────────────────────────────────────────

    protected function classifyIntent(string $keyword): string
    {
        $k = mb_strtolower($keyword);

        return match (true) {
            (bool) preg_match('/\b(buy|price|cost|cheap|deal|discount|book|booking|for sale|near me|coupon)\b/', $k) => 'transactional',
            (bool) preg_match('/\b(best|top|vs|versus|review|reviews|compare|comparison|alternative|cheapest)\b/', $k) => 'commercial',
            (bool) preg_match('/^(how|what|why|when|where|which|who|is|are|can|do|does|should)\b/', $k) || str_contains($k, '?') => 'informational',
            default => 'informational',
        };
    }

    protected function funnelStage(string $intent): string
    {
        return match ($intent) {
            'transactional', 'navigational' => 'bottom',
            'commercial' => 'middle',
            default => 'top',
        };
    }

    /**
     * Opportunity score: reward real volume, penalize difficulty, weight by
     * intent. When volume is unknown (free path) fall back to a source/intent
     * proxy so terms still rank sensibly.
     */
    protected function opportunity(array $t, string $intent): float
    {
        $intentW = ['informational' => 1.0, 'commercial' => 1.2, 'transactional' => 1.4, 'navigational' => 0.6][$intent] ?? 1.0;
        $difficulty = $t['difficulty'] ?? 45;
        $volume = (int) ($t['volume'] ?? 0);

        $base = $volume > 0
            ? sqrt($volume)
            : match ($t['source'] ?? 'related') { 'seed' => 9, 'question' => 5, default => 4 };

        return round($base * $intentW / ($difficulty + 10), 4);
    }

    // ── Clustering ───────────────────────────────────────────────────

    protected function clusterAndRole(KeywordResearchRun $run): void
    {
        $terms = $run->terms()->get();

        $clusters = $this->research->hasSerp()
            ? $this->clusterBySerp($terms, $run)
            : $this->clusterByToken($terms, $run);

        // Persist cluster + role (pillar = highest-volume/broadest per cluster).
        foreach ($clusters as $name => $group) {
            $pillar = collect($group)->sortByDesc(fn ($t) => [$t->volume ?? 0, -mb_strlen($t->keyword)])->first();
            foreach ($group as $t) {
                $t->update([
                    'cluster' => $name,
                    'role' => $t->id === $pillar?->id ? 'pillar' : 'spoke',
                ]);
            }
        }
    }

    /**
     * Topic-token clustering (no API): group terms by their most salient shared
     * token after removing the niche's common "theme" tokens and stopwords.
     * Small clusters are folded into "General".
     *
     * @return array<string, array<int, KeywordResearchTerm>>
     */
    protected function clusterByToken($terms, ?KeywordResearchRun $run = null): array
    {
        // Seed words are the niche itself — exclude them so clusters form around
        // the DISTINGUISHING modifier (itinerary, budget, jr pass…), not "japan".
        $seedTokens = [];
        foreach ((array) ($run?->seeds ?? []) as $s) {
            foreach (preg_split('/[^a-z0-9]+/', mb_strtolower((string) $s), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                if (mb_strlen($w) > 2) {
                    $seedTokens[$w] = true;
                }
            }
        }

        $tokensOf = [];
        $df = [];
        $count = max(1, count($terms));

        foreach ($terms as $t) {
            $toks = array_values(array_unique(array_filter(
                preg_split('/[^a-z0-9]+/', mb_strtolower($t->keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                fn ($w) => mb_strlen($w) > 2 && ! in_array($w, self::STOP, true) && ! isset($seedTokens[$w]),
            )));
            $tokensOf[$t->id] = $toks;
            foreach ($toks as $w) {
                $df[$w] = ($df[$w] ?? 0) + 1;
            }
        }

        // "Theme" tokens appear in most terms (the niche itself) → not a cluster.
        $themeCut = 0.55 * $count;

        $clusters = [];
        foreach ($terms as $t) {
            $candidates = array_filter($tokensOf[$t->id], fn ($w) => ($df[$w] ?? 0) < $themeCut);
            // Pick the most-shared non-theme token as the cluster signal.
            usort($candidates, fn ($a, $b) => ($df[$b] ?? 0) <=> ($df[$a] ?? 0));
            $key = $candidates[0] ?? 'general';
            $clusters[$key][] = $t;
        }

        // Fold tiny clusters into "General".
        $merged = [];
        $general = [];
        foreach ($clusters as $key => $group) {
            if (count($group) < 3 || $key === 'general') {
                $general = array_merge($general, $group);
            } else {
                $merged[Str::title($key)] = $group;
            }
        }
        if ($general !== []) {
            $merged['General'] = array_merge($merged['General'] ?? [], $general);
        }

        return $merged;
    }

    /**
     * SERP-overlap clustering (DataForSEO): keywords whose top results share
     * ≥3 domains belong to the same cluster — Google's own view of "same topic".
     * Only the top SERP_BUDGET terms get a live SERP (cost guard); the rest join
     * the nearest cluster by token, then leftovers fall back to token clustering.
     *
     * @return array<string, array<int, KeywordResearchTerm>>
     */
    protected function clusterBySerp($terms, KeywordResearchRun $run): array
    {
        $opts = array_filter([
            'language' => $run->target_language,
            'location_code' => $run->location_code,
        ], fn ($v) => $v !== null && $v !== '');

        $top = $terms->sortByDesc('opportunity')->take(self::SERP_BUDGET)->values();
        $serpOf = [];
        foreach ($top as $t) {
            $hosts = $this->research->serp($t->keyword, $opts);
            if ($hosts !== []) {
                $serpOf[$t->id] = array_slice($hosts, 0, 10);
                $t->update(['serp' => $serpOf[$t->id]]);
            }
        }

        // Union-find over the SERP'd terms: link any pair sharing ≥3 hosts.
        $ids = array_keys($serpOf);
        $parent = array_combine($ids, $ids);
        $find = function ($x) use (&$parent, &$find) {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }

            return $x;
        };
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $shared = count(array_intersect($serpOf[$ids[$i]], $serpOf[$ids[$j]]));
                if ($shared >= 3) {
                    $parent[$find($ids[$i])] = $find($ids[$j]);
                }
            }
        }

        // Build clusters from the union-find; name each by its highest-volume term.
        $byRoot = [];
        foreach ($ids as $id) {
            $byRoot[$find($id)][] = $terms->firstWhere('id', $id);
        }
        if ($byRoot === []) {
            return $this->clusterByToken($terms, $run); // SERP gave nothing usable
        }

        $named = [];
        foreach ($byRoot as $group) {
            $head = collect($group)->sortByDesc(fn ($t) => $t->volume ?? 0)->first();
            $named[$this->clusterName($head->keyword, $run)] = $group;
        }

        // Terms without a SERP → nearest named cluster by token, else token-cluster them.
        $placed = collect($byRoot)->flatten()->pluck('id')->all();
        $rest = $terms->whereNotIn('id', $placed);
        if ($rest->isNotEmpty()) {
            foreach ($this->clusterByToken($rest, $run) as $name => $group) {
                $named[$name] = array_merge($named[$name] ?? [], $group);
            }
        }

        return $named;
    }

    /** A short, reader-facing cluster name from the head keyword minus theme words. */
    protected function clusterName(string $keyword, KeywordResearchRun $run): string
    {
        $seedTokens = [];
        foreach ((array) $run->seeds as $s) {
            foreach (preg_split('/\s+/', mb_strtolower((string) $s)) ?: [] as $w) {
                $seedTokens[$w] = true;
            }
        }
        $words = array_filter(
            preg_split('/\s+/', mb_strtolower($keyword)) ?: [],
            fn ($w) => mb_strlen($w) > 2 && ! in_array($w, self::STOP, true) && ! isset($seedTokens[$w]),
        );

        return Str::title(implode(' ', array_slice(array_values($words), 0, 3))) ?: Str::title($keyword);
    }
}
