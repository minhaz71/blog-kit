<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Services\Content\ShortcodeParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShortcodeParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('content_blocks.rendered');
    }

    public function test_block_shortcode_is_replaced_in_html(): void
    {
        ContentBlock::create([
            'key' => 'delivery',
            'name' => 'Delivery',
            'type' => 'notice',
            'body' => 'Ships next business day.',
            'is_active' => true,
        ]);

        $html = '<p>Buy this!</p>{{block:delivery}}<p>Enjoy.</p>';
        $out = app(ShortcodeParser::class)->parse($html);

        $this->assertStringContainsString('Ships next business day.', $out);
        $this->assertStringNotContainsString('{{block:delivery}}', $out);
    }

    public function test_unknown_block_falls_through_untouched(): void
    {
        $html = 'Before {{block:missing}} after.';
        $out = app(ShortcodeParser::class)->parse($html);

        $this->assertSame($html, $out);
    }

    public function test_inactive_block_does_not_render(): void
    {
        ContentBlock::create([
            'key' => 'hidden',
            'name' => 'Hidden',
            'type' => 'notice',
            'body' => 'You cannot see me',
            'is_active' => false,
        ]);

        $out = app(ShortcodeParser::class)->parse('{{block:hidden}}');
        $this->assertStringNotContainsString('You cannot see me', $out);
        $this->assertSame('{{block:hidden}}', $out);
    }

    public function test_cta_block_renders_button(): void
    {
        ContentBlock::create([
            'key' => 'shop',
            'name' => 'Shop',
            'type' => 'cta',
            'body' => 'Come see the drop.',
            'data' => ['button_text' => 'Shop now', 'button_url' => '/shop'],
            'is_active' => true,
        ]);

        $out = app(ShortcodeParser::class)->parse('{{block:shop}}');
        $this->assertStringContainsString('Shop now', $out);
        $this->assertStringContainsString('/shop', $out);
    }
}
