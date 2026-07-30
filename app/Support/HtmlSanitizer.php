<?php

namespace App\Support;

/**
 * Defense-in-depth sanitizer for AI-generated HTML before it is stored and
 * rendered raw on the storefront. The writers are instructed to emit clean
 * semantic HTML, but the model is not fully trusted (a prompt-injected CSV
 * row could smuggle a payload), so we strip anything executable here rather
 * than rely on the prompt alone.
 *
 * Deliberately targeted, not a full HTML parser: AI copy is simple
 * (h2/h3/p/ul/ol/li/table/blockquote/strong/em/a/img). We remove executable
 * and framing constructs and neutralise dangerous URLs, keeping the markup.
 */
class HtmlSanitizer
{
    // NOTE: <style> is deliberately NOT stripped here — the
    // MovesInlineStylesToCustomCss trait extracts style blocks/attributes into
    // a scoped, cached CSS file (the store's design layer). CSS is not a
    // JS-execution vector on modern browsers, so this sanitizer owns only the
    // executable/framing constructs and leaves all styling to that trait.
    /** Tags removed entirely, WITH their contents. */
    private const STRIP_WITH_CONTENT = ['script', 'iframe', 'object', 'embed', 'form', 'noscript', 'svg', 'template'];

    /** Tags removed but whose inner text is kept (unwrapped). */
    private const UNWRAP = ['link', 'meta', 'base', 'input', 'button'];

    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if ($html === '') {
            return '';
        }

        // 1. Drop dangerous elements and everything inside them.
        foreach (self::STRIP_WITH_CONTENT as $tag) {
            $html = (string) preg_replace('~<'.$tag.'\b[^>]*>.*?</'.$tag.'>~is', '', $html);
            // Also any self-closing / unclosed occurrence.
            $html = (string) preg_replace('~<'.$tag.'\b[^>]*/?>~is', '', $html);
        }

        // 2. Remove void/framing tags but keep any text around them.
        foreach (self::UNWRAP as $tag) {
            $html = (string) preg_replace('~</?'.$tag.'\b[^>]*>~is', '', $html);
        }

        // 3. Strip inline event handlers: on*="…" / on*='…' / on*=unquoted.
        $html = (string) preg_replace('~\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~is', '', $html);

        // 4. Neutralise dangerous URL schemes in href/src (keep image data URIs).
        $html = (string) preg_replace_callback(
            '~\s(href|src)\s*=\s*("([^"]*)"|\'([^\']*)\')~is',
            function (array $m): string {
                $attr = strtolower($m[1]);
                $value = $m[3] !== '' ? $m[3] : ($m[4] ?? '');
                $scheme = ltrim(strtolower(html_entity_decode($value)));

                $bad = str_starts_with($scheme, 'javascript:')
                    || str_starts_with($scheme, 'vbscript:')
                    // data: is allowed only for images (src), never for links.
                    || (str_starts_with($scheme, 'data:') && ! ($attr === 'src' && str_starts_with($scheme, 'data:image/')));

                return $bad ? '' : $m[0];
            },
            $html
        );

        return trim($html);
    }
}
