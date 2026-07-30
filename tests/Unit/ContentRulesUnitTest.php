<?php

namespace Tests\Unit;

use App\Services\Ai\ContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic unit tests: no database, no framework boot, no network. These
 * exercise the deterministic content rules in isolation — the fast layer
 * of the pyramid the suite was missing.
 */
class ContentRulesUnitTest extends TestCase
{
    public function test_strip_em_dashes_rewrites_all_dash_forms(): void
    {
        $this->assertSame('word, word', ContentReviewer::stripEmDashes('word — word'));
        $this->assertSame('word, word', ContentReviewer::stripEmDashes('word – word'));
        $this->assertSame('2-4', ContentReviewer::stripEmDashes('2—4')); // numeric range → hyphen
        $this->assertSame('plain text', ContentReviewer::stripEmDashes('plain text'));
    }

    public function test_strip_em_dashes_recurses_into_arrays(): void
    {
        $in = ['a' => 'x — y', 'nested' => ['b' => 'p – q']];
        $out = ContentReviewer::stripEmDashes($in);

        $this->assertSame('x, y', $out['a']);
        $this->assertSame('p, q', $out['nested']['b']);
    }

    public function test_clamp_meta_trims_at_word_boundary_within_limits(): void
    {
        $long = str_repeat('word ', 30);
        $out = ContentReviewer::clampMetaLengths(['meta_title' => $long, 'meta_description' => $long]);

        $this->assertLessThanOrEqual(63, mb_strlen($out['meta_title']));
        $this->assertLessThanOrEqual(164, mb_strlen($out['meta_description']));
        // Trimmed at a space, not mid-word.
        $this->assertStringEndsNotWith('wor', $out['meta_title']);
    }

    public function test_clamp_leaves_in_range_values_untouched(): void
    {
        $desc = str_repeat('c', 155);
        $out = ContentReviewer::clampMetaLengths(['meta_description' => $desc]);
        $this->assertSame($desc, $out['meta_description']);
    }

    public function test_keyword_covered_indirectly_needs_majority_of_words(): void
    {
        // 3 of 4 meaningful words present → covered.
        $this->assertTrue(ContentReviewer::keywordCoveredIndirectly(
            'terea stick pack size', 'each terea carton holds ten packs of sticks'
        ));
        // Only 1 of 4 → not covered.
        $this->assertFalse(ContentReviewer::keywordCoveredIndirectly(
            'menthol cooling capsule strength', 'the pack holds sticks'
        ));
    }

    public function test_keyword_indirect_match_is_stemmed(): void
    {
        $this->assertTrue(ContentReviewer::keywordCoveredIndirectly(
            'cleaning guide', 'how to clean the device, a practical guide'
        ));
    }
}
