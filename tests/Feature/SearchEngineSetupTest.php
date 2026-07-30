<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Search-engine ownership verification meta tags + Google tag / tracking code
 * injection, with correct <head> / after-<body> placement.
 */
class SearchEngineSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_tags_by_default(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('google-site-verification', $html);
        $this->assertStringNotContainsString('googletagmanager.com', $html);
    }

    public function test_verification_meta_tags_render_for_each_engine(): void
    {
        Setting::set('seo.verify_google', 'g-token-123');
        Setting::set('seo.verify_bing', 'bing-token-456');
        Setting::set('seo.verify_yandex', 'yandex-token');
        Setting::set('seo.verify_baidu', 'baidu-token');
        Setting::set('seo.verify_pinterest', 'pin-token');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="google-site-verification" content="g-token-123">', $html);
        $this->assertStringContainsString('<meta name="msvalidate.01" content="bing-token-456">', $html);
        $this->assertStringContainsString('<meta name="yandex-verification" content="yandex-token">', $html);
        $this->assertStringContainsString('<meta name="baidu-site-verification" content="baidu-token">', $html);
        $this->assertStringContainsString('<meta name="p:domain_verify" content="pin-token">', $html);
    }

    public function test_verification_extracts_token_from_a_pasted_meta_tag(): void
    {
        Setting::set('seo.verify_google', '<meta name="google-site-verification" content="PASTED_XYZ" />');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('content="PASTED_XYZ"', $html);
        // The whole raw tag is not double-emitted.
        $this->assertSame(1, substr_count($html, 'google-site-verification'));
    }

    public function test_verification_still_renders_while_discouraging_indexing(): void
    {
        Setting::set('seo.discourage_indexing', true);
        Setting::set('seo.verify_google', 'still-verify');

        $this->get('/')->assertOk()
            ->assertSee('content="still-verify"', false)
            ->assertSee('noindex, nofollow', false);
    }

    public function test_google_tag_manager_injects_head_and_noscript(): void
    {
        Setting::set('seo.google_tag_manager_id', 'GTM-TEST123');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("gtm.js?id=", $html);
        $this->assertStringContainsString('GTM-TEST123', $html);
        // noscript iframe fallback (placed after <body>).
        $this->assertStringContainsString('googletagmanager.com/ns.html?id=GTM-TEST123', $html);
    }

    public function test_google_tag_gtag_injects_in_head(): void
    {
        Setting::set('seo.google_tag_id', 'G-ABC1234567');

        $this->get('/')->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-ABC1234567', false)
            ->assertSee("gtag('config','G-ABC1234567')", false);
    }

    public function test_custom_head_and_body_code_is_injected(): void
    {
        Setting::set('seo.custom_head_code', '<meta name="custom-head" content="yes">');
        Setting::set('seo.custom_body_code', '<span data-custom-body="yes"></span>');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('name="custom-head"', $html);
        $this->assertStringContainsString('data-custom-body="yes"', $html);
    }
}
