<?php

namespace App\Services\Ai;

use App\Models\AiImportItem;

/**
 * The GPT-side critic in the Claude-writes / GPT-reviews / Claude-fixes loop.
 *
 * It ONLY critiques — it never rewrites. The output is a compact JSON verdict
 * (approved flag + a short bullet list of actionable issues), so the reviewer
 * spends few output tokens. Claude then does the (larger) rewrite. This split
 * is what keeps a cheap model in the review seat without ballooning cost.
 */
class CrossReviewer
{
    /** $system overrides the product rulebook (e.g. BlogWriter::BLOG_CRITIC_SYSTEM for blog batches). */
    public function __construct(protected LlmClient $llm, protected ?string $system = null) {}

    public function systemPrompt(): string
    {
        return $this->system ?? self::CRITIC_SYSTEM;
    }

    public const CRITIC_SYSTEM = <<<'SYS'
You are a senior ecommerce SEO editor reviewing AI-written product copy (JSON).
Judge it against these dimensions and report ONLY what must change:
- SEO quality: meta lengths are auto-corrected mechanically and NEVER blocking — do not raise issues about meta_title or meta_description length (targets: title 50-60, description 150-164). Primary keyword in the early copy.
- E-E-A-T: real experience signals, correct specs, honest limitations, no invented facts/certifications.
- Keyword placement: natural, no stuffing, no exact-match spam. When the source data lists target keywords, the FIRST is the primary: it must be present DIRECTLY OR INDIRECTLY — the exact phrase where it reads naturally, or its meaningful words/close variants spread across the meta fields, first 100 words, and a heading. Never demand the verbatim phrase in one specific field. Flag only a keyword that is absent in BOTH forms, and flag forced repetition.
- Grammar & readability: paragraphs ≤4 sentences, varied sentences, scannable.
- Factual accuracy: every claim supported by the given source data.
- Product details: specs, compatibility (incl. an explicit "not compatible with"), package contents.
- Internal linking: 2-4 contextual in-sentence links with descriptive anchors — no end-of-copy link dumps, no generic anchors, no invented URLs.
  IMPORTANT: internal link URLs come verbatim from the store's own catalog and are validated automatically elsewhere. NEVER flag a link's host or domain (including localhost / 127.0.0.1 on dev stores) — judge only anchor text and placement.
- Schema / FAQ: 6-10 self-contained FAQ pairs, answers name the product.
- Duplicate / AI-sounding tone: no banned filler phrases, no templated headings, distinct voice.
- Buyer-intent sections: "best for" segmentation, comparison guidance, delivery/where-to-buy, trust.

Return ONLY compact JSON, no prose:
{"approved": <true if publish-ready with zero blocking issues, else false>,
 "issues": ["<imperative, specific, one fix each — e.g. 'Add compatibility: not compatible with IQOS 3 Duo', 'Rewrite intro sentence 1 to lead with product type + spec'>"],
 "summary": "<one sentence overall verdict>"}
Keep each issue under 20 words. An issue is BLOCKING only when publishing without the fix would hurt ranking, conversion, or accuracy: factual errors, missing required sections, rulebook violations. Meta lengths are NEVER blocking (auto-corrected mechanically). Style preferences, optional enhancements and "consider…" suggestions are NOT issues — leave them out entirely. If nothing blocking remains, return {"approved": true, "issues": [], "summary": "..."}.
SYS;

    /**
     * @return array{approved: bool, issues: array<string>, summary: string}
     */
    public function critique(AiImportItem $item, array $output, array $lintFindings = []): array
    {
        $user = "Source data (all claims must trace to this):\n".ProductWriter::compactRow($item->row)
            .($lintFindings !== [] ? "\n\nAutomated checks already flagged (include these):\n- ".implode("\n- ", $lintFindings) : '')
            ."\n\nCopy to review (JSON):\n".json_encode($output, JSON_UNESCAPED_SLASHES);

        // Small output budget — the critic writes a short list, not a rewrite.
        $raw = $this->llm->complete($this->systemPrompt(), $user, maxTokens: 1500, cacheStatic: true);
        $parsed = LlmClient::parseJson($raw);

        $issues = self::dropFalsePositives(array_values(array_filter(array_map(
            fn ($i) => trim((string) $i),
            (array) ($parsed['issues'] ?? []),
        ))));

        // Deterministic lint findings are always blocking, even if the model
        // called the copy approved.
        $issues = array_values(array_unique(array_merge($lintFindings, $issues)));

        return [
            'approved' => ($parsed['approved'] ?? false) === true && $issues === [],
            'issues' => $issues,
            'summary' => trim((string) ($parsed['summary'] ?? '')),
        ];
    }

    /**
     * Drop critic complaints the pipeline itself causes. Internal links use
     * the store's own catalog URLs — on a dev store that host IS localhost,
     * and the writer is required to use it verbatim. Flagging it creates an
     * unwinnable review loop that holds every product in needs_review.
     * (ContentReviewer::lint and InternalLinker::audit validate URLs
     * deterministically, so nothing real is lost.)
     *
     * @param  array<string>  $issues
     * @return array<string>
     */
    public static function dropFalsePositives(array $issues): array
    {
        return array_values(array_filter(
            $issues,
            fn (string $i) => ! preg_match('/localhost|127\.0\.0\.1|\blink (?:url|domain|host)/i', $i),
        ));
    }
}
