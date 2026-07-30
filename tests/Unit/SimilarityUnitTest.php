<?php

namespace Tests\Unit;

use App\Models\BlogTopicIdea;
use App\Services\Search\ProductSearch;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function unit tests for the text-matching primitives shared by the
 * link agent, the funnel canonical guard, and live search. No DB.
 */
class SimilarityUnitTest extends TestCase
{
    public function test_fingerprint_is_word_order_independent(): void
    {
        $a = BlogTopicIdea::fingerprint('TEREA Amber Kazakhstan');
        $b = BlogTopicIdea::fingerprint('Kazakhstan Amber TEREA');
        $this->assertSame($a, $b, 'Reordered same words must fingerprint identically');

        $c = BlogTopicIdea::fingerprint('TEREA Sienna Japan');
        $this->assertNotSame($a, $c);
    }

    public function test_similarity_scores_reworded_titles_high_and_distinct_low(): void
    {
        // Same meaningful words reordered + one extra → high overlap.
        $high = BlogTopicIdea::similarity(
            'Clean IQOS ILUMA guide',
            'IQOS ILUMA clean guide steps'
        );
        // No shared meaningful words → ~0.
        $low = BlogTopicIdea::similarity(
            'Clean IQOS ILUMA guide',
            'TEREA Amber flavor buying tips'
        );

        $this->assertGreaterThanOrEqual(0.6, $high);
        $this->assertLessThan(0.3, $low);
        // Symmetric.
        $this->assertEqualsWithDelta(
            $high,
            BlogTopicIdea::similarity('IQOS ILUMA clean guide steps', 'Clean IQOS ILUMA guide'),
            0.0001
        );
    }

    public function test_ly_words_do_not_stem_so_near_pairs_can_fall_below_the_guard(): void
    {
        // Documents a real edge: "properly" and "proper" are NOT treated as
        // the same token (only ing/ed/es/s strip), so this reworded pair
        // scores below the 0.6 canonical-guard threshold.
        $score = BlogTopicIdea::similarity(
            'How to clean an IQOS ILUMA properly',
            'Cleaning your IQOS ILUMA the proper way'
        );
        $this->assertLessThan(0.6, $score);
    }

    public function test_ranking_conflict_returns_the_offending_entry_or_null(): void
    {
        $corpus = ['IQOS TEREA Amber Carton', 'TEREA Japan Edition Guide'];

        $this->assertSame(
            'IQOS TEREA Amber Carton',
            BlogTopicIdea::rankingConflict('Amber Carton IQOS TEREA', $corpus)
        );
        $this->assertNull(BlogTopicIdea::rankingConflict('How heated tobacco works', $corpus));
    }

    public function test_search_normalize_lowercases_and_collapses_whitespace(): void
    {
        $this->assertSame('iqos terea amber', ProductSearch::normalize("  IQOS   Terea\tAmber  "));
        $this->assertSame('', ProductSearch::normalize('   '));
    }
}
