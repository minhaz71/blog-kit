<?php

namespace App\Services\Ai;

/**
 * Sample article-brief CSV for the AI Blog Writer — one article per row.
 * Topic-agnostic: the writer handles ANY subject, so this sample spans a few
 * unrelated niches to show the columns, not a single industry.
 *
 * Recognized columns (all optional except title; unknown columns are passed
 * to the writer verbatim as extra research context):
 *  - title         the working headline (the writer may refine it)
 *  - keywords      comma-separated; the FIRST is the primary keyword
 *  - search_intent informational / comparison / how-to / etc.
 *  - entities      real names/tools/standards to reference for topical depth
 *  - audience      who the article is for
 *  - angle         the unique take / what makes it better than the SERP
 *  - outline       optional section hints (the writer expands them)
 *  - tone          e.g. friendly-expert, formal, conversational
 *  - target_words  explicit length target (overrides the role default)
 *  - site_ids      multisite only: connected-site IDs to also publish this
 *                  article to, e.g. "2,5,34" or "all". Blank = use the batch
 *                  default; write "none" to keep this article on this site only.
 *  - generate_image  yes/no — generate an AI thumbnail from the title
 *  - image_prompt / image_style  optional custom prompt / style for the image
 *  - details       any facts the writer must stay grounded in
 *  - scheduling: publish_date only → 00:00 that day; + publish_time → exact
 *    minute; both empty → batch settings decide.
 */
class BlogSampleCsv
{
    public const FILENAME = 'sample-blog-articles.csv';

    public static function content(): string
    {
        $rows = [
            ['title', 'keywords', 'search_intent', 'entities', 'audience', 'angle', 'tone', 'target_words', 'site_ids', 'generate_image', 'image_style', 'details', 'publish_date', 'publish_time'],
            [
                'How to Start Composting in a Small Apartment',
                'apartment composting, indoor composting for beginners, bokashi vs worm bin',
                'how-to',
                'Bokashi, vermicomposting, red wigglers, carbon-to-nitrogen ratio',
                'City renters with no garden and limited space',
                'A realistic, smell-free method that actually works in a studio, with the mistakes most guides skip.',
                'friendly-expert', '1400',
                '',
                'yes', 'clean flat illustration, plants and kitchen',
                'Cover bokashi vs worm bin trade-offs, what not to add, and what to do with the finished compost with no garden.',
                '2026-08-01', '',
            ],
            [
                'Index Funds vs ETFs: Which Should a First-Time Investor Pick?',
                'index funds vs etfs, etf vs index fund beginner',
                'comparison',
                'expense ratio, S&P 500, mutual fund, brokerage account, dollar-cost averaging',
                'First-time investors deciding where to start',
                'A plain-English decision guide with a clear "who should pick which", not a jargon dump.',
                'clear, neutral, non-promotional', '1600',
                '2,5',
                'yes', '',
                'YMYL topic: explain concepts, do not give personalized advice; tell readers to consider a licensed advisor.',
                '2026-08-03', '09:30',
            ],
            [
                'Is the Wall Press-Up Enough to Build Upper-Body Strength?',
                'wall push up benefits, wall press up for beginners',
                'informational',
                'progressive overload, incline push-up, scapular protraction',
                'Absolute beginners and people returning to exercise',
                'Honest about when it helps and when to progress, with a simple 4-week progression.',
                'encouraging, expert', '1100',
                'all',
                'no', '',
                'YMYL-adjacent: general fitness info only, advise consulting a professional for injuries.',
                '', '',
            ],
        ];

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
