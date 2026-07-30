<?php

namespace App\Services\Seo;

use App\Models\LinkSuggestion;
use RuntimeException;

/**
 * Applies an approved suggestion: finds the exact occurrence the engine
 * scored, wraps it in a root-relative <a>, and saves — the model save
 * fires the existing observers, so the internal-links index, sitemap and
 * IndexNow all update automatically. Undo unwraps the same anchor.
 *
 * If the content changed since the suggestion was made and the occurrence
 * no longer exists, the suggestion is marked stale instead of guessing.
 */
class LinkApplier
{
    public function apply(LinkSuggestion $suggestion): void
    {
        if ($suggestion->status !== 'pending') {
            throw new RuntimeException('Only pending suggestions can be applied.');
        }

        $source = $suggestion->source;
        $target = $suggestion->target;

        if (! $source || ! $target) {
            $suggestion->update(['status' => 'dismissed']);

            throw new RuntimeException('Source or target no longer exists — suggestion dismissed.');
        }

        // Duplicate guard at apply time: the page may have gained a link to
        // this target (any anchor text) since the suggestion was created —
        // one page should link one target once, so refuse and dismiss.
        $alreadyLinked = \App\Models\InternalLink::query()
            ->where('source_type', $source::class)->where('source_id', $source->getKey())
            ->where('target_type', $target::class)->where('target_id', $target->getKey())
            ->exists();

        if ($alreadyLinked) {
            $suggestion->update(['status' => 'dismissed']);

            throw new RuntimeException('This page already links to "'.($target->name ?? $target->title).'" (possibly with different anchor text) — duplicate skipped.');
        }

        $field = $suggestion->source_field;
        $doc = HtmlLinkDom::load((string) $source->{$field});

        $located = HtmlLinkDom::findOccurrence($doc, $suggestion->anchor, $suggestion->occurrence);

        if ($located === null) {
            $suggestion->update(['status' => 'dismissed']);

            throw new RuntimeException('The text changed since this was suggested — suggestion removed. Re-scan to get fresh ones.');
        }

        [$node, $offset] = $located;

        // One link per paragraph: an earlier apply may have put a link into
        // this same block since these suggestions were generated (pending
        // rows now SURVIVE an apply instead of being regenerated). Refuse
        // and dismiss just this one, with a reason the admin can read.
        for ($el = $node->parentNode; $el instanceof \DOMElement; $el = $el->parentNode) {
            if (in_array(strtolower($el->nodeName), ['p', 'li', 'td', 'blockquote'], true)) {
                if ($el->getElementsByTagName('a')->length > 0) {
                    $suggestion->update(['status' => 'dismissed']);

                    throw new RuntimeException('This paragraph already has a link (one link per paragraph) — suggestion dismissed; the others are untouched.');
                }
                break;
            }
        }

        // Byte-split around the matched anchor (offsets come from the same
        // byte arithmetic the engine used) and wrap it.
        $text = $node->nodeValue;
        $before = substr($text, 0, $offset);
        $anchorText = substr($text, $offset, strlen($suggestion->anchor));
        $after = substr($text, $offset + strlen($suggestion->anchor));

        $href = $this->rootRelative($target->url());

        $link = $doc->createElement('a');
        $link->setAttribute('href', $href);
        $link->appendChild($doc->createTextNode($anchorText));

        $parent = $node->parentNode;
        $parent->insertBefore($doc->createTextNode($before), $node);
        $parent->insertBefore($link, $node);
        $parent->insertBefore($doc->createTextNode($after), $node);
        $parent->removeChild($node);

        // Mark applied BEFORE saving the content (belt and braces with the
        // suspension below: if any other path still triggers a re-scan, this
        // row must already be out of the pending bucket).
        $suggestion->update(['status' => 'applied', 'applied_at' => now()]);

        // Suspend the suggestion re-scan for THIS save: the observers would
        // otherwise delete and regenerate every pending suggestion for the
        // source — the "apply 1 of 5, the other 4 disappear" bug. The
        // internal-links index scan still runs (separate service), so the
        // at-apply duplicate guard stays accurate for the next apply.
        LinkSuggestionEngine::$suspendScans = true;

        try {
            $source->update([$field => HtmlLinkDom::save($doc)]);
        } catch (\Throwable $e) {
            $suggestion->update(['status' => 'pending', 'applied_at' => null]);

            throw $e;
        } finally {
            LinkSuggestionEngine::$suspendScans = false;
        }
    }

    /** Unwrap the applied anchor; the suggestion returns to pending. */
    public function undo(LinkSuggestion $suggestion): void
    {
        if ($suggestion->status !== 'applied') {
            throw new RuntimeException('Only applied suggestions can be undone.');
        }

        $source = $suggestion->source;
        $target = $suggestion->target;

        if (! $source) {
            return;
        }

        $field = $suggestion->source_field;
        $doc = HtmlLinkDom::load((string) $source->{$field});
        $href = $target ? $this->rootRelative($target->url()) : null;

        foreach ($doc->getElementsByTagName('a') as $link) {
            $sameHref = $href === null || $link->getAttribute('href') === $href;
            $sameText = strcasecmp(trim($link->textContent), trim($suggestion->anchor)) === 0;

            if ($sameHref && $sameText) {
                $link->parentNode->replaceChild($doc->createTextNode($link->textContent), $link);
                $source->update([$field => HtmlLinkDom::save($doc)]);
                $suggestion->update(['status' => 'pending', 'applied_at' => null]);

                return;
            }
        }

        throw new RuntimeException('That link is no longer in the content (edited or removed manually).');
    }

    /**
     * Remove a specific internal link from a source's content without
     * visiting the page — used by the Internal Links report's Unlink action.
     * Unwraps the <a> (keeping its text), saves, and lets the observers
     * re-index. Returns true if a link was actually removed.
     */
    public function unlink(\Illuminate\Database\Eloquent\Model $source, string $targetUrl, ?string $anchor = null): bool
    {
        $href = $this->rootRelative($targetUrl);
        $removed = 0;

        foreach ($this->linkableFields($source) as $field) {
            $html = (string) $source->{$field};

            if ($html === '' || ! str_contains($html, 'href')) {
                continue;
            }

            $doc = HtmlLinkDom::load($html);
            $count = HtmlLinkDom::unwrap($doc, $href, $anchor)
                // Some content stores absolute hrefs — try that form too.
                + ($href !== $targetUrl ? HtmlLinkDom::unwrap($doc, $targetUrl, $anchor) : 0);

            if ($count > 0) {
                $source->update([$field => HtmlLinkDom::save($doc)]);
                $removed += $count;
            }
        }

        // Any pending suggestion for this pair can resurface after removal.
        return $removed > 0;
    }

    /** Content fields the link agent treats as linkable, per source type. */
    protected function linkableFields(\Illuminate\Database\Eloquent\Model $source): array
    {
        return match (true) {
            $source instanceof \App\Models\Product => ['description', 'short_description'],
            $source instanceof \App\Models\Category => ['content_block'],
            default => ['content'],
        };
    }

    protected function rootRelative(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: $url;
    }
}
