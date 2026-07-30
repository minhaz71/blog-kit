<?php

namespace App\Services\Ai;

/**
 * Sample article-brief CSV for the AI Blog Writer — one article per row.
 *
 * Demonstrates every recognized column, including scheduling:
 *  - publish_date only  → published at 00:00 that day;
 *  - publish_date + publish_time → published at that exact time;
 *  - both empty → publishes per the batch settings (immediately, or
 *    staggered by the batch's "delay between articles").
 * Extra columns are passed to the AI verbatim as research context.
 */
class BlogSampleCsv
{
    public const FILENAME = 'sample-blog-articles-terea.csv';

    public static function content(): string
    {
        $rows = [
            ['title', 'keywords', 'country', 'city', 'details', 'angle', 'publish_date', 'publish_time'],
            [
                'How to Spot Genuine TEREA Cartons Before You Pay',
                'genuine terea uae, fake terea sticks, terea authenticity check',
                'United Arab Emirates', 'Dubai',
                'We sell full cartons only: 1 carton = 10 packs = 200 sticks. Buyers check the seal and date code on arrival before paying (cash or card on delivery).',
                'Practical at-the-door checklist an experienced buyer uses, not generic anti-fake advice.',
                '2026-08-01', '',
            ],
            [
                'TEREA Japan vs UAE Edition: What Actually Differs',
                'terea japan vs uae, japan edition terea difference',
                'United Arab Emirates', 'Dubai',
                'Japan editions are exclusive flavors imported by the carton (10 packs, 200 sticks); UAE editions are the officially sold local range. Both genuine, both ILUMA-only.',
                'Honest comparison that helps a buyer decide which edition suits them, with flavor mapping.',
                '2026-08-03', '14:30',
            ],
            [
                'Why Buying TEREA by the Carton Costs Less Per Stick',
                'terea carton price uae, terea bulk price per stick',
                'United Arab Emirates', 'Sharjah',
                'Cartons hold 10 packs (200 sticks). Break down price per stick vs street single-pack sellers; mention 1-hour delivery in Dubai, Sharjah, Ajman and free delivery over 300 AED.',
                'Do the math for the reader with a simple table; no hype, just numbers.',
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
