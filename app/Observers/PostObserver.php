<?php

namespace App\Observers;

use App\Models\Post;
use App\Services\Performance\LiteSpeedPurger;
use App\Services\Seo\SeoAnalyzer;
use Throwable;

class PostObserver
{
    public function saved(Post $post): void
    {
        try {
            app(SeoAnalyzer::class)->analyzeExisting(
                $post,
                content: (string) ($post->content ?? ''),
                h1: (string) $post->title,
            );
        } catch (Throwable) {
        }

        try {
            app(LiteSpeedPurger::class)->purgeTags(['posts.'.$post->slug, 'blog']);
        } catch (Throwable) {
        }

        // Incremental internal-link index refresh on content edits — also
        // when publish state changes, since only live posts count as sources.
        if ($post->wasChanged(['content', 'status', 'published_at'])) {
            try {
                app(\App\Services\Seo\InternalLinkScanner::class)->scanSource($post);
            } catch (Throwable) {
            }

            try {
                app(\App\Services\Seo\LinkSuggestionEngine::class)->scanSource($post);
            } catch (Throwable) {
            }
        }
    }

    public function deleted(Post $post): void
    {
        // Flag pages still linking to this post as broken BEFORE forget()
        // clears the index rows those reports are built from.
        try {
            $scanner = app(\App\Services\Seo\InternalLinkScanner::class);
            $scanner->reportBrokenInbound($post);
            $scanner->forget($post);
        } catch (Throwable) {
        }
    }

    public function restored(Post $post): void
    {
        try {
            $scanner = app(\App\Services\Seo\InternalLinkScanner::class);
            $scanner->resolveBrokenTargeting($post);
            $scanner->scanSource($post);
        } catch (Throwable) {
        }
    }
}
