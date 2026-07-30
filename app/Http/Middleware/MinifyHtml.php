<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Minifies storefront HTML: collapses the whitespace runs Blade control
 * structures leave behind and strips HTML comments. Cuts page weight
 * ~15-20% and directly improves the text-to-HTML ratio.
 *
 * <script>, <style>, <pre> and <textarea> contents are preserved
 * byte-for-byte. Admin + Livewire endpoints are skipped — Livewire's DOM
 * morphing must see markup exactly as it rendered it.
 */
class MinifyHtml
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $response = $next($request);

        if (! config('blogkit.minify_html', true)
            || ! $response instanceof Response
            || $request->is('admin/*', 'admin', 'livewire/*')
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || $html === '') {
            return $response;
        }

        $response->setContent(self::minify($html));

        return $response;
    }

    public static function minify(string $html): string
    {
        // Carve out blocks whose whitespace is meaningful.
        $protected = [];
        $html = (string) preg_replace_callback(
            '~<(pre|textarea|script|style)\b[^>]*>.*?</\1>~is',
            function (array $m) use (&$protected): string {
                $token = "\x1A".count($protected)."\x1A";
                $protected[$token] = $m[0];

                return $token;
            },
            $html,
        );

        // Drop HTML comments (Blade {{-- --}} never reaches output; these are
        // stray <!-- --> from markup). Keep IE conditionals, and keep the
        // critical-fold marker — the CriticalCss middleware runs AFTER minify
        // and needs it to know where above-the-fold content ends.
        $html = (string) preg_replace('/<!--(?!\[if)(?!<!)(?!critical-fold)[^\[>].*?-->/s', '', $html);

        // Collapse all whitespace runs to a single space. Single spaces stay
        // (never removed entirely): "<a>…</a> <a>…</a>" keeps its word gap,
        // so inline typography is untouched while the Blade indentation goes.
        $html = (string) preg_replace('/\s{2,}|\n|\t/', ' ', $html);

        return trim(strtr($html, $protected));
    }
}
