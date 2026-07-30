<?php

namespace App\Observers;

use App\Models\SeoMeta;
use App\Services\Seo\SeoAnalyzer;
use Throwable;

/**
 * The SeoMeta row owns its own analysis: whenever it is saved — by the
 * Filament SeoForm relationship, the AI publisher, or the SEO editor — its
 * score is recomputed from its parent. This is why the parent observers
 * only ever UPDATE an existing row: creation belongs to whoever manages the
 * form, and this observer fills in the score after, so the two never race
 * to insert (the bug that tripped the metable unique key).
 */
class SeoMetaObserver
{
    public function saved(SeoMeta $meta): void
    {
        try {
            app(SeoAnalyzer::class)->analyzeMeta($meta);
        } catch (Throwable) {
            // Analysis is a cache of quality signals — never block a save.
        }
    }
}
