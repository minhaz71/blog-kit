<?php

namespace Tests\Feature;

use App\Services\Seo\SeoAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scores_high_when_all_signals_are_present(): void
    {
        $content = str_repeat('<p>Widgets are the finest widgets in every widget category we test. Widgets deliver value.</p> ', 20)
            .'<h2>Why widgets?</h2><p>Because widgets. <a href="/">Learn more</a> <a href="https://example.org">External</a> <img src="w.jpg" alt="widgets"></p>';

        $result = app(SeoAnalyzer::class)->analyze(
            focusKeyword: 'widgets',
            title: 'Widgets — The Best Widgets Online for Every Budget',
            description: 'Widgets are made better here. Read our detailed widgets buying guide covering styles, prices, and reviews.',
            slug: 'best-widgets-online',
            content: $content,
            h1: 'The Best Widgets Online',
            hasSchema: true,
            hasCanonical: true,
        );

        $this->assertGreaterThanOrEqual(80, $result['score'], 'Well-optimized page should score 80+.');
    }

    public function test_scores_low_when_focus_keyword_missing_everywhere(): void
    {
        $result = app(SeoAnalyzer::class)->analyze(
            focusKeyword: 'nonexistent-keyword',
            title: 'Some page',
            description: 'A short page.',
            slug: 'a',
            content: '<p>Nothing here.</p>',
            h1: 'Something',
            hasSchema: false,
            hasCanonical: false,
        );

        $this->assertLessThan(50, $result['score'], 'Bare page should score under 50.');
    }
}
