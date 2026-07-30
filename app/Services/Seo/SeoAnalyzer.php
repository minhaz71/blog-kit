<?php

namespace App\Services\Seo;

use App\Models\SeoMeta;
use Illuminate\Support\Str;

/**
 * Rank Math-style on-page analysis. Scores 0–100 across weighted checks
 * and stores per-check results on the SeoMeta record.
 */
class SeoAnalyzer
{
    /**
     * @param  string  $title  the meta/SEO title being evaluated
     * @param  string  $description  the meta description
     * @param  string  $slug  URL slug
     * @param  string  $content  the page's HTML body content
     * @param  string  $h1  the page's H1 text
     * @return array{score:int, checks:array<int, array{key:string, passed:bool, weight:int, message:string}>}
     */
    public function analyze(
        ?string $focusKeyword,
        string $title,
        string $description,
        string $slug,
        string $content,
        string $h1 = '',
        bool $hasSchema = true,
        bool $hasCanonical = true,
    ): array {
        $checks = [];
        $keyword = Str::lower(trim((string) $focusKeyword));
        $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        $wordCount = str_word_count($plainContent);

        $contains = fn (string $haystack) => $keyword !== '' && str_contains(Str::lower($haystack), $keyword);

        // ── Focus keyword checks ───────────────────────────────────
        $checks[] = $this->check('keyword_set', $keyword !== '', 5, 'Set a focus keyword.');
        $checks[] = $this->check('keyword_in_title', $contains($title), 10, 'Use the focus keyword in the SEO title.');
        $checks[] = $this->check('keyword_in_description', $contains($description), 8, 'Use the focus keyword in the meta description.');
        $checks[] = $this->check('keyword_in_url', $keyword !== '' && str_contains(Str::lower($slug), Str::slug($keyword)), 6, 'Use the focus keyword in the URL slug.');
        $checks[] = $this->check('keyword_in_h1', $contains($h1), 8, 'Use the focus keyword in the H1 heading.');

        $firstParagraph = Str::of($plainContent)->limit(600, '')->toString();
        $checks[] = $this->check('keyword_in_first_paragraph', $contains($firstParagraph), 6, 'Use the focus keyword near the beginning of the content.');

        // Keyword density between 0.5% and 2.5%
        $densityOk = false;
        if ($keyword !== '' && $wordCount > 0) {
            $occurrences = substr_count(Str::lower($plainContent), $keyword);
            $density = ($occurrences * max(1, str_word_count($keyword)) / $wordCount) * 100;
            $densityOk = $occurrences > 0 && $density <= 2.5;
        }
        $checks[] = $this->check('keyword_density', $densityOk, 5, 'Keep focus keyword density between 0.5% and 2.5%.');

        // ── Meta length checks ─────────────────────────────────────
        $titleLen = mb_strlen($title);
        $checks[] = $this->check('title_length', $titleLen >= 30 && $titleLen <= 60, 6, 'Keep the SEO title between 30 and 60 characters.');

        $descLen = mb_strlen($description);
        $checks[] = $this->check('description_length', $descLen >= 100 && $descLen <= 164, 6, 'Keep the meta description between 150 and 164 characters (100+ acceptable).');

        // ── Content checks ─────────────────────────────────────────
        $checks[] = $this->check('content_length', $wordCount >= 300, 8, 'Write at least 300 words of content.');

        $imgCount = preg_match_all('/<img[^>]+>/i', $content, $imgs);
        $missingAlt = 0;
        foreach ($imgs[0] ?? [] as $img) {
            if (! preg_match('/alt=["\'][^"\']+["\']/', $img)) {
                $missingAlt++;
            }
        }
        $checks[] = $this->check('image_alt', $imgCount === 0 || $missingAlt === 0, 5, 'Add alt text to all images.');

        $internalLinks = preg_match_all('/<a[^>]+href=["\']'.preg_quote(url('/'), '/').'|<a[^>]+href=["\']\//i', $content);
        $checks[] = $this->check('internal_links', $internalLinks > 0, 5, 'Add at least one internal link.');

        $externalLinks = preg_match_all('/<a[^>]+href=["\']https?:\/\/(?!'.preg_quote(request()?->getHost() ?? 'localhost', '/').')/i', $content);
        $checks[] = $this->check('external_links', $externalLinks > 0, 3, 'Link to at least one authoritative external source.');

        // ── Readability ────────────────────────────────────────────
        $paragraphs = preg_split('/<\/p>|\n\n/', $content) ?: [];
        $longParagraphs = collect($paragraphs)
            ->map(fn ($p) => str_word_count(strip_tags($p)))
            ->filter(fn ($words) => $words > 150)
            ->count();
        $checks[] = $this->check('short_paragraphs', $longParagraphs === 0, 4, 'Break up paragraphs longer than 150 words.');

        $hasSubheadings = (bool) preg_match('/<h[23][^>]*>/i', $content);
        $checks[] = $this->check('heading_structure', $wordCount < 300 || $hasSubheadings, 4, 'Use H2/H3 subheadings to structure long content.');

        // ── Technical ──────────────────────────────────────────────
        $checks[] = $this->check('schema_available', $hasSchema, 5, 'Enable schema markup for this page.');
        $checks[] = $this->check('canonical_set', $hasCanonical, 3, 'Set a canonical URL.');

        $slugWords = count(array_filter(explode('-', $slug)));
        $checks[] = $this->check('slug_quality', $slugWords > 0 && $slugWords <= 8 && ! preg_match('/[^a-z0-9-]/', $slug), 3, 'Keep the slug short, lowercase, and hyphenated.');

        $totalWeight = array_sum(array_column($checks, 'weight'));
        $earned = array_sum(array_map(fn ($c) => $c['passed'] ? $c['weight'] : 0, $checks));
        $score = $totalWeight > 0 ? (int) round($earned / $totalWeight * 100) : 0;

        return ['score' => $score, 'checks' => $checks];
    }

    /**
     * Analyze a model that uses HasSeoMeta and persist the result. Creates
     * the SeoMeta row if missing — use only from CLI/backfill, never inside
     * a Filament create flow (the SeoForm relationship owns creation there;
     * pre-creating collides on the unique key).
     */
    public function analyzeAndStore($model, string $content = '', string $h1 = ''): SeoMeta
    {
        return $this->writeAnalysis($model->getOrCreateSeoMeta(), $model, $content, $h1);
    }

    /**
     * Re-run analysis on an EXISTING SeoMeta row only — never creates one.
     * Safe to call from a parent model's saved-observer during a Filament
     * create (before the SeoForm relationship row exists, this is a no-op;
     * the SeoMeta saved-observer computes the score once that row lands).
     */
    public function analyzeExisting($model, string $content = '', string $h1 = ''): ?SeoMeta
    {
        $meta = $model->seoMeta;

        return $meta ? $this->writeAnalysis($meta, $model, $content, $h1) : null;
    }

    /** Compute analysis for a SeoMeta from its parent and store it quietly. */
    public function analyzeMeta(SeoMeta $meta): void
    {
        $model = $meta->metable;

        if ($model) {
            $this->writeAnalysis($meta, $model);
        }
    }

    /** Shared: score a meta against its parent and persist quietly (no event re-fire). */
    protected function writeAnalysis(SeoMeta $meta, $model, string $content = '', string $h1 = ''): SeoMeta
    {
        $result = $this->analyze(
            focusKeyword: $meta->focus_keyword,
            title: $meta->title ?: $model->seoTitle(),
            description: (string) ($meta->description ?: $model->seoDescription()),
            slug: (string) $model->slug,
            content: $content ?: (string) ($model->description ?? $model->content ?? $model->content_block ?? ''),
            h1: $h1 ?: (string) ($model->name ?? $model->title ?? ''),
            hasSchema: (bool) $meta->schema_enabled,
            hasCanonical: true, // canonical is always emitted (self-canonical by default)
        );

        // updateQuietly: bypasses model events so the SeoMeta saved-observer
        // that may have called us does not recurse.
        $meta->updateQuietly([
            'seo_score' => $result['score'],
            'seo_analysis' => $result['checks'],
        ]);

        return $meta;
    }

    /** Duplicate title/description detection across all SEO meta. */
    public function findDuplicates(): array
    {
        $all = SeoMeta::whereNotNull('title')->get(['id', 'metable_type', 'metable_id', 'title', 'description']);

        return [
            'titles' => $all->groupBy(fn ($m) => Str::lower(trim($m->title)))->filter(fn ($g) => $g->count() > 1),
            'descriptions' => $all->filter(fn ($m) => filled($m->description))
                ->groupBy(fn ($m) => Str::lower(trim($m->description)))
                ->filter(fn ($g) => $g->count() > 1),
        ];
    }

    protected function check(string $key, bool $passed, int $weight, string $message): array
    {
        return ['key' => $key, 'passed' => $passed, 'weight' => $weight, 'message' => $message];
    }
}
