<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AI category-description writer — the ProductWriter agent adapted to
 * category pages. Grounding input is the category's OWN products (titles +
 * short descriptions + prices), so the copy can only claim what the store
 * actually stocks. The model additionally analyzes how the top competing
 * category pages in the market present this product type (never naming
 * them), does the keyword research for the category, and returns content +
 * SEO title/meta + FAQs in one JSON response.
 *
 * Deterministic gates from the product pipeline are reused verbatim:
 * ContentReviewer::lint (banned AI phrases, em dashes, meta-vs-H1, invented
 * URLs, heading stuffing) with ONE corrective rewrite, stripEmDashes,
 * clampMetaLengths, and HtmlSanitizer on the stored HTML.
 */
class CategoryWriter
{
    public function __construct(protected LlmClient $llm) {}

    /** Providers the action can offer (key = setting suffix). */
    public const PROVIDERS = ['anthropic' => 'Claude', 'openai' => 'OpenAI', 'gemini' => 'Gemini'];

    public static function forProvider(string $provider, ?string $model = null): self
    {
        return new self(LlmClient::for($provider, $model)->withContext('category'));
    }

    /** True when at least one provider API key is configured. */
    public static function available(): bool
    {
        foreach (array_keys(self::PROVIDERS) as $provider) {
            if (filled(setting("ai.{$provider}_api_key"))) {
                return true;
            }
        }

        return false;
    }

    // ── Background-run status (cache-backed) ─────────────────────────
    // The write runs in a detached process (see the category:write command),
    // so the admin request never blocks. Status lives in cache; the edit
    // page reads it to show progress / the result.

    public static function statusKey(int $categoryId): string
    {
        return "category-ai.status.{$categoryId}";
    }

    /** @return array{status: string, message: string, at: string}|null */
    public static function status(int $categoryId): ?array
    {
        return Cache::get(self::statusKey($categoryId));
    }

    public static function setStatus(int $categoryId, string $status, string $message = ''): void
    {
        Cache::put(self::statusKey($categoryId), [
            'status' => $status, // running | done | failed
            'message' => $message,
            'at' => now()->toDateTimeString(),
        ], now()->addHour());
    }

    public static function clearStatus(int $categoryId): void
    {
        Cache::forget(self::statusKey($categoryId));
    }

    /**
     * Write → review → fix, like the product pipeline. Each cycle the draft
     * is reviewed by the deterministic lint PLUS (when given) an independent
     * LLM reviewer — cross-check mode: a different model critiques, the
     * writer fixes. Like products, the lint has the final word: reviewer
     * nitpicks never block, they're returned as notes.
     *
     * @param  int  $passes  max fix cycles after the first draft
     * @return array{output: array, issues: list<string>, passes_used: int}
     */
    public function write(Category $category, string $notes = '', int $passes = 1, ?LlmClient $reviewer = null): array
    {
        $system = self::systemFor();
        $user = self::userPromptFor($category, $notes);

        $output = $this->completeJson($system, $user);
        $passesUsed = 0;
        $issues = [];

        for ($cycle = 0; ; $cycle++) {
            $lint = $this->lint($category, $output);
            $critique = $reviewer !== null
                ? $this->critique($reviewer, $category, $output, $lint)
                : ['approved' => $lint === [], 'issues' => $lint];

            $issues = array_values(array_unique(array_merge($lint, (array) $critique['issues'])));

            // Done when both reviewers are satisfied — or out of cycles, in
            // which case the deterministic lint decides (reviewer notes are
            // reported, not blocking; the admin reviews the form anyway).
            if (($critique['approved'] && $lint === []) || $cycle >= max(0, $passes)) {
                break;
            }

            $output = $this->completeJson($system, $user
                ."\n\nA reviewer found these issues in your previous draft — return the FULL corrected JSON with every one fixed:\n- "
                .implode("\n- ", $issues));
            $passesUsed++;
        }

        $output = ContentReviewer::clampMetaLengths(ContentReviewer::stripEmDashes($output));

        return ['output' => $output, 'issues' => $issues, 'passes_used' => $passesUsed];
    }

    /**
     * Independent LLM critique of the draft (cross-check seat). Deterministic
     * lint findings are injected so the model folds them into its verdict.
     * Reviewer infrastructure failures never kill a good draft — fall back
     * to the lint alone.
     *
     * @return array{approved: bool, issues: list<string>}
     */
    protected function critique(LlmClient $reviewer, Category $category, array $output, array $lint): array
    {
        try {
            $parsed = LlmClient::parseJson($reviewer->complete(
                self::REVIEWER_RULES,
                'CATEGORY: '.$category->name
                ."\n\nDRAFT JSON:\n".json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                .($lint !== [] ? "\n\nAUTOMATED CHECKS ALREADY FAILED (must be fixed, include them):\n- ".implode("\n- ", $lint) : '')
                ."\n\nReturn only the verdict JSON.",
                maxTokens: 1500,
                cacheStatic: true,
            ));

            return [
                'approved' => (bool) ($parsed['approved'] ?? false),
                'issues' => array_values(array_filter(array_map(
                    fn ($issue) => trim((string) $issue),
                    (array) ($parsed['issues'] ?? []),
                ))),
            ];
        } catch (\Throwable) {
            return ['approved' => $lint === [], 'issues' => $lint];
        }
    }

    /**
     * Persist the generated copy onto the category. FAQs are only created
     * when the category has none — never silently overwrite curated ones.
     * A degenerate result (empty/truncated content) is REFUSED so a bad AI
     * run can never blank out existing category content.
     *
     * @return array{faqs_written: bool}
     */
    public static function apply(Category $category, array $output): array
    {
        $content = HtmlSanitizer::clean((string) ($output['content_html'] ?? ''));

        if (mb_strlen(strip_tags($content)) < 100) {
            throw new \RuntimeException('The AI returned no usable category content — nothing was changed. Try again (or a different model).');
        }

        $category->update([
            'content_block' => $content,
            'description' => mb_substr(trim(strip_tags((string) ($output['description'] ?? ''))), 0, 500),
        ]);

        $secondary = collect((array) ($output['secondary_keywords'] ?? []))
            ->map(fn ($kw) => trim((string) $kw))
            ->filter(fn ($kw) => $kw !== '' && mb_strlen($kw) <= 60)
            ->unique()->take(5)->values()->all();

        $category->seoMeta()->updateOrCreate([], [
            'title' => mb_substr((string) ($output['meta_title'] ?? $category->name), 0, 60),
            'description' => mb_substr((string) ($output['meta_description'] ?? ''), 0, 164),
            'focus_keyword' => trim((string) ($output['focus_keyword'] ?? '')),
            'secondary_keywords' => $secondary,
            'schema_enabled' => true,
        ]);

        $faqsWritten = false;

        if (! $category->faqs()->exists()) {
            foreach (array_slice((array) ($output['faqs'] ?? []), 0, 7) as $index => $faq) {
                $question = trim((string) ($faq['question'] ?? ''));
                $answer = trim((string) ($faq['answer'] ?? ''));

                if ($question !== '' && $answer !== '') {
                    $category->faqs()->create([
                        'question' => $question,
                        'answer' => $answer,
                        'sort_order' => $index,
                        'is_active' => true,
                    ]);
                    $faqsWritten = true;
                }
            }
        }

        return ['faqs_written' => $faqsWritten];
    }

    /** @return list<string> */
    protected function lint(Category $category, array $output): array
    {
        // Map to the lint contract the product pipeline uses. Keywords are
        // the AI's own research; the H1 is the category name.
        return ContentReviewer::lint(
            [
                'description_html' => (string) ($output['content_html'] ?? ''),
                'meta_title' => (string) ($output['meta_title'] ?? ''),
                'meta_description' => (string) ($output['meta_description'] ?? ''),
                'faqs' => (array) ($output['faqs'] ?? []),
            ],
            allowedUrls: self::catalogUrls($category),
            selfUrl: $category->url(),
            keywords: array_values(array_filter([trim((string) ($output['focus_keyword'] ?? ''))])),
            pageTitle: $category->name,
        );
    }

    protected function completeJson(string $system, string $user): array
    {
        // Same generous output budget as the product writer (16000 default,
        // setting-tunable). The first live run proved 8000 truncates: the
        // model returned EXACTLY 8000 tokens of half-finished JSON, the
        // parse failed, and the admin got nothing.
        $maxTokens = ProductWriter::maxOutputTokens();

        try {
            return LlmClient::parseJson($this->llm->complete($system, $user, maxTokens: $maxTokens, cacheStatic: true));
        } catch (\RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'valid JSON')) {
                throw $e;
            }

            return LlmClient::parseJson($this->llm->complete(
                $system,
                $user."\n\nCRITICAL: your previous reply was not parseable JSON. Return ONLY one valid JSON object, compact, no commentary.",
                maxTokens: $maxTokens,
                cacheStatic: true,
            ));
        }
    }

    /** Static rulebook — byte-identical across categories (prompt-cacheable). */
    public static function systemFor(): string
    {
        $sections = [ProductWriter::DEFAULT_SYSTEM];

        $store = trim((string) setting('ai.default_system_prompt', ''));
        if ($store !== '') {
            $sections[] = "STORE BRIEF:\n".$store;
        }

        $sections[] = self::CATEGORY_RULES;
        $sections[] = ProductWriter::SEARCH_ENGINE_RULES;

        if (($vocabulary = ProductWriter::attributeVocabulary()) !== '') {
            $sections[] = "ATTRIBUTE VOCABULARY (the store's canonical facet values — use EXACTLY these terms for flavor/strength/cooling/origin/compatibility facts):\n".$vocabulary;
        }

        $sections[] = self::OUTPUT_CONTRACT;

        return implode("\n\n", $sections);
    }

    public static function userPromptFor(Category $category, string $notes = ''): string
    {
        $trail = collect($category->breadcrumbTrail())->pluck('name')->implode(' > ');
        $children = $category->children()->where('is_active', true)->pluck('name');

        $products = $category->products()
            ->where('products.status', 'published')
            ->orderByDesc('products.is_featured')
            ->limit(30)
            ->get(['products.id', 'products.name', 'products.short_description', 'products.price']);

        $productLines = $products->map(function ($product) {
            $desc = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $product->short_description))), 160);

            return '- '.$product->name.' ('.price_format((float) $product->price).')'.($desc !== '' ? ' — '.$desc : '');
        })->implode("\n");

        $catalog = collect(self::catalogUrls($category, withNames: true))
            ->map(fn ($entry) => '- '.$entry['name'].' — '.$entry['url'])
            ->implode("\n");

        return "CATEGORY: {$category->name}\n"
            ."PLACE IN STORE: {$trail}\n"
            .($children->isNotEmpty() ? 'SUB-CATEGORIES: '.$children->implode(', ')."\n" : '')
            ."\nPRODUCTS IN THIS CATEGORY (the ONLY products you may reference — names, live prices, short descriptions):\n"
            .($productLines !== '' ? $productLines : '(no published products yet — write the category positioning without naming specific products)')
            ."\n\nCATALOG (pages you may link, copied exactly):\n".$catalog
            .($notes !== '' ? "\n\nOWNER NOTES FOR THIS CATEGORY:\n".trim($notes) : '')
            ."\n\nWrite the category page copy now. Return only the JSON.";
    }

    /**
     * Linkable URLs for lint + the prompt catalog: this category's products,
     * child categories, and the parent category.
     *
     * @return ($withNames is true ? array<int, array{name: string, url: string}> : list<string>)
     */
    public static function catalogUrls(Category $category, bool $withNames = false): array
    {
        $entries = [];

        foreach ($category->products()->where('products.status', 'published')->limit(30)->get(['products.id', 'products.name', 'products.slug']) as $product) {
            $entries[] = ['name' => $product->name, 'url' => $product->url()];
        }

        foreach ($category->children()->where('is_active', true)->get(['id', 'name', 'slug']) as $child) {
            $entries[] = ['name' => $child->name.' (sub-category)', 'url' => $child->url()];
        }

        if ($category->parent_id && $category->parent) {
            $entries[] = ['name' => $category->parent->name.' (parent category)', 'url' => $category->parent->url()];
        }

        return $withNames ? $entries : array_column($entries, 'url');
    }

    public const CATEGORY_RULES = <<<'RULES'
CATEGORY PAGE RULES (mandatory):

GROUNDING: every product fact must come from the PRODUCTS list you are given. Never invent a product, flavor, price, or availability. If the list is small, write depth about the category itself, not fake breadth.

MARKET ANALYSIS: before writing, consider how the top 10 competing category pages for this product type in this market (UAE ecommerce) present it: what they cover, what buyer questions they leave unanswered, and the boilerplate they all share. Your copy must answer what they skip and read nothing like their template. NEVER name a competitor or invent competitor claims.

KEYWORD RESEARCH: derive the category-level search terms buyers actually type (purchase intent, this market): ONE primary keyword (e.g. "terea kazakhstan uae") and 3-5 secondary variations (need-based phrasings, attribute-led variants). Place the primary naturally in the first 100 words, one H2, meta_title and meta_description; use secondaries once each where natural. No stuffing — after those placements use synonyms and related entities.

STRUCTURE (content_html, 350-650 words):
- Open with 2-3 sentences a buyer could quote: what this category is, who it suits, and the strongest concrete reason to buy it here (delivery promise, authenticity, range).
- 3-5 H2/H3 sections passing the skim test (headings alone must teach the category's story). BANNED: generic labels ("Overview", "Why Choose Us", "Our Products") and empty abstractions.
- Include ONE compact comparison table of 3-6 real products from the list (name, key facet, price) when 3+ products exist — buyers scan tables, and they double as AI-answer source material.
- Practical buying guidance: how to choose between the products in THIS category (facet-grounded: strength, cooling, flavor family), who should pick what.
- CONCRETENESS: every section carries at least one verifiable specific (a product name, price, stick count, delivery time, facet value). Write like a shop assistant who sells these daily — first-hand, realistic, no abstract praise.
- E-E-A-T: honest guidance beats hype; note real limitations where relevant (e.g. device compatibility).

INTERNAL LINKS: weave 2-5 links from the CATALOG inside sentences (top products, sub-categories, parent) with natural anchors. Never a link dump, never invent or alter URLs.

STYLE: NO em dashes anywhere. NO AI-cliché words (delve, seamless, meticulously, elevate, unleash…). Short paragraphs. Plain semantic HTML only: h2, h3, p, ul, ol, li, table, thead, tbody, tr, th, td, strong, em, a — no classes, no styles, no h1 (the page renders the category name as H1).
RULES;

    /** Static reviewer rulebook (cross-check seat) — prompt-cacheable. */
    public const REVIEWER_RULES = <<<'RULES'
You are a strict, independent reviewer of ecommerce CATEGORY page copy for a UAE store. You did not write this draft. Judge it against:
1. GROUNDING: no invented products, prices, flavors or claims — only what the draft's own product references support.
2. E-E-A-T: first-hand, specific, honest guidance; reject abstract praise, thin filler paragraphs, and template boilerplate any store could publish.
3. SEO: primary keyword placed naturally (first 100 words, one heading, meta fields); meta_title ≤60 chars and NOT the category name verbatim; meta_description 150-164 chars, click-worthy; headings pass the skim test (each teaches something concrete).
4. STYLE: no em dashes, no AI-cliché words (delve, seamless, meticulously, elevate…), short paragraphs, natural link anchors.
Be pragmatic: flag REAL problems a buyer or Google would notice, not stylistic taste. If the copy is publishable, approve it.
OUTPUT: return ONLY JSON: {"approved": true|false, "issues": ["<specific, actionable fix>", …]} — issues empty when approved.
RULES;

    public const OUTPUT_CONTRACT = <<<'JSON'
OUTPUT: return ONLY a JSON object, no prose, keys:
content_html, description, meta_title, meta_description, focus_keyword, secondary_keywords, faqs.
- content_html: the category page copy per CATEGORY PAGE RULES (350-650 words).
- description: 1-2 plain-text sentences summarising the category (used as the short listing description).
- meta_title: <=60 chars, used VERBATIM as the title tag, must NOT equal the category name (the H1) — intent word + category + concrete differentiator (e.g. "Buy TEREA Kazakhstan in UAE | 1-Hour Dubai Delivery").
- meta_description: 150-164 chars, click-worthy, primary keyword near the front.
- focus_keyword: the researched primary keyword. secondary_keywords: array of 3-5.
- faqs: array of {question, answer} (5-7) answering real category-level buyer doubts: which product to start with, compatibility, strength differences, delivery, authenticity, price range.
JSON;
}
