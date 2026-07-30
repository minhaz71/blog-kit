<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Performance\LiteSpeedPurger;
use App\Services\Seo\InternalLinkScanner;
use App\Services\Seo\SeoAnalyzer;
use Throwable;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        try {
            app(SeoAnalyzer::class)->analyzeExisting(
                $category,
                content: (string) ($category->description ?? '').' '.(string) ($category->content_block ?? ''),
                h1: (string) $category->name,
            );
        } catch (Throwable) {
        }

        // Re-index this category's editorial links for the internal-links
        // report — but only when a link-bearing field (or its live status)
        // actually changed, so a sort-order tweak doesn't rescan.
        try {
            if ($category->wasRecentlyCreated
                || $category->wasChanged(['description', 'content_block', 'custom_html', 'is_active', 'slug'])) {
                app(InternalLinkScanner::class)->scanSource($category);
            }
        } catch (Throwable) {
        }

        try {
            app(LiteSpeedPurger::class)->purgeCategory($category->slug);
        } catch (Throwable) {
        }
    }

    public function deleted(Category $category): void
    {
        // Flag every live page that still links here, then drop this
        // category's index rows (both directions).
        try {
            $scanner = app(InternalLinkScanner::class);
            $scanner->reportBrokenInbound($category);
            $scanner->forget($category);
        } catch (Throwable) {
        }

        try {
            app(LiteSpeedPurger::class)->purgeCategory($category->slug);
        } catch (Throwable) {
        }
    }
}
