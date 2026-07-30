<?php

namespace App\Services\Ai;

use App\Models\AiImportBatch;
use App\Models\AiImportItem;

/**
 * Writes product copy from a CSV row + the batch brief.
 *
 * Token strategy: the ENTIRE instruction set (system prompt, output
 * contract, class list, tag whitelist, competitor brief) is assembled
 * once per batch and kept byte-identical across every item — so
 * provider-side prompt caching (Anthropic cache_control, OpenAI/Gemini
 * automatic prefix caching) means the static block is only paid for
 * once; each product costs little more than its own row data.
 */
class ProductWriter
{
    public function __construct(protected LlmClient $llm) {}

    /**
     * Output-token budget for a full product (long description + FAQs + meta).
     * 8192 was too tight and truncated verbose products mid-JSON; 16000 gives
     * headroom. LlmClient clamps this down to each provider's real ceiling.
     */
    public static function maxOutputTokens(): int
    {
        return max(4096, (int) setting('ai.max_output_tokens', 16000));
    }

    public function write(AiImportItem $item): array
    {
        $batch = $item->batch;

        $output = $this->completeJson(static::systemFor($batch), static::userPromptFor($item));

        $item->update(['ai_output' => $output, 'status' => 'reviewing']);

        return $output;
    }

    /**
     * complete() + parseJson with ONE resample on malformed JSON. A bad
     * sample (unescaped control char, stray prose) is a dice roll — the
     * immediate retry almost always parses, so a whole product must not
     * fail on the first bad draw.
     */
    protected function completeJson(string $system, string $user): array
    {
        try {
            return LlmClient::parseJson($this->llm->complete(
                system: $system,
                user: $user,
                maxTokens: static::maxOutputTokens(),
                cacheStatic: true,
            ));
        } catch (\RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'valid JSON')) {
                throw $e; // provider/API errors keep their own handling
            }

            return LlmClient::parseJson($this->llm->complete(
                system: $system,
                user: $user."\n\nCRITICAL: your previous reply was not parseable JSON. Return ONLY one valid JSON object — no markdown fences, no prose, every string properly escaped.",
                maxTokens: static::maxOutputTokens(),
                cacheStatic: true,
            ));
        }
    }

    /**
     * Claude rewrites its own draft to resolve the reviewer's issues. The big
     * rulebook system block stays cached; only the compact issue list is new.
     *
     * @param  array<string>  $issues
     */
    public function rewrite(AiImportItem $item, array $output, array $issues): array
    {
        $user = "You wrote this product copy. A reviewer found the issues below. "
            ."Return the FULL corrected JSON (same keys) with EVERY issue fixed and nothing else changed unnecessarily.\n\n"
            ."ISSUES TO FIX:\n- ".implode("\n- ", $issues)
            ."\n\nSource data:\n".static::compactRow($item->row)
            .static::keywordDirective($item->row)
            ."\n\nCurrent JSON:\n".json_encode($output, JSON_UNESCAPED_SLASHES)
            ."\n\nReturn only the corrected JSON.";

        $fixed = $this->completeJson(static::systemFor($item->batch), $user);

        // A valid rewrite must carry the content. If the model returned a
        // stray blob (e.g. just {"approved":true}), keep the prior output
        // rather than destroying good copy.
        if (empty($fixed['description_html'])) {
            return $output;
        }

        $item->update(['ai_output' => $fixed]);

        return $fixed;
    }

    /** Stable per-batch instruction block. MUST NOT vary between items. */
    public static function systemFor(AiImportBatch $batch): string
    {
        $base = trim((string) ($batch->system_prompt
            ?: setting('ai.default_system_prompt')
            ?: self::DEFAULT_SYSTEM));

        $sections = [$base];

        // Store brief (the user's prompt) is static per batch → cacheable here.
        $sections[] = "STORE BRIEF (follow it for every product):\n".trim($batch->prompt);

        $targeting = array_filter([
            $batch->target_country ? "Country: {$batch->target_country}" : null,
            $batch->target_city ? "City: {$batch->target_city}" : null,
            $batch->target_language ? "Language/locale: {$batch->target_language} (use its spelling, units, currency conventions)" : null,
            $batch->audience_note ? "Audience: {$batch->audience_note}" : null,
        ]);

        if ($targeting !== []) {
            $sections[] = "LOCAL TARGETING (weave naturally into copy and meta fields — no forced repetition):\n".implode("\n", $targeting);
        }

        if ($batch->competitor_count > 0) {
            $sections[] = "MARKET POSITIONING:\nBefore writing, consider the top {$batch->competitor_count} competing products of this type on the market. "
                .'Write copy that outperforms them for Google ranking: cover the questions competitors leave unanswered, lead with sharper concrete benefits, '
                .'include comparison-ready specifics (materials, dimensions, compatibility, capacity), and use natural language a buyer would search for. '
                .'Never name competitor brands or invent competitor claims.';
        }

        $sections[] = self::WRITING_RULES;

        // Refresh batches rewrite EXISTING copy. This directive is static per
        // batch (cached); the current copy itself rides each item's prompt.
        if ($batch->refresh) {
            $sections[] = self::REFRESH_RULES;
        }

        $sections[] = self::SEMANTIC_SEO_RULES;

        $sections[] = self::SEARCH_ENGINE_RULES;

        // Attribute vocabulary is store-wide, static data (like link_catalog
        // below) — one prompt-cache hit covers the whole batch.
        if (($vocabulary = self::attributeVocabulary()) !== '') {
            $sections[] = self::ATTRIBUTE_RULES."\n\nATTRIBUTE VOCABULARY (attribute: allowed values):\n".$vocabulary;
        }

        // Catalog + linking rules are STATIC per batch: the full URL list is
        // paid for once (provider prompt cache), never re-sent per product.
        if (! empty($batch->link_catalog)) {
            $lines = collect($batch->link_catalog)
                ->filter(fn ($p) => ! empty($p['name']) && ! empty($p['url']))
                ->map(fn ($p) => '- '.$p['name'].(! empty($p['type']) ? ' ('.$p['type'].')' : '').' — '.$p['url'])
                ->implode("\n");

            $sections[] = self::LINKING_RULES."\n\nCATALOG (name — live URL; entries marked (category)/(guide) are not products):\n".$lines;
        }

        $sections[] = self::formatContract($batch);

        $sections[] = <<<'JSON'
OUTPUT: return ONLY a JSON object, no prose, keys:
short_description_html, description_html, css, suggested_price, meta_title, meta_description, focus_keyword, secondary_keywords, image_alt, image_title, image_caption, faqs, attributes.
- short_description_html: one short <p> hook (1-2 sentences, benefit-led), then a <ul> of 4-6 key-fact bullets. Each bullet is "<strong>Label:</strong> fact" — draw the labels that fit THIS product from: Flavor, Strength, Cooling, Pack (e.g. "1 carton = 10 packs x 20 sticks = 200 sticks"), Compatibility (e.g. "IQOS ILUMA series only"), Origin/Variant, Color/Type (devices), Delivery. Facts only, pulled from the product data — never marketing filler, never a fact you were not given.
- faqs: array of {question, answer} objects (6-10 items).
- description_html: 400-800 words total. Compact, high-density copy — never pad.
- description_html carries your contextual internal links (per INTERNAL LINKING, when a catalog is provided).
- suggested_price: number; if unsure return the given sale or regular price.
- meta_title <= 60 chars; meta_description 150-164 chars, click-worthy, keyword near the front.
- meta_title is used VERBATIM as the page's title tag and must NOT be identical to the product name (the H1). Write it as a search snippet that beats competitor listings: intent word + product + a concrete differentiator (city, delivery promise, price angle), e.g. "Buy TEREA Amber in Dubai | 1-Hour Delivery".
- image_alt/image_title/image_caption: descriptive, keyword-aware, natural.
- secondary_keywords: array of 3-5 SHORT related search phrases real buyers use for this product (synonyms, need-based phrasings, attribute-led variants like "smooth menthol sticks") — never city-suffixed copies of the focus keyword, never invented product names.
- attributes: object per STRUCTURED ATTRIBUTES below (omit/empty object if no vocabulary was given).
- Never invent certifications, reviews, ratings, or guarantees.
JSON;

        return implode("\n\n", $sections);
    }

    protected static function formatContract(AiImportBatch $batch): string
    {
        $tags = collect((array) ($batch->allowed_tags ?: []))->values();
        $tagLine = $tags->isNotEmpty()
            ? 'Allowed HTML tags in description_html: '.$tags->implode(', ').', li, thead, tbody, tr, th, td. Use NOTHING else.'
            : 'Allowed HTML tags: h2, h3, p, ul, ol, li, table, thead, tbody, tr, th, td, blockquote, strong, em, a.';

        return match ($batch->output_format) {
            'html_plain' => "FORMAT: plain semantic HTML.\n{$tagLine}\n"
                .'No class attributes, no id attributes, no style attributes, no <style> blocks. The css key must be an empty string.',

            'html_classes' => "FORMAT: HTML using ONLY the site's existing CSS classes below. Do not invent new classes. The css key must be an empty string.\n"
                .$tagLine."\nAVAILABLE CLASSES (use exactly these names):\n".trim((string) $batch->custom_classes),

            default => "FORMAT: clean, well-structured semantic HTML — the STORE styles it (modern typography, bordered spec tables, spacing); you do NOT write CSS.\n{$tagLine}\n"
                .'Structure the copy with <h2>/<h3> section headings, short <p> paragraphs, <ul> bullet lists, and a <table> for specs. '
                .'You MAY add "pd-" prefixed class names for structure (e.g. pd-hero, pd-specs), but they are optional and unstyled by you. '
                .'The css key MUST be an empty string — never output <style> blocks or inline style="" attributes.',
        };
    }

    /** Full-detail store brief template — inserted via the form's "Use detailed template" action. */
    public const DEFAULT_STORE_PROMPT = <<<'PROMPT'
BUSINESS: [Store name] — online store selling [product category] in [city/country].
SELLING UNIT (hard fact — the AI must never contradict it): [exact unit sold, e.g. "full cartons only: 1 carton = 10 packs = 200 sticks; we never sell single packs"].
AUDIENCE: [who buys — age range, experience level, what they care about, budget sensitivity].
TONE: confident, knowledgeable, human — like an experienced shop assistant, not an ad. No hype words.
SEO ANGLE: target "[product name] + [city/country]" style keywords; buyers search with purchase intent
(e.g. "buy X in Dubai", "X price UAE", "X vs Y which is better").
MUST INCLUDE PER PRODUCT: what it is + who it suits in the first 2 sentences; honest strength/intensity guidance;
explicit device/version compatibility AND what it is NOT compatible with; local delivery specifics
([same-day in city], [next-day nationwide]); authenticity assurance ([sourced from official distributors]).
COMPARISONS: reference sibling products from the catalog naturally (link them) — help the buyer pick between them.
AVOID: health claims (say "smoke-free experience", never "healthier"); fake urgency; invented reviews or awards;
any spec not present in the product data.
PRICING CONTEXT: prices in [currency]; position value honestly (why it costs what it costs).
CTA STYLE: calm and benefit-anchored ("Order before [cutoff] for same-day delivery") — never "Buy now!!".
PROMPT;

    public const DEFAULT_SYSTEM = 'You are a senior ecommerce copywriter and SEO specialist. '
        .'Write conversion-focused, benefit-led product copy that reads like an experienced, knowledgeable '
        .'human — never like AI or generic marketing. It must rank in Google AND in AI search '
        .'(ChatGPT Shopping, Google SGE, Perplexity), and convert commercial-investigation and '
        .'transactional buyers. Honest about limitations; specificity over superlatives.';

    /**
     * Writing rulebook distilled from the seo-product-category-writing skill
     * (Semrush/Ahrefs/WooCommerce/Google Search Central synthesis). Static
     * per batch — cached by the provider, so it costs one send per batch.
     */
    public const WRITING_RULES = <<<'RULES'
WRITING RULES (mandatory):

The section list below is a GUIDE, not a fixed template. Every product page must feel unique, natural, and written for real buyers — never copied from one repeated layout. Follow the required information, but mix the presentation per product.

OPENING (vary it — never the same intro style twice in a row across a catalog):
- The intro covers: product name, main flavor or device benefit, who it is best for, the delivery promise, and the main buying keyword naturally (e.g. "Buy [Product] in UAE" style intent) — but REWRITE it fresh for each product.
- Valid openings include: a buying-focused intro; the flavor experience itself; device performance; the best-use case; a buyer-question hook answered immediately; 4-6 quick highlights BEFORE the intro paragraph; or price/delivery near the top. The STRUCTURE VARIATION directive in the product data picks one — follow it.
- Sentence 1 still follows the machines-first pattern: [product type] + [key differentiating attribute] + [1-2 specs]. Primary keyword appears naturally in the first 100 words. Benefits before features.

SECTIONS TO DRAW FROM (pick 8-12 that fit THIS product — never all, never the same subset or order every time):
1. Intro / short overview (see OPENING).
2. Key highlights — flavor/device type, strength, pack quantity, compatibility, origin/variant, delivery cities, stock. Present as bullets, short cards, or a compact table — rotate the layout between products.
3. Flavor details / product experience (consumables): first taste → inhale feel → aftertaste → cooling level → strength → aroma → best time to use → closest-flavor comparison. Concrete, comparative language ("lighter than X, bolder than Y") — never "great flavor" or "fresh finish". For DEVICES instead: design & build, battery, charging, heating system, display/buttons, size and portability.
4. Specification table — clean labels, one fact per row; only fields that fit the product (name, brand, flavor, strength, quantity, compatibility, country/variant, price, availability, delivery area). Never force empty fields.
5. Customer benefits — benefits, not features (Feature → Advantage → Benefit). Product-specific angles: flavor consistency, easy replacement, travel-friendly, stronger cooling, balanced taste. Never list a feature without its benefit.
6. Package info / what's in the box — packs, sticks/pods per pack, sealed + authentic packaging note; devices: charger/cable, cleaning tool, manual, warranty note if available. Storage advice (away from heat/sunlight/moisture) where relevant.
7. Compatibility details (prevents wrong orders): which devices it works with AND an explicit "Not compatible with …" plus any warning.
8. How to use — short, clear steps (insert → start → wait for vibration/light → session ends → dispose; devices: charge → insert → heat → clean). Keep brief unless the product needs more.
9. Best for / who should choose this — segment by flavor preference, experience level, strength, portability needs. Product-specific.
10. Who should choose another option — an honest "not ideal if you prefer strong menthol / very light flavor / your device is X" note, ideally pointing to the better sibling (internal link). Builds trust.
11. Compare with related products — by flavor type, strength, cooling, best-for, price, with internal links. Present as a table, product cards, or a short paragraph — rotate the style; compare DIFFERENT siblings per product, only genuinely relevant ones.
12. Pricing information — current price, offer price, bulk option, free-delivery threshold, COD if available. Mention price near the top or middle when it helps conversion; never hide it only at the bottom.
13. Delivery information — local city specifics where targeting is set, keywords woven naturally. NEVER repeat the same delivery paragraph word-for-word across products; vary phrasing and city emphasis.
14. Authenticity / quality check — sealed pack, fresh stock, trusted sourcing, quality-checked before delivery, clear return/exchange condition.
15. Ingredients / technology — consumables: flavor profile, cooling note, aroma, nicotine format, tobacco/menthol character. Devices: heating technology, battery performance, safety features, charging. Factual only.
16. Safety / responsible-use note (nicotine, vape, heated-tobacco and similar products): adult users only, contains nicotine where applicable, not for minors, use only with compatible devices, store safely. Professional and short (2-3 lines).
17. Where to buy — local buying-intent heading (e.g. "Where to Buy [Product] in Dubai, Ajman, Sharjah and Abu Dhabi"), specific to the product and location — never a generic paragraph.
18. Why buy from us — fast delivery, fresh sealed stock, easy ordering, support, secure payment/COD, product guidance. Reworded per product.
19. FAQ (see FAQS below).
20. Final summary — short and conversion-focused: best reason to buy, best user type, delivery promise, a fresh CTA. Never the same CTA twice; never AI-style CTAs ("Order now and elevate your experience today").

BULLETS: one idea per bullet, never compound. Vary bullet lengths and sentence structures.
PARAGRAPHS: 2-4 sentences max. Mix short punchy sentences with longer ones. Never repeat the same sentence structure twice in a row.

BANNED PHRASES (never use, in any form):
"When it comes to", "In today's world", "Designed to", "Elevate your experience", "Take your X to the next level", "Perfect choice", "Game changer", "Wide range", "Extensive selection", "We are proud to offer", "Welcome to our", "Are you looking for", "Whether you're a beginner or expert", "Look no further", "Unleash", "Unlock".
Replace with the specific fact or benefit each hides.

BANNED AI-STYLE WORDS (these read as machine-written — never use them):
"delve", "delving", "embark", "tapestry", "testament to", "seamless", "seamlessly", "meticulously", "cutting-edge", "state-of-the-art", "revolutionize", "revolutionary", "harness the power", "ever-evolving", "navigating the", "in the world of", "dive into", "let's explore", "it's important to note", "in conclusion", "in summary", "crafted to". Use plain, specific, human wording instead.

PUNCTUATION (hard rule, checked mechanically): never use em dashes (—) or en dashes (–) ANYWHERE, not in the copy, headings, meta fields, FAQs, or image text. Use commas, periods, or parentheses instead. Any dash found fails review.

TARGET KEYWORDS: when the product data includes a TARGET KEYWORDS block, it is the store owner's SEO plan for that product and overrides your own keyword choices. The primary keyword goes in meta_title, the first 100 words, one heading, and focus_keyword; each secondary keyword appears once where it fits naturally. Beyond those placements use synonyms and related entities — never forced exact-match repetition.

CLAIMS: no medical or health claims — never say a nicotine product is healthy, healthier, or safe (say "smoke-free experience" for heated tobacco, nothing more).

EEAT: demonstrate experience with practical observations ("you notice it on the finish, not the inhale"); use correct technical terms; acknowledge what the product is NOT good for when honest; realistic expectations, no fabricated social proof or scarcity.

FAQS: also return a "faqs" key — 6-10 buyer questions with direct 2-4 sentence answers, based on real pre-purchase doubts (compatibility, taste, pack contents, delivery cities and speed, originality, price, COD, strong-or-light, beginner suitability, which sibling to pick instead). Different questions AND answers for every product. These power FAQPage rich results.

UNIQUENESS (critical — duplicate structure reads as duplicate content):
- Do NOT copy the section names above verbatim. Write every heading in your own words for THIS product ("Flavor Profile" might become "How TEREA Sienna Actually Tastes").
- No two products in a catalog may share identical headings, opening sentences, sentence patterns, CTA, delivery wording, or the same set of internal links.
- Rotate presentation: if the last product used a comparison table, use cards or a paragraph here; if highlights were bullets, consider a compact table.
- Follow the STRUCTURE VARIATION directive in the product data for the opening style and section ordering.
RULES;

    /**
     * Semantic-SEO rulebook: entity coverage, descriptive (non-keyword-
     * repeating) headings, and a buyer-question checklist for the FAQs —
     * additive detail on top of WRITING_RULES, not a replacement.
     */
    public const SEMANTIC_SEO_RULES = <<<'RULES'
SEMANTIC SEO (mandatory — write for topics and entities, not repeated keywords):

ENTITY COVERAGE: where genuinely relevant to THIS product, mention real-world entities naturally so the page demonstrates topical depth: IQOS, TEREA, ILUMA / ILUMA i / ILUMA i PRIME, Heated Tobacco, Smartcore Induction System, Philip Morris International, Menthol, Tobacco blend. Never force one in, never list them, never repeat the same entity in consecutive sentences.

SEMANTIC HEADINGS (each heading must TEACH something about THIS product):
- The test for every H2/H3: could a buyer skim ONLY the headings and still learn this product's story? If a heading says nothing concrete, rewrite it.
- Good headings carry a real fact or benefit: "Smooth Menthol With a Cool Citrus Finish", "One Carton = 10 Packs of 20 Sticks", "Works With Every ILUMA Device, Not Blade Models", "Milder Than Bronze, Richer Than Yellow".
- BANNED heading styles: generic template labels that fit any product unchanged ("Overview", "Key Features", "Product Details", "Flavor Profile", "Why Choose Us", "Specifications", "Conclusion") AND meaningless abstractions ("A Journey of Taste", "Experience Excellence").
- The product name or focus keyword MAY appear in 1-2 headings where it reads naturally — that is good SEO. Just never in MOST headings (that reads as stuffing).
- This sits on top of the UNIQUENESS rule (no repeated headings ACROSS products in the batch).

GROUNDED COMPARISONS: when writing the "compare with related products" section (WRITING RULES #11), ground it in the SPECIFIC facet that differs between the two products (flavor family, cooling level, or tobacco strength — see the ATTRIBUTE VOCABULARY below when given), not vague adjectives like "bolder" or "smoother" alone.

CONCRETENESS (kills thin content): every section must contain at least one verifiable specific — a number (stick count, pack count, price, delivery hours), a named device model, a named sibling flavor, a named city, or a named attribute value. A paragraph with zero specifics is filler: cut it or make it concrete. Write like a shop assistant who has SOLD this product, not a copywriter who has only heard of it — real usage observations ("the capsule click is firm", "one pack lasts a typical day at 20 sticks"), realistic expectations, no abstract praise.

FAQ CHECKLIST: the 6-10 FAQs should sample from this buyer-doubt checklist (never all of them, never the same subset every product): device compatibility, flavor/cooling/strength profile, who it suits (beginner vs experienced), pack contents, delivery cities and speed, authenticity/sourcing, price positioning versus a named sibling, which sibling to pick instead.
RULES;

    /**
     * Search-engine + AI-answer-engine rulebook. Covers Google (helpful
     * content, E-E-A-T, PageRank), Bing guidelines, AI Overviews / answer
     * engines (AIO), and UGC-style authenticity. Static per batch → the
     * whole block is served from the provider's prompt cache.
     */
    public const SEARCH_ENGINE_RULES = <<<'RULES'
SEARCH & AI-ANSWER OPTIMIZATION (mandatory — the copy must rank on Google AND Bing AND get cited by AI answer engines):

GOOGLE (Helpful Content + E-E-A-T + PageRank):
- People-first: every section must answer a real buyer question or remove a purchase doubt. Delete anything that exists only "for SEO".
- Show first-hand experience: practical observations, realistic expectations, correct technical terminology — this is what Google's quality systems reward.
- PageRank flows through internal links: link related products with descriptive anchor text (the linked product's name or a natural phrase) placed inside relevant sentences — that builds authority paths and crawl routes. NEVER generic anchors ("click here", "this product", "read more").
- Primary keyword early and natural; after that use synonyms and related entities (brand, model, category, use-case terms) instead of repeating the exact phrase — unnatural repetition reads as stuffing.

BING (Bing Webmaster Guidelines):
- Bing weighs exact keyword placement more literally than Google: put the exact product term in the meta_title, in at least one H2, and in the first paragraph.
- Bing favors clean, literal structure: short paragraphs, bullet lists, and tables parse and rank well.
- The meta_description is used more directly in Bing results — make it a clean, complete one-sentence summary of the page.

AI ANSWER ENGINES / AIO (Google AI Overviews, ChatGPT, Perplexity, Bing Copilot):
- Use question-form H2/H3 headings where natural ("Is TEREA Amber compatible with ILUMA i?") and follow each IMMEDIATELY with a direct, self-contained 40-60 word answer — that block is exactly what gets quoted.
- Citable specifics beat adjectives: numbers, dimensions, counts, materials, battery life, pack contents. AI engines cite facts, not "premium quality".
- Near the top, include one definition-style sentence ("<Product> is a <category> that <key benefit + spec>") an engine can lift as a summary.
- FAQ answers must stand alone when quoted out of context: name the product inside the answer; never answer with only "Yes" or "it does".
- Tables and lists are extraction-friendly — the spec table doubles as AI-answer source material.

UGC-STYLE AUTHENTICITY:
- Experience/sensory sections should read like a knowledgeable owner's genuine account (expert-review / forum tone), not a brochure.
- Include one honest trade-off or limitation — authenticity is both a ranking signal and a trust signal.
- NEVER fabricate reviews, star ratings, "customers say" claims, or usage statistics.
RULES;

    /**
     * Contextual linking contract. The catalog itself is appended by
     * systemFor() — once per batch, cached. The AI does the linking while
     * writing; there is no separate linking pass and no extra tokens.
     */
    public const LINKING_RULES = <<<'RULES'
INTERNAL LINKING (you place the links while writing — this is the ONLY linking pass):
The catalog below lists this store's products, category pages, and guides with their live URLs. While writing description_html, link 2-4 of them WHERE the mention genuinely helps the buyer — comparisons ("noticeably bolder than <a>TEREA Sienna</a>"), compatible devices/refills, and the alternatives section.
- SEMANTIC LINK FLOW: when the catalog contains (category) entries, link this product's parent category ONCE in natural context (e.g. "part of our <a>TEREA Japan</a> range"). When a (guide) entry genuinely helps the buyer decide (a flavor guide, a compatibility explainer), link it once. Product pages that link up to their category and out to one guide build the store's topic structure.
- Links sit INSIDE sentences, in context. NEVER a link list or "related products" dump at the end of the copy.
- Anchor text = the linked product's natural name or a short descriptive phrase.
- Never link this product to itself. Never invent or alter URLs — copy them exactly from the catalog.
- Not every catalog product needs a link; pick only the few that truly fit this product's copy.
- VARY the linked siblings from product to product — do not link the same set of products on every page; link what is genuinely closest to THIS product (same flavor family, same strength tier, the natural upgrade/alternative).
RULES;

    /**
     * Structured-attribute classification (semantic SEO). The vocabulary is
     * injected by systemFor() from the live Attribute/AttributeValue
     * taxonomy — this constant only carries the instruction, not the values,
     * so it stays byte-identical (and cached) even as the vocabulary grows.
     */
    public const ATTRIBUTE_RULES = <<<'RULES'
STRUCTURED ATTRIBUTES (also return an "attributes" key):
- Classify this product against the store's canonical attribute vocabulary below. Return an object keyed by the attribute names shown (e.g. "flavor_family", "cooling_level").
- Pick the closest matching value from the vocabulary given for each attribute — never invent a value that is not in the list.
- device_compatibility may be an array when the product genuinely supports more than one device.
- If a fact is genuinely not knowable from the product data, leave that key null rather than guessing.
RULES;

    /**
     * Live attribute taxonomy as a compact "key: value, value, ..." block,
     * keyed by the snake_case name the AI must use in its "attributes"
     * output object. Empty when no attributes are seeded yet — the writer
     * then simply omits ATTRIBUTE_RULES from the prompt.
     */
    public static function attributeVocabulary(): string
    {
        return \App\Models\Attribute::with('values')->get()
            ->filter(fn ($attribute) => $attribute->values->isNotEmpty())
            ->map(fn ($attribute) => str_replace('-', '_', $attribute->slug).': '.$attribute->values->pluck('value')->implode(', '))
            ->implode("\n");
    }

    /**
     * Per-product message. Includes a deterministic structure-variation
     * directive (rotating by item) so no two products in a batch share the
     * same section order — while the big system block stays byte-identical
     * and cached. For bulk runs, a compacted digest of earlier products
     * (headings + openers only, not full conversations) enforces
     * uniqueness at a fraction of the token cost.
     */
    /** Static per-batch refresh directive (cached with the system block). */
    public const REFRESH_RULES = <<<'RULES'
REFRESH MODE (you are REWRITING existing published copy, not creating a new product):
- The item includes the CURRENT PUBLISHED COPY. Read it first and analyze it critically.
- PRESERVE every real fact from it: specifications, quantities, pack/carton size, flavor/strength notes, compatibility, prices, delivery terms, brand, origin/variant. Never drop or contradict a true fact, and never invent a new one to fill space.
- REWRITE everything else in full — do not lightly edit. Produce a genuinely better page: sharper machines-first opening, clearer E-E-A-T (first-hand specifics, honest trade-off, correct terminology), tighter benefit-led sections, question-form H2s each followed by a direct 40-60 word answer, a clean spec table, and 6-10 fresh FAQs.
- COMPETITOR-GAP FILL: cover the buyer questions and comparison angles that competing pages for this product answer but the current copy misses (compatibility edge cases, "which to choose vs a sibling", storage, authenticity, delivery specifics). Add what is missing; remove filler and repetition.
- Keep or improve the internal links; never invent URLs.
- The output fully REPLACES the current description. Return the complete JSON as usual.
RULES;

    public static function userPromptFor(AiImportItem $item): string
    {
        $variants = [
            'Buying-focused intro (name + benefit + delivery promise). Standard section order. Comparison as a short paragraph. Price info mid-page.',
            'Open with the flavor experience (or device performance) itself; price and delivery near the top; spec table in the last third; comparison as a table.',
            'Put 4-6 key highlights (bullets) BEFORE the intro paragraph; compatibility immediately after; spec table near the end, just before the FAQ; comparison as short cards.',
            'Open with the best-use case / who this suits; bring the comparison/alternatives section early (second or third); merge package contents and storage into one section; comparison as a paragraph.',
            'Open the overview with a buyer-question hook and answer it in the first two sentences; delivery details mid-page; comparison as a compact table; include a "who should choose another option" note.',
            'Lead with customer benefits right after a one-line overview; an honest "not ideal if…" note early; price near the top; comparison as cards linking 2-3 siblings.',
            'Start with a definition-style summary sentence, then key highlights as a compact table; how-to-use before the spec table; comparison as a short paragraph late in the copy.',
            'Open with the strongest differentiator vs the closest sibling (link it in sentence one or two); spec table early; delivery and authenticity merged into one trust section near the end.',
        ];

        $directive = $variants[$item->id % count($variants)];
        $digest = self::uniquenessDigest($item);
        $learned = self::learnedFixes($item);
        $categoryContext = static::categoryContextFor($item->row);

        return "Product data:\n".static::compactRow($item->row)
            .static::currentCopyBlock($item->row)
            .($categoryContext !== '' ? "\n\n".$categoryContext : '')
            .($digest !== '' ? "\n\n".$digest : '')
            .($learned !== '' ? "\n\n".$learned : '')
            .static::keywordDirective($item->row)
            ."\n\nSTRUCTURE VARIATION for this product: {$directive}"
            ."\n\nReturn the JSON now.";
    }

    /**
     * The current published copy, injected on refresh batches so the writer
     * can analyze and preserve its facts. Empty string when not refreshing.
     */
    public static function currentCopyBlock(array $row): string
    {
        $current = $row['_current'] ?? null;
        if (! is_array($current) || $current === []) {
            return '';
        }

        $parts = ['CURRENT PUBLISHED COPY (rewrite this fully; preserve every fact):'];
        foreach (['meta_title' => 'Meta title', 'meta_description' => 'Meta description', 'short_description' => 'Short description', 'description' => 'Description'] as $key => $label) {
            $val = trim(strip_tags((string) ($current[$key] ?? '')));
            if ($val !== '') {
                $parts[] = $label.': '.mb_substr($val, 0, 4000);
            }
        }

        return "\n\n".implode("\n", $parts);
    }

    /**
     * The product's place in the store taxonomy, resolved from the row's
     * category_id / category columns against EXISTING categories (nothing is
     * created here — the publisher owns creation). Includes the full mother
     * chain and the category's own positioning text, so the writer knows the
     * range this product belongs to and writes copy that fits it — and links
     * the right (category) catalog entry per SEMANTIC LINK FLOW.
     */
    public static function categoryContextFor(array $row): string
    {
        $ids = collect(preg_split('/[|,;]+/', (string) ($row['category_id'] ?? '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter();

        $slugs = collect(preg_split('/[|,;]+/', (string) ($row['category'] ?? '')))
            ->map(fn ($name) => \Illuminate\Support\Str::slug(trim($name)))
            ->filter();

        if ($ids->isEmpty() && $slugs->isEmpty()) {
            return '';
        }

        $categories = \App\Models\Category::query()
            ->where(fn ($q) => $q->whereIn('id', $ids->all())->orWhereIn('slug', $slugs->all()))
            ->get();

        if ($categories->isEmpty()) {
            return '';
        }

        $lines = $categories->map(function ($category) {
            $trail = collect($category->breadcrumbTrail())->pluck('name')->implode(' > ');
            $about = trim(preg_replace('/\s+/', ' ', strip_tags((string) $category->content_block)));

            return '- '.$trail.($about !== '' ? ' — '.mb_substr($about, 0, 220) : '');
        })->implode("\n");

        return 'CATEGORY CONTEXT (where this product sits in the store; write copy that fits the range: '
            .'use its terminology, mention the parent range naturally, never contradict its positioning):'
            ."\n".$lines;
    }

    /**
     * The per-product TARGET KEYWORDS instruction block ('' when the row has
     * no keywords). Shared by the first write AND every rewrite, so a
     * correction pass can never drift off the store owner's keyword plan.
     */
    public static function keywordDirective(array $row): string
    {
        $keywords = self::keywordsFor($row);

        if ($keywords === []) {
            return '';
        }

        $block = "\n\nTARGET KEYWORDS (set by the store owner — these drive the SEO for this product):"
            ."\n- Primary: \"{$keywords[0]}\" — it MUST appear naturally in meta_title, in the first 100 words of description_html, and in one H2/H3 heading. Set focus_keyword to it.";

        if (count($keywords) > 1) {
            $block .= "\n- Secondary: ".implode(', ', array_map(fn ($k) => "\"{$k}\"", array_slice($keywords, 1)))
                .' — weave each in naturally ONCE where it genuinely fits (copy, headings, FAQ answers, meta_description). Skip any that cannot fit naturally.';
        }

        return $block."\n- No stuffing: beyond these placements use synonyms and related terms, never forced exact-match repetition.";
    }

    /**
     * The reviewer's recurring fixes from earlier products in THIS batch,
     * fed back into the writer so later products pre-empt the same mistakes.
     * Saved to ai_fix_prompts (scope=batch) and reused — this is what makes a
     * 20-product batch get cleaner and cheaper as it progresses.
     */
    public static function learnedFixes(AiImportItem $item): string
    {
        $digest = \App\Models\AiFixPrompt::where('batch_id', $item->batch_id)
            ->where('scope', 'batch')
            ->value('instructions');

        if (! $digest) {
            return '';
        }

        return "LEARNED FIXES (recurring issues the reviewer caught on earlier products — avoid all of these up front):\n".trim($digest);
    }

    /**
     * Conversation compacting for bulk batches: instead of replaying earlier
     * products' full outputs (thousands of tokens each), later items get a
     * terse digest of the headings and openings already used — enough to
     * guarantee cross-product uniqueness for ~20+ product runs while adding
     * only a few hundred tokens per item.
     */
    public static function uniquenessDigest(AiImportItem $item): string
    {
        $previousOutputs = $item->batch->items()
            ->whereNotNull('ai_output')
            ->where('id', '<', $item->id)
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('ai_output');

        $headings = [];
        $openers = [];

        foreach ($previousOutputs as $output) {
            $html = (string) ($output['description_html'] ?? '');

            if (preg_match_all('~<h[23][^>]*>(.*?)</h[23]>~is', $html, $matches)) {
                foreach ($matches[1] as $heading) {
                    $headings[] = mb_substr(trim(strip_tags($heading)), 0, 70);
                }
            }

            $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

            if ($text !== '') {
                $openers[] = mb_substr($text, 0, 90).'…';
            }
        }

        $headings = array_slice(array_values(array_unique(array_filter($headings))), 0, 24);
        $openers = array_slice($openers, 0, 6);

        if ($headings === [] && $openers === []) {
            return '';
        }

        $digest = 'BATCH MEMORY (compacted from products already written in this batch — do NOT reuse any of it):';

        if ($headings !== []) {
            $digest .= "\nHeadings already used (write different ones):\n- ".implode("\n- ", $headings);
        }

        if ($openers !== []) {
            $digest .= "\nOpenings already used (open differently):\n- ".implode("\n- ", $openers);
        }

        return $digest;
    }

    /** Row columns that are pipeline plumbing, not writing material. */
    protected const INTERNAL_COLUMNS = ['image_link', 'image', 'image_url', 'img', 'photo', 'picture', 'idea_id', 'publish_date', 'publish_time', 'compared_product_ids', 'category_id', '_current'];

    /** Compact a CSV row: drop empties + internal columns, truncate long values. */
    /**
     * Comma-separated target keywords from the CSV "keywords" column.
     * The FIRST keyword is the primary; the rest are secondary variations.
     *
     * @return array<int, string>
     */
    public static function keywordsFor(array $row): array
    {
        return collect(explode(',', (string) ($row['keywords'] ?? '')))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function compactRow(array $row): string
    {
        return collect($row)
            ->except(self::INTERNAL_COLUMNS)
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->map(fn ($v, $k) => $k.': '.mb_substr(trim(preg_replace('/\s+/', ' ', (string) $v)), 0, 500))
            ->implode("\n");
    }
}
