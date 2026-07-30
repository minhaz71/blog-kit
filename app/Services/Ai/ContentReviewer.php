<?php

namespace App\Services\Ai;

use App\Models\AiImportItem;

/**
 * Multi-pass QA: the LLM re-reads its own copy 3-4 times (batch setting)
 * and either approves it or returns a corrected version. Stops early on
 * approval so extra passes cost nothing when the copy is already clean.
 */
class ContentReviewer
{
    public function __construct(protected LlmClient $llm) {}

    public function review(AiImportItem $item, array $output): array
    {
        $batch = $item->batch;
        $passes = max(1, min(4, (int) $batch->review_passes));

        // Static per batch → provider prompt cache hits on every pass/item.
        $formatRule = match ($batch->output_format) {
            'html_plain' => 'HTML must contain NO class/id/style attributes and css must be empty.',
            'html_classes' => 'HTML may ONLY use these CSS classes (reject any others): '
                .trim((string) $batch->custom_classes).'. css must be empty.',
            default => 'CSS may only target pd-* classes — no body/global/h1 selectors.',
        };

        $system = <<<SYS
You are a strict ecommerce content QA reviewer applying an SEO/conversion rulebook.
Reject (and fix) the copy if ANY criterion fails:

FACTS & FORMAT
- Factual claims not present in the source data (invented certifications, origins, awards).
- Broken/invalid HTML, missing JSON keys. Meta lengths (title aim 50-60, up to 63; description 150-164) are auto-corrected mechanically — NEVER reject or rewrite over meta length alone.
- Format rule: {$formatRule}

INTRO (most-failed element)
- Sentence 1 must state [product type] + differentiator + a spec — machines-first pattern.
- No filler openers: "Welcome to", "We are proud", "Are you looking for", "When it comes to", "In today's world".
- Primary keyword (product name + category term) missing from the first 100 words.

WRITING QUALITY
- Banned phrases anywhere: "Designed to", "Elevate your experience", "next level", "Perfect choice", "Game changer", "Wide range", "Extensive selection", "Look no further", "Unleash", "Unlock".
- Any feature listed without its benefit explained (FAB violation).
- Compound bullets (multiple ideas in one bullet); identical repeated sentence structures.
- Paragraphs longer than 4 sentences.
- Generic sensory language ("great flavor", "fresh finish") where the product is experiential — demand inhale/body/finish/intensity specificity with comparative anchors.
- Keyword stuffing or unnatural exact-match repetition.

CONVERSION & TRUST
- Missing buyer segmentation ("best for" guidance).
- Compatibility not stated explicitly when the source data includes it.
- Pushy closing ("Buy now!") instead of a benefit-anchored nudge.
- faqs: fewer than 5, generic, or answers longer than 4 sentences. FAQ answers must name the product (self-contained when quoted by an AI engine).
- Template headings copied verbatim ("Flavor Profile", "Key Features", "Product Overview") instead of product-specific wording; or the STRUCTURE VARIATION directive in the product data was ignored.

INTERNAL LINKS
- Generic anchor text ("click here", "here", "read more", "this product") — anchors must be product names or descriptive phrases.
- The product linking to itself, or any URL not taken verbatim from the writer's catalog (invented/altered URLs).
- Links dumped in a list at the end of the copy instead of woven into relevant sentences (comparison, compatibility, alternatives).

If everything passes, return exactly: {"approved": true}
Otherwise return the FULL corrected JSON object (same keys), fixing every problem you found.
Return only JSON.
SYS;

        $allowedUrls = array_column((array) $batch->link_catalog, 'url');
        $selfUrl = $item->reserved_slug ? \App\Support\Permalinks::product($item->reserved_slug) : null;

        $keywords = ProductWriter::keywordsFor($item->row);

        for ($i = 1; $i <= $passes; $i++) {
            $lint = self::lint($output, $allowedUrls, $selfUrl, $keywords, (string) ($item->row['name'] ?? ''));

            $user = "Source data:\n".ProductWriter::compactRow($item->row)
                .($lint !== [] ? "\n\nAUTOMATED LINT FINDINGS (fix all of these):\n- ".implode("\n- ", $lint) : '')
                ."\n\nCopy to review (pass {$i}/{$passes}):\n".json_encode($output, JSON_UNESCAPED_SLASHES);

            try {
                $result = LlmClient::parseJson($this->llm->complete($system, $user, maxTokens: \App\Services\Ai\ProductWriter::maxOutputTokens(), cacheStatic: true));
            } catch (\Throwable $e) {
                // QA infrastructure hiccup must not kill a good draft — keep
                // the current version and stop reviewing.
                \App\Models\AiActivityLog::write($batch->id, $item->id, 'review',
                    "Review pass {$i}/{$passes} could not complete (".mb_substr($e->getMessage(), 0, 200).") — publishing the current draft.", 'warning');
                break;
            }

            $item->update(['passes_done' => $i]);

            if (($result['approved'] ?? false) === true) {
                \App\Models\AiActivityLog::write($batch->id, $item->id, 'review', "Review pass {$i}/{$passes}: approved ✓");
                break;
            }

            \App\Models\AiActivityLog::write($batch->id, $item->id, 'review', "Review pass {$i}/{$passes}: issues found — corrections applied, re-checking.", 'warning');

            // Reviewer returned a corrected version — adopt it and re-check.
            if (isset($result['description_html'])) {
                $output = $result;
                $item->update(['ai_output' => $output]);
            }
        }

        return $output;
    }

    /** Phrases that signal AI/filler copy — from the writing rulebook. */
    public const BANNED_PHRASES = [
        'when it comes to', 'in today\'s world', 'designed to', 'elevate your',
        'to the next level', 'perfect choice', 'game changer', 'game-changer',
        'wide range', 'extensive selection', 'we are proud', 'welcome to our',
        'are you looking for', 'look no further', 'unleash', 'unlock',
        // AI-typical vocabulary — words humans rarely write in store copy.
        'delve', 'delving', 'embark', 'tapestry', 'testament to',
        'seamless', 'seamlessly', 'meticulously', 'cutting-edge', 'state-of-the-art',
        'revolutionize', 'revolutionary', 'harness the power', 'ever-evolving',
        'navigating the', 'in the world of', 'dive into', 'let\'s explore',
        'it\'s important to note', 'it is important to note', 'in conclusion',
        'in summary', 'in this article', 'crafted to',
    ];

    /**
     * Store rule: no em dashes in customer-facing copy. LLMs love them, so
     * prompt instructions alone don't hold — rewrite them deterministically
     * across every string in the output (copy, meta fields, FAQs).
     * "word — word" reads naturally as "word, word"; numeric ranges keep a
     * plain hyphen.
     */
    public static function stripEmDashes(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'stripEmDashes'], $value);
        }

        if (! is_string($value) || ! preg_match('/[—–]/u', $value)) {
            return $value;
        }

        $value = preg_replace('/(?<=\d)\s*[—–]\s*(?=\d)/u', '-', $value); // 2—4 → 2-4
        $value = preg_replace('/\s*[—–]\s*/u', ', ', $value);             // word — word → word, word

        return $value;
    }

    /**
     * Indirect keyword coverage: the phrase counts as implemented when MOST
     * of its meaningful words (stopwords dropped, lightly stemmed) appear
     * anywhere in the copy — matching how engines score topical relevance.
     */
    public static function keywordCoveredIndirectly(string $keyword, string $haystack): bool
    {
        $stopwords = ['a', 'an', 'the', 'of', 'in', 'on', 'for', 'to', 'and', 'or', 'is', 'are',
            'how', 'what', 'why', 'do', 'it', 'your', 'you', 'with', 'my', 'much', 'many', 'can'];

        $tokens = array_values(array_diff(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            $stopwords
        ));

        if ($tokens === []) {
            return false;
        }

        $stem = function (string $word): string {
            foreach (['ing', 'ed', 'es'] as $suffix) {
                if (str_ends_with($word, $suffix) && mb_strlen($word) > mb_strlen($suffix) + 3) {
                    return mb_substr($word, 0, -mb_strlen($suffix));
                }
            }

            return str_ends_with($word, 's') && mb_strlen($word) > 4 ? mb_substr($word, 0, -1) : $word;
        };

        $found = count(array_filter($tokens, fn ($t) => str_contains($haystack, $stem($t))));

        // Majority of the meaningful words = indirect implementation.
        return $found / count($tokens) >= 0.6;
    }

    /**
     * Meta length overruns are mechanically fixable — trim at a word
     * boundary instead of failing (or holding) an otherwise-clean product.
     */
    public static function clampMetaLengths(array $output): array
    {
        foreach (['meta_title' => 63, 'meta_description' => 164] as $key => $limit) {
            $value = trim((string) ($output[$key] ?? ''));

            if ($value !== '' && mb_strlen($value) > $limit) {
                $cut = preg_replace('/\s+\S*$/u', '', mb_substr($value, 0, $limit + 1));
                $output[$key] = rtrim($cut, " \t\n\r.–—|,;:-");
            }
        }

        return $output;
    }

    /**
     * Zero-token deterministic checks. Violations are injected into the
     * review prompt so the model fixes them on the first pass — and they
     * catch what a lenient reviewer might wave through.
     */
    public static function lint(array $output, array $allowedUrls = [], ?string $selfUrl = null, array $keywords = [], ?string $pageTitle = null): array
    {
        $violations = [];
        $html = (string) ($output['description_html'] ?? '');
        $shortHtml = (string) ($output['short_description_html'] ?? '');
        $text = strtolower(strip_tags($html.' '.$shortHtml));

        // The meta title is the title tag, the H1 is the page headline —
        // identical copies waste the SERP snippet and read as lazy SEO.
        $metaTitle = mb_strtolower(trim((string) ($output['meta_title'] ?? '')));
        $h1s = array_filter([
            mb_strtolower(trim((string) $pageTitle)),
            mb_strtolower(trim((string) ($output['title'] ?? ''))), // blog articles carry their H1 in the output
        ]);

        if ($metaTitle !== '' && in_array($metaTitle, $h1s, true)) {
            $violations[] = 'meta_title is identical to the page H1 — rewrite it as a search snippet (intent word + keyword + a concrete differentiator like city or delivery promise).';
        }

        // Store-owner target keywords: the PRIMARY (first) one is a hard
        // requirement — copy that never mentions it cannot rank for it.
        // Secondary keywords are prompt/critic guidance, not blocking.
        // DIRECT OR INDIRECT placement both count (owner rule): the exact
        // phrase, OR most of its meaningful words (stemmed, so "cleaning"
        // matches "clean") present anywhere across the copy + meta. Search
        // engines match this way; demanding verbatim long-tail phrases
        // ("terea stick pack size how many") produces unnatural copy.
        if ($keywords !== []) {
            $primary = mb_strtolower($keywords[0]);
            $meta = mb_strtolower(($output['meta_title'] ?? '').' '.($output['meta_description'] ?? ''));
            $haystack = $text.' '.$meta;

            if (! str_contains($haystack, $primary) && ! self::keywordCoveredIndirectly($primary, $haystack)) {
                $violations[] = "Primary target keyword \"{$keywords[0]}\" is missing — use it directly, or indirectly through its main words, in the copy, meta fields, or a heading.";
            }
        }

        // Store rule: NO em dashes anywhere — copy, title, meta, FAQs, image
        // fields. Making it a lint violation means the model rewrites the
        // sentence properly during review; stripEmDashes stays as the final
        // mechanical guarantee for anything that slips through.
        $dashed = [];
        array_walk_recursive($output, function ($value, $key) use (&$dashed) {
            if (is_string($value) && $key !== 'css' && preg_match('/[—–]/u', $value)) {
                $dashed[$key] = true;
            }
        });

        if ($dashed !== []) {
            $violations[] = 'Em/en dashes found in: '.implode(', ', array_keys($dashed))
                .'. Never use them anywhere; rewrite those sentences with commas, periods, or parentheses.';
        }

        foreach (self::BANNED_PHRASES as $phrase) {
            // Word-boundary match: "unlock" must not fire on "unlocked",
            // "designed to" must not fire on "redesigned to".
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/ui';

            if (preg_match($pattern, $text)) {
                $violations[] = "Banned phrase found: \"{$phrase}\" — replace with the specific fact or benefit it hides.";
            }
        }

        // Owner rule: meta lengths NEVER block content. Overruns are fixed
        // mechanically by clampMetaLengths (title ≤63, description ≤164)
        // before publishing — no violation, no rewrite cycle, no held item.

        $faqs = $output['faqs'] ?? [];
        if (! is_array($faqs) || count($faqs) < 5) {
            $violations[] = 'faqs must contain at least 5 question/answer pairs (target 5-8).';
        }

        // PRODUCT outputs only (suggested_price is the product signature —
        // blog outputs have no price): the short description must carry the
        // key-fact bullet list, not a thin one-liner.
        if (array_key_exists('suggested_price', $output)
            && substr_count(strtolower($shortHtml), '<li') < 3) {
            $violations[] = 'short_description_html is too thin — after the <p> hook it must include a <ul> of 4-6 key-fact bullets (Flavor, Strength, Pack, Compatibility, …) per the output contract.';
        }

        if ($html !== '' && preg_match('/<h1[\s>]/i', $html)) {
            $violations[] = 'description_html contains an <h1> — the page already has one; use <h2>/<h3>.';
        }

        // Semantic headings: headings should describe what the section
        // answers, not repeat the target keyword or product name — a
        // keyword in most headings reads as stuffing to search engines
        // and buyers alike. Enforced here deterministically; the prompt
        // rule alone is guidance the model can drift from.
        if ($html !== '' && preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $headingMatches)) {
            $headings = array_map(fn ($h) => mb_strtolower(trim(strip_tags($h))), $headingMatches[1]);
            $total = count($headings);

            $stuffCandidates = array_unique(array_filter([
                mb_strtolower(trim((string) ($output['focus_keyword'] ?? ''))),
                mb_strtolower(trim((string) ($keywords[0] ?? ''))),
                mb_strtolower(trim((string) $pageTitle)),
            ]));

            foreach ($stuffCandidates as $candidate) {
                $hits = count(array_filter($headings, fn ($h) => str_contains($h, $candidate)));

                if ($total >= 4 && $hits > (int) floor($total / 2)) {
                    $violations[] = "\"{$candidate}\" appears in {$hits} of {$total} headings — rewrite most headings to describe what each section answers (e.g. \"Flavor Experience\", \"Compatible Devices\", \"Who This Suits\") instead of repeating the keyword.";

                    break; // one violation is enough to trigger the rewrite
                }
            }
        }

        // ── Internal link checks (deterministic, zero tokens) ──────────
        // Both quote styles; short description scanned too.
        $linkable = trim($html."\n".$shortHtml);

        if ($linkable !== '' && preg_match_all('~<a\s[^>]*?href=(["\'])(.*?)\1[^>]*>(.*?)</a>~is', $linkable, $links, PREG_SET_ORDER)) {
            $appUrl = rtrim((string) config('app.url'), '/');

            foreach ($links as $link) {
                $href = html_entity_decode($link[2]);
                $anchor = strtolower(trim(strip_tags($link[3])));

                if (in_array($anchor, ['click here', 'here', 'read more', 'this product', 'link'], true)) {
                    $violations[] = "Generic anchor text \"{$anchor}\" — use the linked product's name or a descriptive phrase.";
                }

                if ($selfUrl !== null && $href === $selfUrl) {
                    $violations[] = 'The copy links the product to itself — remove that link.';
                } elseif ($allowedUrls !== [] && $appUrl !== '' && str_starts_with($href, $appUrl) && ! in_array($href, $allowedUrls, true)) {
                    $violations[] = "Invented internal URL \"{$href}\" — only URLs from the provided catalog may be used, copied exactly.";
                }
            }

            // Link dump at the very end (a trailing list where items are links).
            if (preg_match('~<(ul|ol)[^>]*>\s*(?:<li[^>]*>\s*<a\s[^>]*>.*?</a>\s*</li>\s*)+</\1>\s*(?:</div>\s*)?$~is', trim($html))) {
                $violations[] = 'Links are dumped in a list at the end of the copy — weave them into relevant sentences instead (comparison, compatibility, alternatives).';
            }
        }

        return $violations;
    }
}
