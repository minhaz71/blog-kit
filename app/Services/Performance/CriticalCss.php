<?php

namespace App\Services\Performance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Per-page critical CSS for guest traffic — no headless browser, no
 * external service (LiteSpeed's CCSS needs QUIC.cloud; this is pure PHP).
 *
 * Approach: parse the compiled Tailwind stylesheet once per page, keep
 * only rules whose classes/ids actually appear in that page's HTML,
 * inline the result and swap the render-blocking stylesheet link to an
 * async one (media="print" trick + noscript fallback). Utility-first CSS
 * makes this very effective: almost every selector is a single class.
 *
 * Selection rulebook (what counts as critical):
 *  INCLUDE — structural layout (grid/flex wrappers), the header/main nav,
 *  hero-section styles, essential typography, and visibility resets
 *  (preflight, [x-cloak]). Implemented as: only tokens ABOVE the
 *  <!--critical-fold--> template marker count, from elements actually
 *  visible at load.
 *  EXCLUDE — hidden-at-load elements (modals, drawers, dropdowns: anything
 *  under [x-cloak]/[hidden]/<template>/<dialog>/display:none), lower-page
 *  components (<footer> and everything below the fold marker), and heavy
 *  animation work (@keyframes, :hover/:focus/:active rules,
 *  transition/animation declarations). The full stylesheet always loads
 *  right after, so excluded styling appears before the user can interact.
 *
 * A wrongly KEPT rule costs a few bytes; a wrongly dropped one only
 * mis-styles the pre-full-CSS window — so ambiguity resolves toward
 * keeping: selectors without classes/ids stay, and classes inside
 * :where()/:is()/:not() are never treated as required.
 *
 * Results are cached per path, keyed by the CSS build hash AND the page
 * cache version — content edits and Purge All regenerate them.
 */
class CriticalCss
{
    /** Template marker: tokens below this point are below the fold. */
    public const FOLD_MARKER = '<!--critical-fold-->';

    public static function enabled(): bool
    {
        return (bool) setting('performance.critical_css_enabled', true);
    }

    /** Rewrite the page's app stylesheet into inline critical + async full. */
    public function transform(string $html, Request $request): string
    {
        if (! self::enabled() || ! PageCache::eligible($request)) {
            return $html;
        }

        // The compiled app stylesheet link (theme overrides + fonts are tiny
        // and stay as-is). Hash in the filename = safe long-lived cache key.
        if (! preg_match('#<link[^>]*rel="stylesheet"[^>]*href="([^"]*/build/assets/(app-[^"/]+\.css))"[^>]*>#', $html, $m)) {
            return $html;
        }

        [$linkTag, $href, $file] = $m;

        $path = public_path('build/assets/'.$file);
        if (! is_file($path)) {
            return $html;
        }

        try {
            $version = (int) Cache::get('pagecache.version', 1);
            $key = 'critcss.v'.$version.'.'.md5($file.'|'.$request->path());

            $critical = Cache::remember(
                $key,
                now()->addDays(7),
                fn () => $this->generate($html, (string) file_get_contents($path))
            );
        } catch (\Throwable) {
            return $html; // never break the page over an optimization
        }

        if ($critical === '') {
            return $html;
        }

        // Inline the critical rules; the full stylesheet still loads (the
        // Vite preload keeps its network priority) but no longer blocks
        // rendering. noscript covers JS-disabled visitors.
        $replacement = '<style id="critical-css">'.$critical.'</style>'
            .'<link rel="stylesheet" href="'.$href.'" media="print" onload="this.media=\'all\';this.onload=null" data-navigate-track="reload">'
            .'<noscript><link rel="stylesheet" href="'.$href.'"></noscript>';

        // The fold marker has served its purpose — keep shipped HTML clean.
        return str_replace([$linkTag, self::FOLD_MARKER], [$replacement, ''], $html);
    }

    /** Filter the stylesheet down to rules this page can actually use. */
    public function generate(string $html, string $css): string
    {
        [$classes, $ids] = $this->pageTokens($html);

        if ($classes === []) {
            return '';
        }

        $filtered = $this->filterCss($css, $classes, $ids);

        // Second pass: Tailwind v4 ships heavy --tw-* machinery (@property
        // registrations + a universal variable reset) that the first pass
        // keeps wholesale. Drop the parts no surviving rule reads.
        preg_match_all('/var\(\s*(--[\w-]+)/', $filtered, $m);

        return $this->pruneVars($filtered, array_fill_keys($m[1], true));
    }

    /**
     * Remove @property blocks and universal-reset custom-property
     * declarations for variables nothing in the kept CSS references.
     */
    protected function pruneVars(string $css, array $usedVars): string
    {
        $out = '';
        $i = 0;
        $len = strlen($css);

        while ($i < $len) {
            if (ctype_space($css[$i])) {
                $i++;

                continue;
            }

            if ($css[$i] === '@') {
                $brace = $this->findUnquoted($css, $i, '{;');
                if ($brace === null) {
                    break;
                }

                if ($css[$brace] === ';') {
                    $out .= substr($css, $i, $brace - $i + 1);
                    $i = $brace + 1;

                    continue;
                }

                $header = trim(substr($css, $i, $brace - $i));
                [$body, $i] = $this->readBlock($css, $brace);

                if (preg_match('/^@property\s+(--[\w-]+)/', $header, $pm)) {
                    if (isset($usedVars[$pm[1]])) {
                        $out .= $header.'{'.$body.'}';
                    }

                    continue;
                }

                if (preg_match('/^@(layer|media|supports|container)\b/', $header)) {
                    $inner = $this->pruneVars($body, $usedVars);
                    if ($inner !== '') {
                        $out .= $header.'{'.$inner.'}';
                    }

                    continue;
                }

                $out .= $header.'{'.$body.'}';

                continue;
            }

            $brace = $this->findUnquoted($css, $i, '{');
            if ($brace === null) {
                break;
            }

            $selector = trim(substr($css, $i, $brace - $i));
            [$body, $i] = $this->readBlock($css, $brace);

            // The universal reset (*, ::before, …) is a wall of --tw-*: initial
            // declarations — keep only the variables something actually reads.
            if (str_starts_with($selector, '*')) {
                $body = implode(';', array_filter(
                    explode(';', $body),
                    function (string $declaration) use ($usedVars): bool {
                        $property = trim(strtok($declaration, ':'));

                        return ! str_starts_with($property, '--') || isset($usedVars[$property]);
                    }
                ));

                if (trim($body) === '') {
                    continue;
                }
            }

            $out .= $selector.'{'.$body.'}';
        }

        return $out;
    }

    /**
     * Collect class/id sets for content that is VISIBLE AT FIRST PAINT:
     * cut at the fold marker, then drop hidden-at-load and lower-page
     * subtrees (modals, drawers, dropdowns, footer). Alpine :class
     * literals are deliberately NOT collected — JS-toggled states are
     * post-interaction by definition, and the full stylesheet is applied
     * long before the user can toggle anything.
     *
     * @return array{0: array<string, true>, 1: array<string, true>}
     */
    protected function pageTokens(string $html): array
    {
        if (($cut = strpos($html, self::FOLD_MARKER)) !== false) {
            $html = substr($html, 0, $cut);
        }

        [$classes, $ids] = $this->visibleTokens($html);

        // DOM parse failed outright (mangled markup) — fall back to a plain
        // regex sweep of the cut HTML rather than shipping an empty page.
        if ($classes === []) {
            preg_match_all('/\bclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $html, $m);
            $classes = array_fill_keys(
                preg_split('/\s+/', implode(' ', array_merge($m[1], $m[2])), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                true
            );

            preg_match_all('/\bid\s*=\s*"([^"]*)"/i', $html, $m);
            $ids = array_fill_keys(array_filter($m[1]), true);
        }

        return [$classes, $ids];
    }

    /**
     * DOM pass: remove subtrees that contribute nothing to first paint,
     * then collect tokens from what remains.
     *
     * @return array{0: array<string, true>, 1: array<string, true>}
     */
    protected function visibleTokens(string $html): array
    {
        $classes = [];
        $ids = [];

        libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument();
            if (! @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return [[], []];
            }

            $xpath = new \DOMXPath($dom);

            // Hidden at load (modals, drawers, dropdown panels) or
            // lower-page (footer) — excluded per the critical-CSS rulebook.
            $query = '//*[@x-cloak or @hidden or @data-non-critical]'
                .' | //footer | //template | //dialog'
                .' | //*[contains(translate(@style, " ", ""), "display:none")]';

            foreach ($xpath->query($query) as $node) {
                $node->parentNode?->removeChild($node);
            }

            foreach ($xpath->query('//*[@class]') as $element) {
                foreach (preg_split('/\s+/', $element->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY) as $class) {
                    $classes[$class] = true;
                }
            }

            foreach ($xpath->query('//*[@id]') as $element) {
                if (($id = $element->getAttribute('id')) !== '') {
                    $ids[$id] = true;
                }
            }
        } catch (\Throwable) {
            return [[], []];
        } finally {
            libxml_clear_errors();
        }

        return [$classes, $ids];
    }

    /** Recursively filter a CSS block (handles @media/@supports/@layer nesting). */
    protected function filterCss(string $css, array $classes, array $ids): string
    {
        $out = '';
        $i = 0;
        $len = strlen($css);

        while ($i < $len) {
            // Whitespace / comments between rules.
            if (ctype_space($css[$i])) {
                $i++;

                continue;
            }
            if (substr($css, $i, 2) === '/*') {
                $end = strpos($css, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;

                continue;
            }

            if ($css[$i] === '@') {
                $brace = $this->findUnquoted($css, $i, '{;');
                if ($brace === null) {
                    break;
                }

                if ($css[$brace] === ';') {
                    // @import / @charset / @layer list — keep verbatim.
                    $out .= substr($css, $i, $brace - $i + 1);
                    $i = $brace + 1;

                    continue;
                }

                $header = trim(substr($css, $i, $brace - $i));
                [$body, $i] = $this->readBlock($css, $brace);

                $name = strtolower(strtok(ltrim($header, '@'), " \t("));

                if (in_array($name, ['media', 'supports', 'layer', 'container'], true)) {
                    $inner = $this->filterCss($body, $classes, $ids);
                    if ($inner !== '') {
                        $out .= $header.'{'.$inner.'}';
                    }
                } elseif (str_contains($name, 'keyframes')) {
                    // Animations are never critical — first paint is static.
                } else {
                    // @font-face, @property, @page… — structural, cheap,
                    // and referenced from kept rules: always keep.
                    $out .= $header.'{'.$body.'}';
                }

                continue;
            }

            // Ordinary rule: selector { declarations }
            $brace = $this->findUnquoted($css, $i, '{');
            if ($brace === null) {
                break;
            }

            $selector = trim(substr($css, $i, $brace - $i));
            [$body, $i] = $this->readBlock($css, $brace);

            if ($this->selectorUsed($selector, $classes, $ids)) {
                $body = $this->stripAnimationWork($body);
                if (trim($body) !== '') {
                    $out .= $selector.'{'.$body.'}';
                }
            }
        }

        return $out;
    }

    /**
     * Transitions/animations only matter once the user interacts — by then
     * the full stylesheet has long applied. Dropping them also prevents
     * entrance animations replaying half-styled during first paint.
     */
    protected function stripAnimationWork(string $body): string
    {
        return (string) preg_replace(
            '/(?<![\w-])(?:transition|animation|will-change)[\w-]*\s*:[^;}]*;?/i',
            '',
            $body
        );
    }

    /** A rule survives if ANY comma-separated selector could apply to this page. */
    protected function selectorUsed(string $selectorList, array $classes, array $ids): bool
    {
        foreach ($this->splitSelectors($selectorList) as $selector) {
            // Classes inside :where()/:is()/:not() are alternatives or
            // exclusions, never hard requirements — strip them first.
            do {
                $selector = preg_replace('/\([^()]*\)/', '', $selector, -1, $replaced);
            } while ($replaced > 0);

            // Interaction states are post-first-paint by definition.
            if (preg_match('/:(?:hover|active|focus|focus-visible|focus-within)\b/', $selector)) {
                continue;
            }

            preg_match_all('/\.((?:\\\\.|[\w-])+)/', $selector, $m);
            $required = array_map('stripslashes', $m[1]);

            preg_match_all('/#((?:\\\\.|[\w-])+)/', $selector, $m);
            $requiredIds = array_map('stripslashes', $m[1]);

            // No class/id requirement (element, universal, [attr] selectors,
            // preflight) — keep: cheap and often load-bearing (e.g. x-cloak).
            if ($required === [] && $requiredIds === []) {
                return true;
            }

            $ok = true;
            foreach ($required as $class) {
                if (! isset($classes[$class])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                foreach ($requiredIds as $id) {
                    if (! isset($ids[$id])) {
                        $ok = false;
                        break;
                    }
                }
            }

            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /** Split a selector list on top-level commas ( :where(a, b) stays intact ). */
    protected function splitSelectors(string $selectorList): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        for ($i = 0, $len = strlen($selectorList); $i < $len; $i++) {
            $char = $selectorList[$i];

            if ($char === '\\') {
                $current .= $char.($selectorList[$i + 1] ?? '');
                $i++;

                continue;
            }

            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return array_filter(array_map('trim', $parts), fn ($p) => $p !== '');
    }

    /** Index of the first of $needles at brace/quote depth 0, or null. */
    protected function findUnquoted(string $css, int $from, string $needles): ?int
    {
        for ($i = $from, $len = strlen($css); $i < $len; $i++) {
            $char = $css[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }
            if ($char === '"' || $char === "'") {
                $i = $this->skipString($css, $i);

                continue;
            }
            if (str_contains($needles, $char)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Read a balanced {...} block starting at the opening brace.
     *
     * @return array{0: string, 1: int} body (without braces) and next index
     */
    protected function readBlock(string $css, int $openBrace): array
    {
        $depth = 0;

        for ($i = $openBrace, $len = strlen($css); $i < $len; $i++) {
            $char = $css[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }
            if ($char === '"' || $char === "'") {
                $i = $this->skipString($css, $i);

                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                return [substr($css, $openBrace + 1, $i - $openBrace - 1), $i + 1];
            }
        }

        return [substr($css, $openBrace + 1), $len];
    }

    /** Index of the closing quote (handles escapes). */
    protected function skipString(string $css, int $openQuote): int
    {
        $quote = $css[$openQuote];

        for ($i = $openQuote + 1, $len = strlen($css); $i < $len; $i++) {
            if ($css[$i] === '\\') {
                $i++;
            } elseif ($css[$i] === $quote) {
                return $i;
            }
        }

        return $len;
    }
}
