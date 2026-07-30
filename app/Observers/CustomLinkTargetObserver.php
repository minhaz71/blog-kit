<?php

namespace App\Observers;

use App\Models\CustomLinkTarget;
use App\Models\InternalLink;
use App\Models\LinkSuggestion;
use App\Services\Seo\LinkDictionary;
use App\Services\Seo\LinkSuggestionEngine;
use App\Support\BackgroundProcess;
use Throwable;

/**
 * Keeps the link agent in sync with admin-defined targets.
 *
 * A custom target is a link DESTINATION, not a page. Unlike a product or
 * post (whose own save re-scans just that page), adding or editing a custom
 * target changes which phrases are linkable across EVERY source page — so
 * the whole phrase dictionary must be rebuilt and all sources re-scanned
 * before its anchors can turn into suggestions. Without this the target sat
 * idle until someone manually pressed "Rebuild" on the Link Agent page.
 */
class CustomLinkTargetObserver
{
    public function saved(CustomLinkTarget $target): void
    {
        $this->refreshLinkAgent();
    }

    public function deleted(CustomLinkTarget $target): void
    {
        // Clear the rows this target owns on both sides (the scanners' stale
        // sweeps only cover product/post/category targets, not custom ones).
        try {
            InternalLink::where('target_type', CustomLinkTarget::class)->where('target_id', $target->id)->delete();
            LinkSuggestion::where('target_type', CustomLinkTarget::class)->where('target_id', $target->id)->delete();
        } catch (Throwable) {
        }

        $this->refreshLinkAgent();
    }

    /**
     * Rebuild the dictionary + regenerate suggestions. Runs detached so the
     * admin save returns immediately; falls back to an inline rebuild when
     * the host can't spawn a background process (e.g. proc_open disabled).
     */
    protected function refreshLinkAgent(): void
    {
        if (BackgroundProcess::artisan(['seo:suggest-links'])) {
            return;
        }

        try {
            @set_time_limit(300);
            app(LinkDictionary::class)->rebuild();
            app(LinkSuggestionEngine::class)->scanAll();
        } catch (Throwable) {
        }
    }
}
