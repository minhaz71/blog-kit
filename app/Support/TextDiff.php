<?php

namespace App\Support;

/**
 * Word-level diff for the post revision comparer (WordPress-style):
 * deletions render as <del>, insertions as <ins>, everything HTML-escaped.
 * Classic LCS over word tokens; HTML input is flattened to readable text
 * first so the comparison is about the words, not the markup.
 */
class TextDiff
{
    /** Render an inline word-diff between two strings as safe HTML. */
    public static function html(?string $old, ?string $new): string
    {
        $oldTokens = self::tokenize($old);
        $newTokens = self::tokenize($new);

        // LCS table (word counts stay small: a 1,500-word article is fine).
        $m = count($oldTokens);
        $n = count($newTokens);
        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $oldTokens[$i] === $newTokens[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        // Walk the table, buffering runs so adjacent words share one tag.
        $out = '';
        $del = [];
        $ins = [];
        $i = 0;
        $j = 0;

        $flush = function () use (&$out, &$del, &$ins) {
            if ($del !== []) {
                $out .= '<del>'.e(implode(' ', $del)).'</del> ';
                $del = [];
            }
            if ($ins !== []) {
                $out .= '<ins>'.e(implode(' ', $ins)).'</ins> ';
                $ins = [];
            }
        };

        while ($i < $m && $j < $n) {
            if ($oldTokens[$i] === $newTokens[$j]) {
                $flush();
                $out .= e($oldTokens[$i]).' ';
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $del[] = $oldTokens[$i++];
            } else {
                $ins[] = $newTokens[$j++];
            }
        }

        while ($i < $m) {
            $del[] = $oldTokens[$i++];
        }
        while ($j < $n) {
            $ins[] = $newTokens[$j++];
        }
        $flush();

        return trim($out);
    }

    /** True when the two values differ once flattened to comparable text. */
    public static function changed(?string $old, ?string $new): bool
    {
        return self::tokenize($old) !== self::tokenize($new);
    }

    /** @return array<int, string> */
    protected static function tokenize(?string $value): array
    {
        // Keep block boundaries readable: paragraph/heading breaks become a
        // pilcrow token so structural moves show up in the diff.
        $text = preg_replace('~</(p|div|h[1-6]|li|tr|blockquote)>~i', ' ¶ ', (string) $value);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? [] : explode(' ', $text);
    }
}
