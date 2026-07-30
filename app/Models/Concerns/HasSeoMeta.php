<?php

namespace App\Models\Concerns;

use App\Models\SeoMeta;

trait HasSeoMeta
{
    public static function bootHasSeoMeta(): void
    {
        static::deleting(function ($model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->seoMeta()->delete();
            }
        });
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }

    public function getOrCreateSeoMeta(): SeoMeta
    {
        // firstOrCreate (not create): race-safe against a concurrent creator
        // and against a stale/unloaded relationship, so it can never insert a
        // second row and trip the (metable_type, metable_id) unique key.
        return $this->seoMeta ?? $this->seoMeta()->firstOrCreate([]);
    }

    /** Resolved meta title with fallback to the model's own name/title. */
    public function seoTitle(): string
    {
        return $this->seoMeta?->title
            ?: ($this->name ?? $this->title ?? '');
    }

    public function seoDescription(): ?string
    {
        return $this->seoMeta?->description
            ?: str(strip_tags($this->short_description ?? $this->excerpt ?? $this->description ?? ''))->limit(157)->toString() ?: null;
    }
}
