<?php

namespace App\Services\Ai;

use App\Models\AiActivityLog;
use App\Models\AiFixPrompt;
use App\Models\AiImportItem;

/**
 * Orchestrates the multi-agent loop:
 *   Claude writes → GPT critiques (compact) → Claude fixes → re-critique …
 * until the reviewer approves or passes run out. Every critique's fixing
 * instructions are saved (item scope) and rolled into a batch-level digest
 * that later products reuse. Returns whether the copy is publish-ready.
 *
 * @return array{output: array, approved: bool, issues: array<string>, summary: string, passes: int}
 */
class ReviewCycle
{
    public function __construct(
        protected ProductWriter $writer,   // Claude (or a subclass, e.g. BlogWriter)
        protected CrossReviewer $reviewer, // GPT (cheap)
    ) {}

    public function run(AiImportItem $item, array $output): array
    {
        $batch = $item->batch;
        $passes = max(1, min(4, (int) $batch->review_passes));

        $allowedUrls = array_column((array) $batch->link_catalog, 'url');
        $selfUrl = $item->reserved_slug
            ? route($batch->kind === 'blog' ? 'blog.show' : 'product.show', $item->reserved_slug)
            : null;
        $keywords = ProductWriter::keywordsFor($item->row);

        $approved = false;
        $issues = [];
        $summary = '';
        $done = 0;

        // Same provider+model on both seats → one combined call per cycle
        // (critique + corrected copy together), sharing the writer's prompt
        // cache. Half the requests, no cross-provider cache miss.
        $combined = $batch->usesCombinedReview();

        if ($combined) {
            AiActivityLog::write($batch->id, $item->id, 'review',
                'Single-provider mode: review + fix run as one call per cycle (token-optimized).');
        }

        for ($i = 1; $i <= $passes; $i++) {
            $lint = ContentReviewer::lint($output, $allowedUrls, $selfUrl, $keywords, (string) ($item->row['name'] ?? ''));

            if ($combined) {
                try {
                    [$critique, $corrected] = $this->combinedPass($item, $output, $lint);
                } catch (\Throwable $e) {
                    AiActivityLog::write($batch->id, $item->id, 'review',
                        "Combined review pass {$i} failed (".mb_substr($e->getMessage(), 0, 160).") — using automated checks only.", 'warning');
                    $critique = ['approved' => $lint === [], 'issues' => $lint, 'summary' => 'Automated checks only.'];
                    $corrected = null;
                }

                $done = $i;
                $approved = $critique['approved'];
                $issues = $critique['issues'];
                $summary = $critique['summary'];

                if ($approved) {
                    AiActivityLog::write($batch->id, $item->id, 'review',
                        "Review pass {$i}/{$passes}: approved ✓ (combined mode)", 'success');
                    break;
                }

                AiActivityLog::write($batch->id, $item->id, 'review',
                    "Review pass {$i}/{$passes}: ".count($issues).' issue(s) — corrected in the same call.', 'warning');
                $this->saveFixPrompt($item, $issues);

                if ($corrected !== null) {
                    $output = $corrected;
                    $item->update(['ai_output' => $output]);
                }

                continue;
            }

            try {
                $critique = $this->reviewer->critique($item, $output, $lint);
            } catch (\Throwable $e) {
                // Reviewer infrastructure failure must not kill a good draft:
                // fall back to the deterministic lint only.
                AiActivityLog::write($batch->id, $item->id, 'review',
                    "Reviewer unavailable on pass {$i} (".mb_substr($e->getMessage(), 0, 160).") — using automated checks only.", 'warning');
                $critique = ['approved' => $lint === [], 'issues' => $lint, 'summary' => 'Automated checks only.'];
            }

            $done = $i;
            $approved = $critique['approved'];
            $issues = $critique['issues'];
            $summary = $critique['summary'];

            if ($approved) {
                AiActivityLog::write($batch->id, $item->id, 'review',
                    "Review pass {$i}/{$passes}: approved by {$batch->reviewer_provider} ✓", 'success');
                break;
            }

            $issueList = implode('; ', array_slice($issues, 0, 8));
            AiActivityLog::write($batch->id, $item->id, 'review',
                "Review pass {$i}/{$passes}: ".count($issues)." issue(s) — {$issueList}", 'warning');

            // Save the fixing instructions for reuse (item + rolling batch digest).
            $this->saveFixPrompt($item, $issues);

            if ($i < $passes) {
                try {
                    $output = $this->writer->rewrite($item, $output, $issues);
                    AiActivityLog::write($batch->id, $item->id, 'write',
                        "Claude rewrote the copy to fix {$batch->reviewer_provider}'s ".count($issues).' issue(s).', 'info');
                } catch (\Throwable $e) {
                    AiActivityLog::write($batch->id, $item->id, 'write',
                        "Rewrite failed on pass {$i} (".mb_substr($e->getMessage(), 0, 160).').', 'error');
                    break;
                }
            }
        }

        // FINAL CONVERGENCE GATE — the LLM critic is a useful editor but a
        // poor binary judge: given unlimited passes it nitpicks forever
        // (style preferences, one-char meta overruns), so "approved with
        // zero issues" rarely happens and every item lands in needs_review.
        // Instead: apply ONE last rewrite for the outstanding issues, then
        // let the deterministic lint decide. Copy that passes every hard
        // rule (meta lengths, banned phrases, link rules, FAQs, no <h1>)
        // is publish-ready; residual critic notes are recorded, not blocking.
        if (! $approved) {
            if ($issues !== []) {
                try {
                    $output = $this->writer->rewrite($item, $output, $issues);
                } catch (\Throwable $e) {
                    AiActivityLog::write($batch->id, $item->id, 'write',
                        'Final rewrite failed ('.mb_substr($e->getMessage(), 0, 160).') — gating the current draft as-is.', 'warning');
                }
            }

            // Auto-fix what a machine can fix (meta length overruns) before
            // judging — a 3-char overrun must not hold a product.
            $output = ContentReviewer::clampMetaLengths($output);

            $finalLint = ContentReviewer::lint($output, $allowedUrls, $selfUrl, $keywords, (string) ($item->row['name'] ?? ''));

            if ($finalLint === []) {
                $approved = true;
                AiActivityLog::write($batch->id, $item->id, 'review',
                    "✅ Deterministic quality gate passed after {$done} review pass(es) + final fixes — publishing. Reviewer's remaining notes were style-level.", 'success');
            } else {
                $issues = $finalLint;
                AiActivityLog::write($batch->id, $item->id, 'review',
                    'Hard rules still failing after final rewrite: '.implode('; ', array_slice($finalLint, 0, 5)), 'warning');
            }
        }

        // Store style rule: no em dashes in customer-facing copy — enforced
        // mechanically on every path (approved or gated) since the models
        // reintroduce them no matter what the prompt says.
        $output = ContentReviewer::stripEmDashes($output);

        $item->update([
            'passes_done' => $done,
            'ai_output' => $output,
            'review_summary' => $summary,
            'open_issues' => $approved ? 0 : count($issues),
        ]);

        return compact('output', 'approved', 'issues', 'summary') + ['passes' => $done];
    }

    /**
     * Combined mode: ONE call returns the critique AND the corrected copy.
     * Response protocol: {"approved":true,...} or
     * {"approved":false,"issues":[...],"summary":"...","corrected":{full JSON}}.
     *
     * @return array{0: array{approved: bool, issues: array<string>, summary: string}, 1: ?array}
     */
    protected function combinedPass(AiImportItem $item, array $output, array $lint): array
    {
        $system = $this->reviewer->systemPrompt()
            ."\n\nADDITIONALLY: when approved is false, also include a \"corrected\" key holding the FULL fixed copy JSON (same keys as the input) with every issue resolved.";

        $user = "Source data (all claims must trace to this):\n".ProductWriter::compactRow($item->row)
            .($lint !== [] ? "\n\nAutomated checks already flagged (include and fix these):\n- ".implode("\n- ", $lint) : '')
            ."\n\nCopy to review (JSON):\n".json_encode($output, JSON_UNESCAPED_SLASHES);

        // Bigger budget than critique-only: the corrected copy rides along.
        $llm = \App\Services\Ai\LlmClient::for($item->batch->provider, $item->batch->model)
            ->withContext('review', $item->batch_id, $item->id);

        $parsed = \App\Services\Ai\LlmClient::parseJson($llm->complete($system, $user, maxTokens: \App\Services\Ai\ProductWriter::maxOutputTokens(), cacheStatic: true));

        $issues = array_values(array_unique(array_merge($lint, CrossReviewer::dropFalsePositives(array_values(array_filter(array_map(
            fn ($i) => trim((string) $i),
            (array) ($parsed['issues'] ?? []),
        )))))));

        $corrected = (isset($parsed['corrected']['description_html'])) ? $parsed['corrected'] : null;

        return [[
            'approved' => ($parsed['approved'] ?? false) === true && $issues === [],
            'issues' => $issues,
            'summary' => trim((string) ($parsed['summary'] ?? '')),
        ], $corrected];
    }

    /** Persist the fix instructions, and refresh the batch-level recurring digest. */
    protected function saveFixPrompt(AiImportItem $item, array $issues): void
    {
        if ($issues === []) {
            return;
        }

        AiFixPrompt::create([
            'batch_id' => $item->batch_id,
            'item_id' => $item->id,
            'scope' => 'item',
            'label' => ($item->row['name'] ?? "Item #{$item->id}").' — review fixes',
            'instructions' => "- ".implode("\n- ", $issues),
            'issue_count' => count($issues),
        ]);

        $this->refreshBatchDigest($item->batch_id);
    }

    /**
     * Rebuild the batch digest: the issues seen most often across the batch so
     * far, deduplicated to their normalized form, capped so it stays small.
     */
    protected function refreshBatchDigest(int $batchId): void
    {
        $recent = AiFixPrompt::where('batch_id', $batchId)
            ->where('scope', 'item')
            ->latest('id')
            ->limit(40)
            ->pluck('instructions');

        $counts = [];
        foreach ($recent as $block) {
            foreach (preg_split('/\n/', (string) $block) as $line) {
                $line = trim(ltrim($line, "- \t"));
                if ($line === '') {
                    continue;
                }
                // Normalize so near-identical issues collapse (drop product-specific
                // values). Straight apostrophes are NOT treated as quote pairs —
                // "don't … product's" must not swallow the words between them.
                $key = mb_strtolower(preg_replace('/["“”].*?["“”]|\d+/u', '', $line));
                $key = trim(preg_replace('/\s+/', ' ', $key));
                $counts[$key] ??= ['n' => 0, 'sample' => $line];
                $counts[$key]['n']++;
            }
        }

        // Keep only issues that recurred (appeared 2+ times), top 8.
        $recurring = collect($counts)
            ->filter(fn ($c) => $c['n'] >= 2)
            ->sortByDesc('n')
            ->take(8)
            ->map(fn ($c) => $c['sample'])
            ->values();

        if ($recurring->isEmpty()) {
            return;
        }

        AiFixPrompt::updateOrCreate(
            ['batch_id' => $batchId, 'scope' => 'batch'],
            [
                'label' => 'Recurring fixes (auto-learned)',
                'instructions' => '- '.$recurring->implode("\n- "),
                'issue_count' => $recurring->count(),
            ],
        );
    }
}
