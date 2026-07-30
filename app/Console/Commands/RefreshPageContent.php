<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\Ai\ContentReviewer;
use Illuminate\Console\Command;

/**
 * Rewrites the storefront's static page content to a consistent, SEO /
 * E-E-A-T / YMYL-friendly standard. Idempotent: updates content (and FAQs
 * where provided) by slug, never deletes a page. Run again any time to
 * re-apply the canonical copy.
 *
 * House rules enforced mechanically on every page:
 *  - no em/en dashes anywhere (stripEmDashes final pass);
 *  - no duplicate internal links on a page (deduped at build time);
 *  - the <h1> is the page title (template) so content leads with <h2>.
 */
class RefreshPageContent extends Command
{
    protected $signature = 'pages:seo-refresh {--slug= : Refresh only this slug}';

    protected $description = 'Rewrite static page content to the SEO/E-E-A-T standard (idempotent)';

    public function handle(): int
    {
        $pages = PageContent::all();

        $only = $this->option('slug');
        if ($only) {
            $pages = array_filter($pages, fn ($p, $slug) => $slug === $only, ARRAY_FILTER_USE_BOTH);
        }

        $updated = 0;
        foreach ($pages as $slug => $data) {
            $page = Page::where('slug', $slug)->first();
            if (! $page) {
                $this->warn("skip: no page '{$slug}'");

                continue;
            }

            // Safety net: strip any dash that slipped in, and verify no
            // internal URL repeats on the page.
            $content = ContentReviewer::stripEmDashes($this->dedupeLinks($data['content']));

            $page->update(['content' => $content]);

            if (! empty($data['faqs'])) {
                $page->allFaqs()->delete();
                foreach (array_values($data['faqs']) as $i => $faq) {
                    $page->allFaqs()->create([
                        'question' => ContentReviewer::stripEmDashes($faq['q']),
                        'answer' => ContentReviewer::stripEmDashes($faq['a']),
                        'sort_order' => $i,
                        'is_active' => true,
                    ]);
                }
            }

            $this->info("updated: {$slug}");
            $updated++;
        }

        // Bust the guest page cache so visitors see the new copy immediately.
        \App\Services\Performance\PageCache::flush();

        $this->info("Done. {$updated} page(s) refreshed.");

        return self::SUCCESS;
    }

    /**
     * Keep the FIRST occurrence of each internal href, unwrap later
     * duplicates to plain text (duplicate links to one URL dilute anchors).
     */
    protected function dedupeLinks(string $html): string
    {
        $seen = [];

        return (string) preg_replace_callback(
            '~<a\s[^>]*?href="([^"]+)"[^>]*>(.*?)</a>~is',
            function (array $m) use (&$seen): string {
                $href = $m[1];
                // Only dedupe internal links; external links may legitimately repeat.
                $isInternal = str_starts_with($href, '/') || str_contains($href, 'terea') || str_starts_with($href, '#');
                if ($isInternal && isset($seen[$href])) {
                    return $m[2];
                }
                $seen[$href] = true;

                return $m[0];
            },
            $html
        );
    }
}
