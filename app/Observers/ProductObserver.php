<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Performance\LiteSpeedPurger;
use App\Services\Seo\SeoAnalyzer;
use Throwable;

class ProductObserver
{
    public function saved(Product $product): void
    {
        // Refresh SEO score against the description + name.
        try {
            app(SeoAnalyzer::class)->analyzeExisting(
                $product,
                content: (string) ($product->description ?? ''),
                h1: (string) $product->name,
            );
        } catch (Throwable) {
            // Never let SEO scoring block a save.
        }

        // Invalidate related cache surfaces on any product change.
        try {
            app(LiteSpeedPurger::class)->purgeProduct($product->slug);
        } catch (Throwable) {
        }

        // Every featured image gets a media record so its alt/title/caption
        // are editable in the Media library + Image SEO tools. Idempotent:
        // one row per path.
        if ($product->featured_image && ! $product->images()->where('path', $product->featured_image)->exists()) {
            try {
                $product->images()->create([
                    'path' => $product->featured_image,
                    'alt' => null,
                    'sort_order' => 0,
                ]);
            } catch (Throwable) {
            }
        }

        // Keep the internal-link index fresh: re-scan JUST this product when
        // its copy or publish state changed (only live products count as
        // link sources; full catalog rebuild stays on the weekly cron).
        if ($product->wasChanged(['description', 'short_description', 'status'])) {
            try {
                app(\App\Services\Seo\InternalLinkScanner::class)->scanSource($product);
            } catch (Throwable) {
            }

            // Fresh link-agent suggestions for the edited content (runs
            // AFTER the scanner so already-linked targets are excluded).
            try {
                app(\App\Services\Seo\LinkSuggestionEngine::class)->scanSource($product);
            } catch (Throwable) {
            }
        }
    }

    public function deleted(Product $product): void
    {
        try {
            app(LiteSpeedPurger::class)->purgeProduct($product->slug);
        } catch (Throwable) {
        }

        // Flag every page that still links to this product as a broken link
        // BEFORE forget() clears the index rows those reports are built from.
        try {
            $scanner = app(\App\Services\Seo\InternalLinkScanner::class);
            $scanner->reportBrokenInbound($product);
            $scanner->forget($product);
        } catch (Throwable) {
        }
    }

    public function restored(Product $product): void
    {
        // Its inbound links work again, and it rejoins the link graph.
        try {
            $scanner = app(\App\Services\Seo\InternalLinkScanner::class);
            $scanner->resolveBrokenTargeting($product);
            $scanner->scanSource($product);
        } catch (Throwable) {
        }
    }
}
