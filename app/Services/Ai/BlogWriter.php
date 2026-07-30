<?php

namespace App\Services\Ai;

use App\Models\AiImportBatch;
use App\Models\AiImportItem;

/**
 * The blog seat of the writing agent. Extends ProductWriter so the whole
 * proven pipeline is inherited unchanged — JSON write with one resample,
 * reviewer/rewrite loop, learned fixes, batch-memory uniqueness digest,
 * provider prompt caching (system block byte-identical per batch) — while
 * the prompts speak "article", not "product".
 *
 * The output contract intentionally reuses the product keys
 * (description_html = article body, short_description_html = excerpt) so
 * ContentReviewer::lint, ReviewCycle, stripEmDashes and clampMetaLengths
 * all work verbatim on blog copy.
 */
class BlogWriter extends ProductWriter
{
    public const DEFAULT_SYSTEM = 'You are a senior subject-matter expert and SEO content writer. '
        .'Write genuinely helpful, expert-level articles on the assigned topic that read like an experienced human specialist, never like AI. '
        .'They must rank in Google AND Bing AND get cited by AI answer engines (ChatGPT, Perplexity, Google AI Overviews), '
        .'build topical authority for the site\'s subject area, and guide the reader to a genuinely helpful next step. '
        .'Honest, specific, experience-first; no hype, no fluff, no filler. '
        .'Write about ANY topic the assignment gives you (technology, health, finance, travel, food, hobbies, business, science, lifestyle, etc.); '
        .'never assume a specific product, brand, or industry unless the assignment states one.';

    public const BLOG_RULES = <<<'RULES'
ARTICLE RULES (mandatory):

LENGTH & DEPTH: each assignment carries its own TARGET LENGTH — follow it, sized by the article's role and how much the topic genuinely holds. Within the target range, let the TOPIC decide: a narrow question answered completely at the low end beats a padded high end. Depth beats length — never pad, never repeat. Every section must teach something concrete or remove a real doubt.

STRUCTURE:
- Open with a 2-3 sentence direct answer to the title's core question (this is the AI-quotable summary), then deliver the details.
- <h2> for main sections, <h3> for sub-points. Write headings in your own words for THIS article — several in question form where natural, each followed IMMEDIATELY by a direct 40-60 word self-contained answer.
- Short paragraphs (2-4 sentences), bullet lists for scannable facts, a comparison or data <table> where it genuinely helps.
- End with a short practical conclusion + one calm, benefit-anchored next step (a store link where natural). Never "Buy now!!".

E-E-A-T & AUTHENTICITY:
- Write from hands-on experience: practical observations, realistic expectations, correct terminology, honest trade-offs.
- Cite concrete specifics (numbers, timings, measurements, versions, steps, costs when given) — never vague adjectives.
- No invented statistics, studies, reviews, or expert quotes. For YMYL topics (health, medicine, finance, legal, safety), state only well-established information, avoid definitive personal advice, and tell the reader to consult a qualified professional; never fabricate credentials, guarantees, or outcomes.
- If the assignment's topic is regulated or age-restricted, follow the assignment's stated compliance note and make no prohibited claims.
- Include one honest limitation or "when this is NOT the right choice" note — trust and ranking signal.

TOPIC DISCIPLINE:
- Cover the given ANGLE and OUTLINE HINTS; answer the search intent behind the title completely — the reader should not need a second article.
- Stay on the assigned topic: sibling articles in this cluster cover the related topics, link them instead of drifting into them.

PUNCTUATION (hard rule, checked mechanically): never use em dashes (—) or en dashes (–) ANYWHERE — not in the body, headings, title, excerpt, meta fields, FAQs, or image text. Use commas, periods, or parentheses instead. Any dash found fails review.

BANNED PHRASES (never use, in any form):
"When it comes to", "In today's world", "Designed to", "Elevate your experience", "Take your X to the next level", "Perfect choice", "Game changer", "Wide range", "Extensive selection", "We are proud to offer", "Welcome to our", "Are you looking for", "Whether you're a beginner or expert", "Look no further", "Unleash", "Unlock", "Dive into", "Let's explore", "In this article we will".

BANNED AI-STYLE WORDS (these read as machine-written — never use them):
"delve", "delving", "embark", "tapestry", "testament to", "seamless", "seamlessly", "meticulously", "cutting-edge", "state-of-the-art", "revolutionize", "revolutionary", "harness the power", "ever-evolving", "navigating the", "in the world of", "it's important to note", "in conclusion", "in summary", "crafted to". Use plain, specific, human wording instead.

FAQS: return a "faqs" key — 5-8 reader questions with direct 2-4 sentence answers based on real doubts around this topic. Answers must stand alone when quoted (name the subject inside the answer). Different questions AND answers from every other article in the batch.

UNIQUENESS (critical): no two articles in this batch may share headings, opening lines, sentence patterns, CTA wording, or the same set of internal links. The BATCH MEMORY block lists what is already used — avoid all of it.
RULES;

    /**
     * Semantic-SEO rulebook (article-flavored): entity coverage and
     * descriptive headings, mirroring ProductWriter::SEMANTIC_SEO_RULES but
     * phrased for articles rather than product pages.
     */
    public const SEMANTIC_SEO_RULES = <<<'RULES'
SEMANTIC SEO (mandatory — write for the topic and its entities, not repeated keywords):

ENTITY COVERAGE: mention the real-world entities a genuine expert on THIS article's topic would reference naturally — the relevant people, places, organizations, products, tools, standards, methods, and concepts — so the article demonstrates real topical depth. Draw them from the article's topic, the brief, and any ENTITIES listed in the assignment. Use correct proper names and terminology. Never invent an entity, never force one in, never list them mechanically.

SEMANTIC HEADINGS: every H2/H3 must describe what the section actually answers, not restate the focus keyword. Question-form H2s (already required above) must vary in phrasing from this cluster's sibling articles — do not reuse the same question shape across every article in the batch; phrase each around what THAT article's readers actually ask.
RULES;

    /**
     * Article-flavored search + AI-answer rulebook. Replaces the product
     * engine's SEARCH_ENGINE_RULES (which speaks "buyer / product term / spec
     * table") so blog articles are optimized as articles, on any topic.
     */
    public const BLOG_SEARCH_RULES = <<<'RULES'
SEARCH & AI-ANSWER OPTIMIZATION (mandatory — the article must rank on Google AND Bing AND get cited by AI answer engines):

GOOGLE (Helpful Content + E-E-A-T):
- People-first: every section answers a real question the reader has or removes a real doubt. Delete anything that exists only "for SEO".
- Show first-hand experience and correct terminology for the topic — this is what Google's quality systems reward.
- Internal links pass authority: link related articles and pages with descriptive anchors placed inside relevant sentences; NEVER generic anchors ("click here", "read more").
- Primary keyword early and natural; after that use synonyms and related entities instead of repeating the exact phrase — unnatural repetition reads as stuffing.

BING (Bing Webmaster Guidelines):
- Bing weighs exact keyword placement more literally: put the primary term in the meta_title, in at least one H2, and in the first paragraph.
- Bing favors clean, literal structure: short paragraphs, bullet lists, and tables parse and rank well.
- meta_description is used more directly in Bing results — make it a clean, complete one-sentence summary.

AI ANSWER ENGINES / AIO (Google AI Overviews, ChatGPT, Perplexity, Bing Copilot):
- Use question-form H2/H3 headings where natural and follow each IMMEDIATELY with a direct, self-contained 40-60 word answer — that block is exactly what gets quoted.
- Citable specifics beat adjectives: numbers, dates, steps, measurements, named examples. AI engines cite facts, not "the best".
- Near the top, include one definition-style sentence the engine can lift as a summary ("X is a … that …").
- FAQ answers must stand alone when quoted out of context: name the subject inside the answer; never answer with only "Yes".
- Tables and lists are extraction-friendly — use them where the content is genuinely comparative or sequential.

AUTHENTICITY:
- Write like a knowledgeable practitioner sharing real experience, not a brochure.
- Include one honest trade-off or limitation — authenticity is both a ranking and a trust signal.
- NEVER fabricate reviews, ratings, "people say" claims, statistics, or studies.
RULES;

    /** Blog-flavored critic rulebook for the reviewer seat (same JSON verdict protocol). */
    public const BLOG_CRITIC_SYSTEM = <<<'SYS'
You are a senior SEO content editor reviewing an AI-written blog article (JSON: description_html is the article body, short_description_html the excerpt).
Judge it and report ONLY what must change:
- SEO: meta lengths are NEVER blocking issues (they are auto-corrected mechanically) — meta_title aims 50-60 chars (63 acceptable), meta_description 150-164 chars. Primary keyword must be present DIRECTLY OR INDIRECTLY (the exact phrase, or its meaningful words/close variants spread across meta fields, headings, and early copy) — never demand exact-match placement in one specific field.
- Search intent: the article must fully answer the question its title promises; flag missing core sub-topics.
- E-E-A-T: hands-on experience signals, correct terminology, honest trade-offs, no invented facts/statistics/studies; for YMYL topics no unqualified medical/financial/legal claims.
- Readability: intro answers the core question in the first 2-3 sentences; paragraphs ≤4 sentences; scannable headings (question-form where natural), each followed by a direct self-contained answer.
- Internal linking: 2-5 contextual in-sentence links with descriptive anchors (article titles or page names) — no link dumps, no generic anchors, no invented URLs.
  IMPORTANT: link URLs come verbatim from the site's own catalog and are validated automatically elsewhere. NEVER flag a link's host or domain (including localhost / 127.0.0.1 on dev sites).
- FAQs: 5-8 self-contained pairs, answers name the subject.
- Tone: no banned filler phrases, no AI-sounding patterns, distinct human voice.

Return ONLY compact JSON, no prose:
{"approved": <true if publish-ready with zero blocking issues, else false>,
 "issues": ["<imperative, specific, one fix each>"],
 "summary": "<one sentence overall verdict>"}
Keep each issue under 20 words. An issue is BLOCKING only when publishing without the fix would hurt ranking, accuracy, or trust. Style preferences and "consider…" suggestions are NOT issues. If nothing blocking remains, return {"approved": true, "issues": [], "summary": "..."}.
SYS;

    /**
     * Classes the funnel writer may use — each has default CSS shipped in
     * resources/css/blog.css. BlogPublisher strips anything else, so the
     * vocabulary here IS the whitelist.
     */
    public const CONTENT_CLASSES = [
        'bd-callout',    // key-insight box (brand-tinted)
        'bd-tip',        // practical tip box
        'bd-warning',    // caution / "when NOT to" box
        'bd-steps',      // numbered how-to (on <ol>)
        'bd-proscons',   // two-list pros & cons wrapper (on <div> holding two <ul>)
        'bd-verdict',    // short bottom-line/verdict box
        'bd-faq',        // FAQ block inside the body (on a wrapper <div>)
        'bd-table-wrap', // horizontal-scroll wrapper around wide tables
    ];

    /**
     * Comparison-article brief ("X vs Y"). Only added when the batch
     * contains a role=comparison item — the pair itself was chosen
     * deterministically (ComparisonPlanner), never by the writer; the row's
     * "compared_products" field carries each product's real name and
     * resolved attribute facts so the article is grounded in data, not
     * guesses.
     */
    public const COMPARISON_RULES = <<<'RULES'
COMPARISON ARTICLE RULES (this item compares two real, already-chosen products — see "compared_products" in the assignment data for their names and actual attribute facts):
- Structure: a comparison table naming both products by their real names; a facet-by-facet breakdown (only the facets given — never invent specs); a "who should pick which" verdict section; FAQs answering "which one should I buy" style questions.
- Ground every claim in the given facts. Never invent a spec, flavor note, or strength level not present in "compared_products".
- The verdict must be genuinely useful: name which reader profile suits each product, not a vague "both are great" non-answer.
RULES;

    public const FUNNEL_RULES = <<<'RULES'
FUNNEL ARTICLE RULES (this batch was researched by the Content Cluster & Funnel Builder — each item's data includes funnel_stage, cluster, pain_point, search_query, audience_need, outline and required internal link targets):
- BEFORE writing, analyze the provided brief: the outline is the researched table-of-contents idea — expand and refine it, do not ignore it. The pain_point and search_query define the reader; answer THEM, not a generic audience.
- funnel_stage=top: educate and answer, build trust, ZERO selling; product links appear only as natural "if you want to go deeper/try it" references. funnel_stage=middle: help the reader compare, evaluate and choose; product/category links are the natural next step.
- required_links lists researched URLs this article MUST link contextually (they are also in the catalog). Weave every one of them in naturally.
RULES;

    /** Given to EVERY blog batch — the design toolkit that makes articles detail-oriented. */
    public const CLASS_TOOLKIT = <<<'RULES'
DESIGN TOOLKIT (allowed classes, each styled by the site's blog stylesheet — use 2-4 per article where they genuinely help the reader, never decoratively):
  <div class="bd-callout"> key insight worth remembering, <div class="bd-tip"> practical tip, <div class="bd-warning"> caution or "when this is NOT for you", <ol class="bd-steps"> numbered how-to steps, <div class="bd-proscons"> containing exactly two <ul> (pros first, cons second), <div class="bd-verdict"> the bottom-line recommendation, <div class="bd-faq"> in-body FAQ block, <div class="bd-table-wrap"> around any wide comparison table.
These are the ONLY class attributes allowed; anything else is stripped mechanically. Plain semantic tags (h2, h3, p, ul, ol, table, blockquote) are already beautifully styled by the site — never fake structure with the toolkit when a plain tag is right.
RULES;

    /** Stable per-batch instruction block. MUST NOT vary between items (prompt cache). */
    public static function systemFor(AiImportBatch $batch): string
    {
        $base = trim((string) ($batch->system_prompt ?: self::DEFAULT_SYSTEM));

        $sections = [$base];

        if (trim((string) $batch->prompt) !== '') {
            $sections[] = "SITE / TOPIC BRIEF (context for every article):\n".trim($batch->prompt);
        }

        if ($batch->niche) {
            $sections[] = "SUBJECT AREA (all articles in this batch build topical authority around this):\n".trim($batch->niche);
        }

        $targeting = array_filter([
            $batch->target_country ? "Country: {$batch->target_country}" : null,
            $batch->target_city ? "City: {$batch->target_city}" : null,
            $batch->target_language ? "Language/locale: {$batch->target_language} (use its spelling, units, currency conventions)" : null,
            $batch->audience_note ? "Audience: {$batch->audience_note}" : null,
        ]);

        if ($targeting !== []) {
            $sections[] = "LOCAL TARGETING (weave naturally into copy and meta fields — no forced repetition):\n".implode("\n", $targeting);
        }

        $sections[] = self::BLOG_RULES;

        $sections[] = self::SEMANTIC_SEO_RULES;

        // Store-wide facet vocabulary — ONLY when the ecommerce module is on
        // and a taxonomy actually exists. A pure blog has no product facets,
        // so this block (and its DB query) is skipped entirely — no wasted
        // tokens, no store framing leaking into general articles.
        if (ecommerce_enabled() && ($vocabulary = self::attributeVocabulary()) !== '') {
            $sections[] = "PRODUCT FACT VOCABULARY (the store's canonical facets and allowed values):\n"
                ."When an article states a product's flavor family, cooling level, strength, origin, pack size, or device compatibility, use EXACTLY these terms — never invent or paraphrase a facet value.\n\n"
                .$vocabulary;
        }

        // Funnel batches (sent from the Blog Ideas waiting area) carry the
        // full research brief per item.
        if ($batch->funnel_rounds !== null) {
            $sections[] = self::FUNNEL_RULES;
        }

        // Comparison items (from ComparisonPlanner, sent via the same
        // waiting area) carry a deterministically chosen product pair.
        if ($batch->items()->where('row->role', 'comparison')->exists()) {
            $sections[] = self::COMPARISON_RULES;
        }

        // Refresh batches rewrite existing articles (preserve facts, fill gaps).
        if ($batch->refresh) {
            $sections[] = self::REFRESH_RULES;
        }

        // Every blog batch gets the design toolkit — the publisher strips
        // any class outside the whitelist, so this is safe by construction.
        $sections[] = self::CLASS_TOOLKIT;

        // Article-flavored search rulebook (not the product engine's).
        $sections[] = self::BLOG_SEARCH_RULES;

        if (! empty($batch->link_catalog)) {
            $lines = collect($batch->link_catalog)
                ->filter(fn ($p) => ! empty($p['name']) && ! empty($p['url']))
                ->map(fn ($p) => '- '.$p['name'].' — '.$p['url'])
                ->implode("\n");

            // The catalog is labeled per entry (article / blog category / page /
            // home / — and, only when the store module is on, product / product
            // category). The instructions lead with articles so a pure blog
            // links naturally; commercial targets are mentioned only as "when
            // present", never assumed.
            $sections[] = <<<'LINKS'
INTERNAL LINKING (you place the links while writing — this is the ONLY linking pass):
The catalog below lists this site's linkable pages with live URLs (each entry is labeled by type). While writing description_html, link 3-6 of them WHERE the mention genuinely helps the reader:
- a sibling ARTICLE that covers a sub-topic in depth (the natural "next read") — prefer these;
- a BLOG CATEGORY when pointing at a whole topic archive;
- the HOME PAGE at most once, on a natural brand/site mention;
- a PRODUCT or PRODUCT CATEGORY only IF such entries appear in the catalog AND the advice genuinely leads there (never force a commercial link into an informational article).
Rules:
- Links sit INSIDE sentences, in context. NEVER a link list or "related posts" dump at the end.
- Anchor text carries meaning for SEO: the linked page's natural name or a short descriptive phrase — never "click here", "here", "read more", "this page".
- Never link this article to itself. Never invent or alter URLs — copy them exactly from the catalog.
- VARY the linked pages between articles; link only what genuinely fits THIS topic. Not every type must appear in every article.
LINKS."\n\nCATALOG (name — live URL):\n".$lines;
        }

        $tags = collect((array) ($batch->allowed_tags ?: []))->values();
        $tagLine = $tags->isNotEmpty()
            ? 'Allowed HTML tags in description_html: '.$tags->implode(', ').', li, thead, tbody, tr, th, td.'
            : 'Allowed HTML tags: h2, h3, p, ul, ol, li, table, thead, tbody, tr, th, td, blockquote, strong, em, a.';

        $sections[] = "FORMAT: clean semantic HTML — the SITE styles it; you do NOT write CSS.\n{$tagLine}\n"
            .'Class attributes: ONLY the design-toolkit classes listed above. No id or style attributes, no <style> blocks, no <h1> (the page renders the title as H1). The css key MUST be an empty string.';

        $sections[] = <<<'JSON'
OUTPUT: return ONLY a JSON object, no prose, keys:
title, short_description_html, description_html, css, meta_title, meta_description, focus_keyword, image_alt, image_title, image_caption, faqs.
- title: the final article headline (compelling, keyword-bearing, ≤70 chars) — refine the working title if you can beat it.
- short_description_html: the excerpt/dek, 1-2 sentences in one <p>.
- description_html: the full article body (length per the assignment's TARGET LENGTH) with your contextual internal links.
- meta_title ≤ 60 chars; meta_description 150-164 chars, click-worthy, keyword near the front.
- meta_title is used VERBATIM as the page's title tag and must NOT be identical to title (the H1) — phrase it for the search snippet (intent + keyword + differentiator), not a copy of the headline.
- focus_keyword: the primary keyword this article targets.
- image_alt/image_title/image_caption: for the article's featured image, descriptive and natural.
- faqs: array of {question, answer} objects (5-8 items).
- Never invent statistics, studies, reviews, or expert quotes.
JSON;

        return implode("\n\n", $sections);
    }

    /** Per-article message: topic data + rotation directive + batch memory + learned fixes. */
    public static function userPromptFor(AiImportItem $item): string
    {
        $variants = [
            'Open with the direct answer, then how-it-works, then practical guidance; comparison as a short table; FAQ-style H2s in the middle.',
            'Open with a short real-world scenario (2 sentences max), then the direct answer; practical steps as a numbered list; one comparison table late.',
            'Open with the single most surprising concrete fact, then the direct answer; mostly H2+paragraph flow, one bullet list; skip tables unless data demands one.',
            'Open with the definition-style summary sentence; then criteria as H3s under one "how to choose" H2; comparison table early; short conclusion.',
            'Open by naming who this guide is for and the outcome they get; middle sections in question-form H2s; end with a checklist-style summary list.',
            'Open with the direct answer; structure the middle as mistakes-to-avoid (each an H3 with the fix); one table; conclusion links the natural next read.',
        ];

        $directive = $variants[$item->id % count($variants)];
        $digest = static::uniquenessDigest($item);
        $learned = static::learnedFixes($item);

        return "Article assignment:\n".static::compactRow($item->row)
            .static::currentCopyBlock($item->row)
            .($digest !== '' ? "\n\n".$digest : '')
            .($learned !== '' ? "\n\n".$learned : '')
            .static::keywordDirective($item->row)
            ."\n\nTARGET LENGTH: ".static::lengthDirective($item->row)
            ."\n\nSTRUCTURE VARIATION for this article: {$directive}"
            ."\n\nReturn the JSON now.";
    }

    /**
     * Role- and topic-aware length: cluster PILLARS carry the topical
     * authority and go deep; spokes stay tight; everything else scales to
     * what the topic actually holds. Sent per item (the cached system
     * block stays byte-identical).
     */
    public static function lengthDirective(array $row): string
    {
        // An explicit per-article word target from the CSV/field wins over the
        // role default — lets the author size any article precisely.
        $target = (int) preg_replace('/\D+/', '', (string) ($row['target_words'] ?? $row['word_count'] ?? ''));
        if ($target >= 300) {
            $low = max(300, (int) round($target * 0.85));
            $high = (int) round($target * 1.15);

            return "{$low}-{$high} words (author-specified target ~{$target}). Cover the intent fully within this range; depth over padding.";
        }

        return match ($row['role'] ?? null) {
            'pillar' => '1800-2500 words. This is the cluster PILLAR: cover the topic comprehensively (definitions, choices, comparisons, how-tos, honest limitations) so every spoke article can link back to a section here.',
            'spoke' => '900-1500 words. This is a SPOKE: answer its one specific intent completely and link the pillar for breadth — do not drift into sibling topics.',
            'comparison' => '1200-1900 words. This is a COMPARISON article: cover every given facet difference, a comparison table, and a clear verdict — do not pad with generic content once the two options are fully compared.',
            default => '700-1800 words, sized to the topic: a narrow question answered completely may stop near 700; a broad comparison or guide may need the top of the range. Cover the intent fully, then stop.',
        };
    }

    /**
     * Neutral, any-topic keyword directive (the product engine's version says
     * "set by the store owner"). Enforces the placements that actually move
     * rankings: primary keyword in the title, an H2, and the first 100 words.
     */
    public static function keywordDirective(array $row): string
    {
        $keywords = static::keywordsFor($row);

        if ($keywords === []) {
            return '';
        }

        $block = "\n\nTARGET KEYWORDS (the search terms this article must rank for):"
            ."\n- Primary: \"{$keywords[0]}\" — it MUST appear naturally in meta_title, in the first 100 words of description_html, and in at least one H2/H3 heading. Set focus_keyword to it.";

        if (count($keywords) > 1) {
            $block .= "\n- Secondary: ".implode(', ', array_map(fn ($k) => "\"{$k}\"", array_slice($keywords, 1)))
                .' — weave each in naturally ONCE where it genuinely fits (copy, headings, FAQ answers, meta_description). Skip any that cannot fit naturally.';
        }

        return $block."\n- No stuffing: beyond these placements use synonyms and related entities, never forced exact-match repetition.";
    }
}
