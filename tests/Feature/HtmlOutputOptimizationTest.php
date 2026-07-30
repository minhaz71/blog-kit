<?php

namespace Tests\Feature;

use App\Http\Middleware\MinifyHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HtmlOutputOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_minifier_collapses_whitespace_but_protects_content(): void
    {
        $html = "<div>\n    <p>Hello   world</p>\n\n    <a>foo</a> <a>bar</a>\n</div>"
            ."<!-- a stray comment -->"
            ."<script>\n  const x = 1;\n  // keep me exactly\n</script>"
            ."<pre>  spaced\n  lines  </pre>";

        $out = MinifyHtml::minify($html);

        // Outside protected blocks: no newlines, no whitespace runs.
        $outside = preg_replace('~<(script|pre)\b.*?</\1>~s', '', $out);
        $this->assertStringNotContainsString("\n", $outside);
        $this->assertStringNotContainsString('  ', $outside);
        $this->assertStringNotContainsString('stray comment', $out);              // comments stripped
        $this->assertStringContainsString('<a>foo</a> <a>bar</a>', $out);          // inline word gap kept
        $this->assertStringContainsString("// keep me exactly\n", $out);           // script untouched
        $this->assertStringContainsString("<pre>  spaced\n  lines  </pre>", $out); // pre untouched
    }

    public function test_storefront_ships_no_inline_style_blocks(): void
    {
        $this->seed();

        $html = $this->get('/')->assertOk()->getContent();

        // Fonts + theme both load from external files, never inline <style>.
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringNotContainsString('id="theme-overrides"', $html);
        $this->assertStringContainsString('fonts-', $html);          // external fonts css link

        // Outside protected script/style blocks: no plain comments, no
        // whitespace runs. Bracket markers (<!--[if ...], framework block
        // markers) are deliberately preserved by the minifier — exclude them.
        $stripped = preg_replace('~<(script|pre|textarea|style)\b.*?</\1>~is', '', $html);
        preg_match_all('/<!--.*?-->/s', $stripped, $m);
        $plain = array_values(array_filter($m[0], fn ($c) => ! str_starts_with($c, '<!--[')
            // Livewire injects its asset markers AFTER the minifier when a prior
            // test booted Filament in the same process — not real page content.
            && ! str_contains($c, 'Livewire')
            // The page-cache signature comment is appended AFTER the minifier
            // on purpose — it tells cached from fresh in view-source.
            && ! str_contains($c, 'Hemdox Ecommerce CRM')));
        $this->assertSame([], $plain, 'Plain HTML comments shipped: '.implode(' | ', array_map(fn ($c) => mb_substr($c, 0, 80), $plain)));
        $this->assertStringNotContainsString('   ', $stripped);
    }

    public function test_theme_css_compiles_to_a_hashed_file(): void
    {
        \App\Models\Setting::set('appearance.primary_color', '#0f766e');

        $href = theme_css_href();

        $this->assertNotNull($href);
        $this->assertStringContainsString('storage/theme/overrides-', $href);

        $relative = 'theme/'.basename(parse_url($href, PHP_URL_PATH));
        $css = \Illuminate\Support\Facades\Storage::disk('public')->get($relative);
        $this->assertStringContainsString('--brand:#0f766e', $css);
        $this->assertStringContainsString('.bg-indigo-600{background-color:#0f766e', $css);
    }
}
