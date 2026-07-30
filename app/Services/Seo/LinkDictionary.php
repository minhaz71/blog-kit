<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\LinkTarget;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 of the link agent: the phrase dictionary.
 *
 * Every product/post is expanded into all the ways a writer could mention
 * it — consecutive n-grams of its title/SEO title/slug/keywords PLUS
 * order-independent token SETS (so "Kazakhstan Amber" and "IQOS Amber"
 * both resolve to "IQOS Terea Amber Kazakhstan").
 *
 * The document-frequency filter then lets the catalog define its own
 * stopwords: any phrase shared by more than MAX_SHARED targets is noise
 * ("iqos", "terea", "iqos terea") and is dropped; phrases shared by 2-3
 * targets are kept but flagged ambiguous (context must disambiguate,
 * lower score, review-only); single tokens survive only when globally
 * unique.
 */
class LinkDictionary
{
    public const FILLERS = ['the', 'a', 'an', 'of', 'from', 'in', 'for', 'with', 'and', 'or', 'to', 'on', 'at', 'by', 'your', 'our', 'is', 'are'];

    public const MAX_SHARED = 3;

    /**
     * Generic, niche-neutral words stripped from SEO titles before phrase
     * extraction. Deliberately excludes geo terms (a travel blog links
     * "Dubai") and ambiguous words like "best" (legit in "best practices").
     * Override per site via the `seo.link_title_noise` setting (comma- or
     * newline-separated) — see titleNoise().
     */
    protected const TITLE_NOISE = ['buy', 'shop', 'online', 'order', 'sale', 'discount'];

    /** Effective title-noise list: admin override if set, else the generic default. */
    protected function titleNoise(): array
    {
        $custom = setting('seo.link_title_noise');

        if (is_string($custom) && trim($custom) !== '') {
            $words = array_filter(array_map('trim', preg_split('/[,\n]+/', mb_strtolower($custom))));

            if ($words !== []) {
                return array_values($words);
            }
        }

        return self::TITLE_NOISE;
    }

    /** @return array{targets: int, phrases: int} */
    public function rebuild(): array
    {
        $candidates = []; // key "kind|phrase" => [targetKey => ['row' =>…, 'weight' => max]]

        $targets = $this->targets();

        foreach ($targets as [$type, $id, $sources]) {
            $rows = [];

            foreach ($sources as [$text, $baseWeight]) {
                foreach ($this->phrasesFrom($text, $baseWeight) as $key => $meta) {
                    if ($meta['weight'] > ($rows[$key]['weight'] ?? 0)) {
                        $rows[$key] = $meta;
                    }
                }
            }

            foreach ($rows as $key => $meta) {
                $candidates[$key][$type.'#'.$id] = [
                    'target_type' => $type,
                    'target_id' => $id,
                    'phrase' => $meta['phrase'],
                    'kind' => $meta['kind'],
                    'weight' => $meta['weight'],
                ];
            }
        }

        // Document-frequency filter across the whole catalog.
        $inserts = [];

        foreach ($candidates as $byTarget) {
            $shared = count($byTarget);

            if ($shared > self::MAX_SHARED) {
                continue; // generic — the catalog's own stopwords
            }

            foreach ($byTarget as $row) {
                if ($row['kind'] === 'single' && $shared > 1) {
                    continue; // single tokens must be globally unique
                }

                $row['is_ambiguous'] = $shared > 1;
                $inserts[] = $row;
            }
        }

        DB::transaction(function () use ($inserts) {
            LinkTarget::query()->delete();

            foreach (array_chunk($inserts, 500) as $chunk) {
                LinkTarget::insert($chunk);
            }
        });

        return ['targets' => count($targets), 'phrases' => count($inserts)];
    }

    /** @return array<int, array{0: string, 1: int, 2: array}> [type, id, sources] */
    protected function targets(): array
    {
        $out = [];

        $products = Product::query()
            ->where('status', 'published')
            ->with('seoMeta')
            ->get(['id', 'name', 'slug']);

        foreach ($products as $product) {
            $out[] = [Product::class, $product->id, $this->sourcesFor(
                $product->name,
                $product->seoMeta?->title,
                $product->slug,
                array_merge(
                    [$product->seoMeta?->focus_keyword],
                    (array) ($product->seoMeta?->secondary_keywords ?? []),
                ),
            )];
        }

        $posts = Post::query()->published()->with('seoMeta')->get(['id', 'title', 'slug', 'content']);

        foreach ($posts as $post) {
            $sources = $this->sourcesFor(
                $post->title,
                $post->seoMeta?->title,
                $post->slug,
                array_merge(
                    [$post->seoMeta?->focus_keyword],
                    (array) ($post->seoMeta?->secondary_keywords ?? []),
                ),
            );

            // Post H2/H3 headings are linkable phrases too (weight 60).
            if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', (string) $post->content, $m)) {
                foreach (array_slice($m[1], 0, 6) as $heading) {
                    $clean = trim(strip_tags($heading));

                    if ($clean !== '' && mb_strlen($clean) <= 80) {
                        $sources[] = [$clean, 60];
                    }
                }
            }

            $out[] = [Post::class, $post->id, $sources];
        }

        $categories = Category::query()->where('is_active', true)->with('seoMeta')->get(['id', 'name', 'slug']);

        foreach ($categories as $category) {
            $out[] = [Category::class, $category->id, $this->sourcesFor(
                $category->name,
                $category->seoMeta?->title,
                $category->slug,
                array_merge(
                    [$category->seoMeta?->focus_keyword],
                    (array) ($category->seoMeta?->secondary_keywords ?? []),
                ),
            )];
        }

        // Admin-defined targets (homepage, landing pages). Each anchor phrase
        // is a source string at the target's weight, so all variants are
        // linkable and the engine picks whichever appears naturally.
        $custom = \App\Models\CustomLinkTarget::query()->where('is_active', true)->get();

        foreach ($custom as $target) {
            $sources = [];

            foreach ((array) $target->anchor_phrases as $phrase) {
                if (trim((string) $phrase) !== '') {
                    $sources[] = [trim($phrase), $target->weight];
                }
            }

            if ($sources !== []) {
                $out[] = [\App\Models\CustomLinkTarget::class, $target->id, $sources];
            }
        }

        return $out;
    }

    /** @return array<int, array{0: string, 1: int}> [[text, weight], …] */
    protected function sourcesFor(?string $title, ?string $seoTitle, ?string $slug, array $keywords): array
    {
        $sources = [];

        if (filled($title)) {
            $sources[] = [$title, 100];
        }

        if (filled($seoTitle)) {
            $cleaned = trim(preg_replace(
                '/\b('.implode('|', array_map(fn ($w) => preg_quote($w, '/'), $this->titleNoise())).')\b/iu',
                ' ',
                preg_split('/[|–—]/u', $seoTitle)[0],
            ));

            if ($cleaned !== '') {
                $sources[] = [$cleaned, 85];
            }
        }

        if (filled($slug)) {
            $sources[] = [str_replace('-', ' ', $slug), 80];
        }

        foreach (array_filter(array_map(fn ($k) => trim((string) $k), $keywords)) as $i => $keyword) {
            $sources[] = [$keyword, $i === 0 ? 90 : 70];
        }

        return $sources;
    }

    /**
     * Expand one source string into candidate dictionary entries:
     * consecutive n-grams (kind=phrase), sorted token subsets (kind=set),
     * and the lone unique token case (kind=single).
     *
     * @return array<string, array{kind: string, weight: int}>
     */
    protected function phrasesFrom(string $text, int $baseWeight): array
    {
        $tokens = self::tokenize($text);
        $content = array_values(array_diff($tokens, self::FILLERS));
        $n = count($content);
        $out = [];

        if ($n === 0) {
            return [];
        }

        if ($n === 1) {
            // Single token: kept only if globally unique (DF filter decides).
            $out['single|'.$content[0]] = ['kind' => 'single', 'phrase' => $content[0], 'weight' => (int) round($baseWeight * 0.45)];

            return $out;
        }

        // Consecutive n-grams, longest first (2..n) — weight scales with length.
        for ($len = $n; $len >= 2; $len--) {
            for ($start = 0; $start + $len <= $n; $start++) {
                $phrase = implode(' ', array_slice($content, $start, $len));
                $key = 'phrase|'.$phrase;
                $weight = (int) round($baseWeight * (0.5 + 0.5 * $len / $n));

                $out[$key] = [
                    'kind' => 'phrase',
                    'phrase' => $phrase,
                    'weight' => max($weight, $out[$key]['weight'] ?? 0),
                ];
            }
        }

        // Token SETS (order-independent), sizes 2..4 — reordered/skip matches.
        $max = min(4, $n);

        foreach ($this->subsets($content, $max) as $subset) {
            sort($subset);
            $set = implode(' ', $subset);
            $key = 'set|'.$set;
            $weight = (int) round($baseWeight * 0.55);

            $out[$key] = [
                'kind' => 'set',
                'phrase' => $set,
                'weight' => max($weight, $out[$key]['weight'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * All token combinations of size 2..$max. Generates exactly C(n,k) for
     * each k directly, instead of enumerating all 2^n bitmasks and discarding
     * the wrong sizes — the old approach blew up on long H2/H3 headings
     * (n≈15+ → tens of thousands of masks) on every rebuild. Long headings are
     * capped at the first 16 meaningful tokens for set generation (ordered
     * n-grams still cover the full heading), so worst case stays bounded.
     */
    protected function subsets(array $tokens, int $max): array
    {
        $tokens = array_values($tokens);

        if (count($tokens) > 16) {
            $tokens = array_slice($tokens, 0, 16);
        }

        $n = count($tokens);
        $upper = min($max, $n);
        $out = [];

        for ($k = 2; $k <= $upper; $k++) {
            $this->combine($tokens, $k, 0, [], $out);
        }

        return $out;
    }

    /**
     * Append every size-$k combination of $tokens (indices ascending) to $out.
     *
     * @param  array<int, string>  $current
     * @param  array<int, array<int, string>>  $out
     */
    protected function combine(array $tokens, int $k, int $start, array $current, array &$out): void
    {
        if (count($current) === $k) {
            $out[] = $current;

            return;
        }

        $n = count($tokens);
        $need = $k - count($current);

        // Stop early once too few tokens remain to complete a size-$k set.
        for ($i = $start; $i <= $n - $need; $i++) {
            $next = $current;
            $next[] = $tokens[$i];
            $this->combine($tokens, $k, $i + 1, $next, $out);
        }
    }

    /** @return array<int, string> lowercase alphanumeric tokens */
    public static function tokenize(string $text): array
    {
        return array_values(array_filter(
            preg_split('/[^a-z0-9]+/', mb_strtolower($text)) ?: [],
            fn ($t) => $t !== '' && mb_strlen($t) >= 2,
        ));
    }
}
