<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\InternalLink;
use App\Models\LinkSuggestion;
use App\Models\LinkTarget;
use App\Models\Post;
use App\Models\Product;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 2 of the link agent: scan content for mention opportunities and
 * store scored suggestions. SUGGEST-ONLY — nothing is ever applied here;
 * the admin approves each link in the dashboard or on the edit page.
 *
 * Two matching passes per text node:
 *  1. consecutive phrases (word-boundary, fillers tolerated between words)
 *  2. token-set windows (order-independent → catches "Kazakhstan Amber",
 *     "IQOS Amber", "amber from Kazakhstan")
 *
 * Hard filters: never inside existing links/headings (HtmlLinkDom), never
 * self, never a target already linked from this source (internal_links),
 * never noindex/unpublished targets, never in a paragraph that already
 * contains a link, max 5 pending suggestions per source.
 */
class LinkSuggestionEngine
{
    public const MAX_PER_SOURCE = 5;

    public const MIN_SCORE = 30;

    /**
     * True while LinkApplier is saving an applied link. The save fires the
     * content observers, whose re-scan DELETES and regenerates this source's
     * pending suggestions — which is why applying 1 of 5 suggestions made
     * the other 4 vanish (new ids, same-block drops). Suspended, the pending
     * rows survive an apply untouched; the real-link index (InternalLink
     * scanner) still runs so the duplicate guard stays accurate.
     */
    public static bool $suspendScans = false;

    protected array $phrases = [];      // [[phrase, kind, tokens, targets: [[type,id,weight,ambiguous]]], …]

    protected array $sets = [];         // sortedTokens => targets

    protected array $context = [];      // "Type#id" => [token => true]

    protected array $inbound = [];      // "Type#id" => count

    protected array $noindex = [];      // "Type#id" => true

    protected array $categories = [];   // "Type#id" => [category ids]

    protected array $titleLength = [];  // "Type#id" => token count (base-variant resolution)

    protected array $customCaps = [];   // "CustomLinkTarget#id" => site-wide max links

    protected array $cluster = [];      // "Post#id" => ['cid'=>?int,'role'=>?string,'stage'=>?string,'pillar'=>?int]

    protected bool $loaded = false;

    /** @return array{sources: int, suggestions: int} */
    public function scanAll(): array
    {
        $this->loadIndexes();
        $sources = 0;
        $suggestions = 0;

        Product::query()->where('status', 'published')->select(['id', 'name', 'slug', 'description', 'short_description'])
            ->chunkById(100, function ($products) use (&$sources, &$suggestions) {
                foreach ($products as $product) {
                    $sources++;
                    $suggestions += $this->scanSource($product);
                }
            });

        Post::query()->published()->select(['id', 'title', 'slug', 'content', 'post_category_id'])
            ->chunkById(100, function ($posts) use (&$sources, &$suggestions) {
                foreach ($posts as $post) {
                    $sources++;
                    $suggestions += $this->scanSource($post);
                }
            });

        Category::query()->where('is_active', true)->select(['id', 'name', 'slug', 'content_block'])
            ->chunkById(100, function ($categories) use (&$sources, &$suggestions) {
                foreach ($categories as $category) {
                    $sources++;
                    $suggestions += $this->scanSource($category);
                }
            });

        return ['sources' => $sources, 'suggestions' => $suggestions];
    }

    /** Re-scan ONE source; pending suggestions for it are regenerated. */
    public function scanSource(Model $source): int
    {
        if (self::$suspendScans) {
            return 0;
        }

        $this->loadIndexes();

        $fields = match (true) {
            $source instanceof Product => ['description', 'short_description'],
            $source instanceof Category => ['content_block'],
            default => ['content'],
        };
        $sourceKey = $source::class.'#'.$source->getKey();

        // Regenerate: pending rows are replaced; applied/dismissed persist.
        LinkSuggestion::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->where('status', 'pending')
            ->delete();

        $blockedPairs = $this->blockedTargetPairs($source);
        $cappedTargets = $this->cappedCustomTargets();
        $candidates = [];

        foreach ($fields as $field) {
            $html = (string) $source->{$field};

            if (trim($html) === '') {
                continue;
            }

            foreach ($this->matchesInHtml($html) as $match) {
                $targetKey = $match['target_type'].'#'.$match['target_id'];

                if ($targetKey === $sourceKey
                    || isset($blockedPairs[$targetKey])
                    || isset($this->noindex[$targetKey])
                    || isset($cappedTargets[$targetKey])) {
                    continue;
                }

                $score = $this->score($source, $match);

                if ($score < self::MIN_SCORE) {
                    continue;
                }

                // One suggestion per target per source — keep the best.
                if (($candidates[$targetKey]['score'] ?? -1) >= $score) {
                    continue;
                }

                $candidates[$targetKey] = $match + ['score' => $score, 'source_field' => $field];
            }
        }

        $kept = collect($candidates)->sortByDesc('score')->take(self::MAX_PER_SOURCE);
        $created = 0;

        foreach ($kept as $match) {
            $fingerprint = LinkSuggestion::fingerprintFor(
                $source::class, $source->getKey(), $match['source_field'],
                $match['target_type'], $match['target_id'], $match['anchor'], $match['occurrence'],
            );

            if (LinkSuggestion::where('fingerprint', $fingerprint)->exists()) {
                continue; // previously dismissed/applied — never resurface
            }

            LinkSuggestion::create([
                'source_type' => $source::class,
                'source_id' => $source->getKey(),
                'source_field' => $match['source_field'],
                'target_type' => $match['target_type'],
                'target_id' => $match['target_id'],
                'anchor' => $match['anchor'],
                'occurrence' => $match['occurrence'],
                'sentence' => $match['sentence'],
                'score' => $match['score'],
                'status' => 'pending',
                'fingerprint' => $fingerprint,
            ]);
            $created++;
        }

        return $created;
    }

    // ── Matching ────────────────────────────────────────────────────

    /** @return array<int, array> candidate matches with anchors + occurrences */
    protected function matchesInHtml(string $html): array
    {
        $doc = HtmlLinkDom::load($html);
        $nodes = HtmlLinkDom::eligibleTextNodes($doc);
        $matches = [];

        foreach ($nodes as $index => $node) {
            // Skip paragraphs that already contain a link — natural spacing.
            if ($this->paragraphHasLink($node)) {
                continue;
            }

            foreach ($this->spansInText($node->nodeValue) as $span) {
                $matches[] = [
                    'target_type' => $span['target']['type'],
                    'target_id' => $span['target']['id'],
                    'targets' => $span['targets'],
                    'anchor' => $span['anchor'],
                    'node_index' => $index,
                    'start' => $span['start'],
                    'sentence' => $this->sentenceAround($node->nodeValue, $span['start'], $span['end']),
                    'kind' => $span['kind'],
                    'weight' => $span['weight'],
                    'ambiguous' => $span['ambiguous'],
                    'paragraph' => $this->paragraphText($node),
                ];
            }
        }

        // Occurrence = position of this exact anchor text among ALL its
        // case-insensitive appearances in eligible nodes — this is the same
        // arithmetic the applier uses, so apply hits the right words.
        foreach ($matches as &$match) {
            $occurrence = 1;

            foreach ($nodes as $index => $node) {
                if ($index > $match['node_index']) {
                    break;
                }

                $limit = $index === $match['node_index'] ? $match['start'] : PHP_INT_MAX;
                $occurrence += $this->occurrencesBefore($node->nodeValue, $match['anchor'], $limit);
            }

            $match['occurrence'] = $occurrence;
        }

        return $matches;
    }

    /** Non-overlapping matched spans in one text string, best-first. */
    protected function spansInText(string $text): array
    {
        $lower = mb_strtolower($text);
        $raw = [];

        // Pass 1: consecutive phrases (up to 2 filler words tolerated
        // between tokens: "amber from kazakhstan" matches "amber kazakhstan").
        $separator = '(?:[\s\p{P}]+(?:'.implode('|', LinkDictionary::FILLERS).')\b){0,2}[\s\p{P}]+';

        foreach ($this->phrases as $entry) {
            if (! str_contains($lower, $entry['tokens'][0])) {
                continue;
            }

            $pattern = '/\b'.implode($separator, array_map(fn ($t) => preg_quote($t, '/'), $entry['tokens'])).'\b/iu';

            if (! @preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($m[0] as [$matched, $offset]) {
                $raw[] = $this->spanFor($entry, $matched, $offset);
            }
        }

        // Pass 2: token-set windows (reordered / skip-word mentions).
        foreach ($this->setWindows($text) as $window) {
            $entry = $this->sets[$window['key']] ?? null;

            if ($entry) {
                $raw[] = $this->spanFor($entry, $window['anchor'], $window['start']);
            }
        }

        // Overlap resolution: highest weight, then longest, wins its span.
        usort($raw, fn ($a, $b) => [$b['weight'], $b['end'] - $b['start']] <=> [$a['weight'], $a['end'] - $a['start']]);

        $spans = [];

        foreach ($raw as $candidate) {
            foreach ($spans as $kept) {
                if ($candidate['start'] < $kept['end'] + 200 && $kept['start'] < $candidate['end'] + 200) {
                    continue 2; // overlaps or violates the 200-char spacing
                }
            }

            $spans[] = $candidate;
        }

        return $spans;
    }

    protected function spanFor(array $entry, string $matched, int $offset): array
    {
        // Ambiguous phrases resolve later via context; carry all targets.
        return [
            'start' => $offset,
            'end' => $offset + strlen($matched),
            'anchor' => $matched,
            'kind' => $entry['kind'],
            'weight' => $entry['weight'],
            'ambiguous' => count($entry['targets']) > 1,
            'target' => $entry['targets'][0],
            'targets' => $entry['targets'],
        ];
    }

    /** Sliding 2–4 content-token windows with filler tolerance. */
    protected function setWindows(string $text): array
    {
        if (! preg_match_all('/[a-z0-9]{2,}/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tokens = [];

        foreach ($m[0] as [$word, $offset]) {
            $lower = mb_strtolower($word);

            if (! in_array($lower, LinkDictionary::FILLERS, true)) {
                $tokens[] = ['t' => $lower, 'start' => $offset, 'end' => $offset + strlen($word)];
            }
        }

        $windows = [];
        $n = count($tokens);

        for ($i = 0; $i < $n; $i++) {
            for ($size = 2; $size <= 4 && $i + $size <= $n; $size++) {
                $slice = array_slice($tokens, $i, $size);
                $span = end($slice)['end'] - $slice[0]['start'];

                if ($span > 60) {
                    break; // window stretched over too much text
                }

                $set = array_column($slice, 't');
                sort($set);

                $windows[] = [
                    'key' => implode(' ', $set),
                    'start' => $slice[0]['start'],
                    'anchor' => substr($text, $slice[0]['start'], $span),
                ];
            }
        }

        return $windows;
    }

    // ── Scoring ─────────────────────────────────────────────────────

    protected function score(Model $source, array &$match): int
    {
        // Ambiguous phrase: the paragraph votes for the right variant.
        if ($match['ambiguous']) {
            $resolved = $this->resolveAmbiguous($match);

            if ($resolved === null) {
                return 0; // no deciding context → not suggestible
            }

            $match['target_type'] = $resolved['type'];
            $match['target_id'] = $resolved['id'];
        }

        $targetKey = $match['target_type'].'#'.$match['target_id'];

        $points = (int) round($match['weight'] * 0.4);

        // Paragraph context vote (+6 per shared significant term, cap 24).
        $overlap = $this->contextOverlap($match['paragraph'], $match['anchor'], $targetKey);
        $points += min(24, $overlap * 6);

        // Need boost from the live internal-links index.
        $inbound = $this->inbound[$targetKey] ?? 0;
        $points += $inbound === 0 ? 20 : ($inbound <= 2 ? 10 : 0);

        // Structure bonuses.
        $sourceKey = $source::class.'#'.$source->getKey();

        if ($source instanceof Post && $match['target_type'] === Product::class) {
            $points += 10;
        } elseif ($source instanceof Post && $match['target_type'] === Category::class) {
            // Article → category: funnels readers to a whole range —
            // comparison/guide articles should land on category hubs too.
            $points += 8;
        } elseif ($source instanceof Category && $match['target_type'] === Product::class) {
            // Category prose → product: the hub linking down to its items.
            $points += 8;
        } elseif ($source instanceof Product && $match['target_type'] === Post::class) {
            $points += 5;
        } elseif (array_intersect($this->categories[$sourceKey] ?? [], $this->categories[$targetKey] ?? []) !== []) {
            $points += 5;
        }

        // Cluster + funnel structural bonuses (article → article within the
        // content map): the topical-authority signal search engines reward.
        if ($source instanceof Post && $match['target_type'] === Post::class) {
            $points += $this->clusterBonus($sourceKey, $targetKey);
        }

        // Conservative caps for the risky kinds.
        if ($match['ambiguous']) {
            $points = min($points, 55);
        }

        if ($match['kind'] === 'single') {
            $points = min($points, 45);
        }

        return min(100, $points);
    }

    /** Pick the variant whose context tokens appear near the mention. */
    protected function resolveAmbiguous(array $match): ?array
    {
        $best = null;
        $bestVotes = 0;

        foreach ($match['targets'] as $target) {
            $votes = $this->contextOverlap($match['paragraph'], $match['anchor'], $target['type'].'#'.$target['id']);

            if ($votes > $bestVotes) {
                $bestVotes = $votes;
                $best = $target;
            }
        }

        if ($best !== null) {
            return $best;
        }

        // No context anywhere → the base variant (shortest title), review-only.
        $targets = $match['targets'];
        usort($targets, fn ($a, $b) => ($this->titleLength[$a['type'].'#'.$a['id']] ?? 99) <=> ($this->titleLength[$b['type'].'#'.$b['id']] ?? 99));

        return $targets[0] ?? null;
    }

    /**
     * Cluster + funnel structural score for an article → article link.
     *  - spoke → its pillar: the strongest hub-and-spoke signal (+25);
     *  - pillar → one of its spokes: the hub linking down (+18);
     *  - siblings in the same cluster interlink (+12);
     *  - funnel flow: forward (top→middle→bottom) rewarded, backward penalized.
     * Returns 0 when either post carries no cluster metadata.
     */
    protected function clusterBonus(string $sourceKey, string $targetKey): int
    {
        $s = $this->cluster[$sourceKey] ?? null;
        $t = $this->cluster[$targetKey] ?? null;

        if ($s === null || $t === null) {
            return 0;
        }

        $bonus = 0;

        if (($s['role'] ?? null) === 'spoke' && ($s['pillar'] ?? null) !== null && ($s['pillar'] ?? null) === ($t['id'] ?? null)) {
            $bonus += 25; // spoke links UP to its pillar
        } elseif (($t['role'] ?? null) === 'spoke' && ($t['pillar'] ?? null) !== null && ($t['pillar'] ?? null) === ($s['id'] ?? null)) {
            $bonus += 18; // pillar links DOWN to a spoke
        } elseif (($s['cid'] ?? null) !== null && ($s['cid'] ?? null) === ($t['cid'] ?? null)) {
            $bonus += 12; // siblings in the same cluster
        }

        // Funnel flow: nudge readers forward, discourage sending them back up.
        $order = ['top' => 1, 'middle' => 2, 'bottom' => 3];
        $si = $order[$s['stage'] ?? ''] ?? 0;
        $ti = $order[$t['stage'] ?? ''] ?? 0;
        if ($si !== 0 && $ti !== 0) {
            $bonus += $ti > $si ? 6 : ($ti < $si ? -8 : 0);
        }

        return $bonus;
    }

    /** Distinct significant terms shared by the paragraph and the target. */
    protected function contextOverlap(string $paragraph, string $anchor, string $targetKey): int
    {
        $anchorTokens = LinkDictionary::tokenize($anchor);
        $seen = [];

        foreach (LinkDictionary::tokenize($paragraph) as $token) {
            if (in_array($token, LinkDictionary::FILLERS, true)
                || in_array($token, $anchorTokens, true)
                || isset($seen[$token])) {
                continue;
            }

            if (isset($this->context[$targetKey][$token])) {
                $seen[$token] = true;
            }
        }

        return count($seen);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    protected function paragraphHasLink(\DOMText $node): bool
    {
        $block = $this->blockAncestor($node);

        return $block !== null && $block->getElementsByTagName('a')->length > 0;
    }

    protected function paragraphText(\DOMText $node): string
    {
        return $this->blockAncestor($node)?->textContent ?? $node->nodeValue;
    }

    protected function blockAncestor(\DOMText $node): ?\DOMElement
    {
        for ($el = $node->parentNode; $el !== null && strtolower($el->nodeName) !== 'body'; $el = $el->parentNode) {
            if ($el instanceof \DOMElement && in_array(strtolower($el->nodeName), ['p', 'li', 'td', 'blockquote', 'div'], true)) {
                return $el;
            }
        }

        return null;
    }

    /** Byte-offset based, matching preg offsets and the applier's search. */
    protected function occurrencesBefore(string $text, string $anchor, int $beforeOffset): int
    {
        $count = 0;
        $offset = 0;

        while (($pos = stripos($text, $anchor, $offset)) !== false && $pos < $beforeOffset) {
            $count++;
            $offset = $pos + strlen($anchor);
        }

        return $count;
    }

    protected function sentenceAround(string $text, int $start, int $end): string
    {
        $begin = 0;

        if (preg_match_all('/[.!?]\s/u', substr($text, 0, $start), $m, PREG_OFFSET_CAPTURE)) {
            $last = end($m[0]);
            $begin = $last[1] + strlen($last[0]);
        }

        $stop = strlen($text);

        if (preg_match('/[.!?](\s|$)/u', $text, $m, PREG_OFFSET_CAPTURE, $end)) {
            $stop = $m[0][1] + 1;
        }

        $sentence = trim(preg_replace('/\s+/u', ' ', substr($text, $begin, $stop - $begin)));

        return mb_strlen($sentence) > 300 ? mb_substr($sentence, 0, 297).'…' : $sentence;
    }

    /**
     * Custom targets that have hit their site-wide max_links cap (counting
     * both live links and still-pending suggestions) — no new suggestions.
     */
    protected function cappedCustomTargets(): array
    {
        if ($this->customCaps === []) {
            return [];
        }

        $capped = [];

        foreach ($this->customCaps as $key => $max) {
            [$type, $id] = explode('#', $key);

            $live = InternalLink::where('target_type', $type)->where('target_id', $id)->count();
            $pending = LinkSuggestion::where('target_type', $type)->where('target_id', $id)
                ->where('status', 'pending')->count();

            if ($live + $pending >= $max) {
                $capped[$key] = true;
            }
        }

        return $capped;
    }

    /** Target pairs this source must never get suggestions for. */
    protected function blockedTargetPairs(Model $source): array
    {
        return InternalLink::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->get(['target_type', 'target_id'])
            ->mapWithKeys(fn ($l) => [$l->target_type.'#'.$l->target_id => true])
            ->all();
    }

    // ── Index loading (once per engine instance) ────────────────────

    protected function loadIndexes(): void
    {
        if ($this->loaded) {
            return;
        }

        foreach (LinkTarget::query()->get() as $row) {
            $target = ['type' => $row->target_type, 'id' => $row->target_id, 'weight' => $row->weight];

            if ($row->kind === 'set') {
                $this->sets[$row->phrase]['kind'] = 'set';
                $this->sets[$row->phrase]['weight'] = max($this->sets[$row->phrase]['weight'] ?? 0, $row->weight);
                $this->sets[$row->phrase]['targets'][] = $target;
            } else {
                $key = $row->kind.'|'.$row->phrase;
                $this->phrases[$key]['kind'] = $row->kind;
                $this->phrases[$key]['tokens'] = explode(' ', $row->phrase);
                $this->phrases[$key]['weight'] = max($this->phrases[$key]['weight'] ?? 0, $row->weight);
                $this->phrases[$key]['targets'][] = $target;
            }
        }

        $this->phrases = array_values($this->phrases);

        // Context tokens, categories, inbound counts, noindex, title length.
        foreach (Product::query()->where('status', 'published')->with(['seoMeta', 'brand', 'categories:id,name', 'attributeValues:id,value'])->get(['id', 'name', 'slug', 'brand_id']) as $product) {
            $key = Product::class.'#'.$product->id;
            $tokens = LinkDictionary::tokenize(implode(' ', array_filter([
                $product->name,
                $product->brand?->name,
                $product->categories->pluck('name')->implode(' '),
                // Taxonomy facet values ("Menthol", "Strong") disambiguate
                // mentions the name alone can't — e.g. "the menthol option".
                $product->attributeValues->pluck('value')->implode(' '),
                $product->seoMeta?->focus_keyword,
                implode(' ', (array) ($product->seoMeta?->secondary_keywords ?? [])),
            ])));
            $this->context[$key] = array_fill_keys(array_diff($tokens, LinkDictionary::FILLERS), true);
            $this->categories[$key] = $product->categories->pluck('id')->all();
            $this->titleLength[$key] = count(LinkDictionary::tokenize($product->name));

            if ($product->seoMeta?->noindex) {
                $this->noindex[$key] = true;
            }
        }

        foreach (Post::query()->published()->with(['seoMeta', 'category:id,name'])->get(['id', 'title', 'slug', 'post_category_id', 'content_cluster_id', 'content_role', 'funnel_stage', 'pillar_post_id']) as $post) {
            $key = Post::class.'#'.$post->id;
            $tokens = LinkDictionary::tokenize(implode(' ', array_filter([
                $post->title,
                $post->category?->name,
                $post->seoMeta?->focus_keyword,
            ])));
            $this->context[$key] = array_fill_keys(array_diff($tokens, LinkDictionary::FILLERS), true);
            $this->titleLength[$key] = count(LinkDictionary::tokenize($post->title));

            // Cluster/funnel map — powers pillar↔spoke and funnel-flow scoring.
            $this->cluster[$key] = [
                'cid' => $post->content_cluster_id,
                'role' => $post->content_role,
                'stage' => $post->funnel_stage,
                'pillar' => $post->pillar_post_id,
                'id' => $post->id,
            ];

            if ($post->seoMeta?->noindex) {
                $this->noindex[$key] = true;
            }
        }

        foreach (Category::query()->where('is_active', true)->with('seoMeta')->get(['id', 'name', 'slug']) as $category) {
            $key = Category::class.'#'.$category->id;
            $tokens = LinkDictionary::tokenize(implode(' ', array_filter([
                $category->name,
                $category->seoMeta?->focus_keyword,
                implode(' ', (array) ($category->seoMeta?->secondary_keywords ?? [])),
            ])));
            $this->context[$key] = array_fill_keys(array_diff($tokens, LinkDictionary::FILLERS), true);
            $this->titleLength[$key] = count(LinkDictionary::tokenize($category->name));

            if ($category->seoMeta?->noindex) {
                $this->noindex[$key] = true;
            }
        }

        // Custom targets (homepage etc.): context tokens from every anchor
        // phrase, and their site-wide cap for the over-linking guard.
        foreach (\App\Models\CustomLinkTarget::query()->where('is_active', true)->get() as $target) {
            $key = \App\Models\CustomLinkTarget::class.'#'.$target->id;
            $tokens = LinkDictionary::tokenize(implode(' ', (array) $target->anchor_phrases));
            $this->context[$key] = array_fill_keys(array_diff($tokens, LinkDictionary::FILLERS), true);
            $this->titleLength[$key] = 99; // never the "base variant" tiebreak
            $this->customCaps[$key] = (int) $target->max_links;
        }

        foreach (InternalLink::query()->selectRaw('target_type, target_id, COUNT(*) c')->groupBy('target_type', 'target_id')->get() as $row) {
            $this->inbound[$row->target_type.'#'.$row->target_id] = (int) $row->c;
        }

        $this->loaded = true;
    }
}
