<?php

namespace App\Services\Research;

use App\Services\Ai\LlmClient;
use Illuminate\Support\Collection;

/**
 * Turns a cluster of chosen keyword terms into a COMPLETE brief per idea —
 * a sharp title plus pain_point, audience_need, angle and an outline (TOC) —
 * so the keyword-research path produces the same rich brief the funnel builder
 * does, instead of a bare title with empty columns.
 *
 * One LLM call per cluster (not per idea) keeps token cost low and lets the
 * model see the pillar so spokes complement it. Opt-in from the "Create
 * content plan" action — when off, PlanBuilder falls back to a mechanical
 * title and an empty brief (zero AI cost).
 */
class IdeaBriefWriter
{
    /** First configured AI provider, or null if none — mirrors the research LlmDriver. */
    public static function provider(): ?string
    {
        foreach (['anthropic', 'openai', 'gemini'] as $p) {
            if (trim((string) setting("ai.{$p}_api_key")) !== '') {
                return $p;
            }
        }

        return null;
    }

    public function available(): bool
    {
        return self::provider() !== null;
    }

    private const SYSTEM = <<<'SYS'
You are an SEO content strategist. For each keyword in ONE topic cluster, write a complete brief a writer can execute without guessing.

For EACH item return:
- title: the working headline. Specific and compelling, 70 characters or fewer, matching the exact search intent of the keyword. VARY the format across the cluster (how-to, numbered list, a question, "X vs Y", "mistakes to avoid", checklist, beginner explainer). Reserve "complete guide"/"ultimate guide" for the PILLAR only — never on a spoke. No clickbait, no colon-stuffing, no two titles sharing the same shape.
- pain_point: the specific problem or frustration this reader has, one sentence.
- audience_need: who the reader is and what they must be able to DO after reading, one sentence.
- angle: the unique promise/take that makes this beat what already ranks, one sentence.
- outline: 4-7 section headings forming the table of contents, specific to THIS title (never generic filler like "Introduction"/"Conclusion").

Return ONLY JSON: {"briefs":[{"i":<the item's i>,"title":"...","pain_point":"...","audience_need":"...","angle":"...","outline":["...","..."]}]}
SYS;

    /**
     * Brief every term in one cluster.
     *
     * @param  Collection  $terms  KeywordResearchTerm models in this cluster
     * @return array<int, array{title:string,pain_point:?string,audience_need:?string,angle:?string,outline:array<int,string>}>  keyed by term id
     */
    public function writeCluster(string $cluster, Collection $terms, array $opts = []): array
    {
        $provider = self::provider();

        if ($provider === null || $terms->isEmpty()) {
            return [];
        }

        $ordered = $terms->values();
        $items = $ordered->map(fn ($t, $i) => [
            'i' => $i,
            'keyword' => (string) $t->keyword,
            'role' => $t->role === 'pillar' ? 'pillar' : 'spoke',
            'funnel_stage' => $t->funnel_stage ?: 'top',
        ])->all();

        $user = "CLUSTER: {$cluster}"
            .(($opts['country'] ?? null) ? "\nTARGET MARKET: {$opts['country']}" : '')
            ."\n\nITEMS (return one brief per item, keeping the same \"i\"):\n"
            .json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ."\n\nWrite the briefs now.";

        try {
            $parsed = LlmClient::parseJson(
                LlmClient::for($provider)->withContext('plan')->complete(self::SYSTEM, $user, maxTokens: 3500)
            );
        } catch (\Throwable) {
            return [];
        }

        $byIndex = [];
        foreach ((array) ($parsed['briefs'] ?? []) as $b) {
            if (isset($b['i'])) {
                $byIndex[(int) $b['i']] = $b;
            }
        }

        $out = [];
        foreach ($ordered as $i => $term) {
            $b = $byIndex[$i] ?? null;
            if (! $b) {
                continue;
            }

            $title = trim((string) ($b['title'] ?? ''));

            // Safety net for the title rule: strip a "complete/ultimate guide"
            // tail from any non-pillar even if the model slipped.
            if ($title !== '' && $term->role !== 'pillar') {
                $title = trim((string) preg_replace('/\s*[:\-\x{2013}]?\s*(the\s+)?(complete|ultimate|definitive)\s+guide\b/iu', '', $title));
                $title = trim($title, " :-\u{2013}");
            }

            $out[$term->id] = [
                'title' => $title,
                'pain_point' => trim((string) ($b['pain_point'] ?? '')) ?: null,
                'audience_need' => trim((string) ($b['audience_need'] ?? '')) ?: null,
                'angle' => trim((string) ($b['angle'] ?? '')) ?: null,
                'outline' => array_values(array_filter(
                    array_map(fn ($s) => trim((string) $s), (array) ($b['outline'] ?? [])),
                    fn ($s) => $s !== ''
                )),
            ];
        }

        return $out;
    }
}
