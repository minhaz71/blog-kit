<?php

namespace Tests\Feature;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_strips_scripts_and_iframes_with_content(): void
    {
        $out = HtmlSanitizer::clean('<p>Safe</p><script>alert(1)</script><iframe src="evil"></iframe><p>Also safe</p>');

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringContainsString('<p>Safe</p>', $out);
        $this->assertStringContainsString('<p>Also safe</p>', $out);
    }

    public function test_removes_event_handlers_and_js_urls(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:steal()" onclick="x()">click</a> <img src="/img/pic.jpg" onerror="hack()">');

        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onerror=', $out);
        $this->assertStringNotContainsString('hack()', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        // The visible anchor text and the img tag itself survive (just cleaned).
        $this->assertStringContainsString('click', $out);
        $this->assertStringContainsString('<img', $out);
    }

    public function test_keeps_legitimate_semantic_html_and_internal_links(): void
    {
        $html = '<h2>Flavor</h2><p>Bold and <strong>rich</strong>. See <a href="/product/terea-amber">TEREA Amber</a>.</p><ul><li>Point</li></ul><table><tr><td>Spec</td></tr></table>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_allows_image_data_uri_but_not_link_data_uri(): void
    {
        $img = HtmlSanitizer::clean('<img src="data:image/png;base64,iVBOR">');
        $this->assertStringContainsString('data:image/png', $img);

        $link = HtmlSanitizer::clean('<a href="data:text/html,<script>evil</script>">x</a>');
        $this->assertStringNotContainsString('data:text/html', $link);
    }
}
