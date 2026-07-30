<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMText;
use DOMXPath;

/**
 * Shared HTML walker for the link agent. The suggestion engine and the
 * applier MUST agree on which text is "linkable", so both use this one
 * definition: text nodes that are not inside an existing link, heading,
 * table header, button, or code/script/style.
 */
class HtmlLinkDom
{
    public const SKIP_ANCESTORS = ['a', 'h1', 'h2', 'h3', 'th', 'button', 'script', 'style', 'code', 'pre'];

    public static function load(string $html): DOMDocument
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        return $doc;
    }

    /** Inner HTML of the body wrapper — the original fragment, modified. */
    public static function save(DOMDocument $doc): string
    {
        $body = $doc->getElementsByTagName('body')->item(0);

        if (! $body) {
            return '';
        }

        $out = '';

        foreach ($body->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * Unwrap every <a> whose href matches (and, when given, anchor text
     * matches) back to plain text, in-place. Returns how many were removed.
     */
    public static function unwrap(DOMDocument $doc, string $href, ?string $anchor = null): int
    {
        $removed = 0;

        // Snapshot: modifying the live list mid-iteration skips siblings.
        $links = iterator_to_array($doc->getElementsByTagName('a'));

        foreach ($links as $link) {
            $hrefMatch = $link->getAttribute('href') === $href;
            $anchorMatch = $anchor === null || strcasecmp(trim($link->textContent), trim($anchor)) === 0;

            if ($hrefMatch && $anchorMatch) {
                $link->parentNode->replaceChild($doc->createTextNode($link->textContent), $link);
                $removed++;
            }
        }

        return $removed;
    }

    /** @return DOMText[] linkable text nodes in document order */
    public static function eligibleTextNodes(DOMDocument $doc): array
    {
        $nodes = [];

        foreach ((new DOMXPath($doc))->query('//text()') as $node) {
            if (trim($node->nodeValue) === '') {
                continue;
            }

            $skip = false;

            for ($el = $node->parentNode; $el !== null && strtolower($el->nodeName) !== 'body'; $el = $el->parentNode) {
                if (in_array(strtolower($el->nodeName), self::SKIP_ANCESTORS, true)) {
                    $skip = true;
                    break;
                }
            }

            if (! $skip) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * Find the N-th (1-based) case-insensitive occurrence of $anchor across
     * the eligible text nodes. Byte offsets throughout — the same arithmetic
     * the suggestion engine used when it computed the occurrence number.
     * Returns [DOMText, byte offset] or null.
     */
    public static function findOccurrence(DOMDocument $doc, string $anchor, int $occurrence): ?array
    {
        $seen = 0;

        foreach (self::eligibleTextNodes($doc) as $node) {
            $offset = 0;

            while (($pos = stripos($node->nodeValue, $anchor, $offset)) !== false) {
                $seen++;

                if ($seen === $occurrence) {
                    return [$node, $pos];
                }

                $offset = $pos + strlen($anchor);
            }
        }

        return null;
    }
}
