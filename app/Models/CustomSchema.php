<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CustomSchema extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['json_ld' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('custom-schemas:global'));
        static::deleted(fn () => Cache::forget('custom-schemas:global'));
    }

    public function schemable()
    {
        return $this->morphTo();
    }

    /** Global blocks (every page) — cached; they change rarely. */
    public static function globalBlocks(): array
    {
        return Cache::rememberForever('custom-schemas:global', fn () => static::query()
            ->where('is_active', true)
            ->whereNull('schemable_type')
            ->pluck('json_ld')
            ->all());
    }

    /** Blocks attached to one specific model (product, category, post, page). */
    public static function forModel(Model $model): array
    {
        return static::query()
            ->where('is_active', true)
            ->where('schemable_type', $model->getMorphClass())
            ->where('schemable_id', $model->getKey())
            ->pluck('json_ld')
            ->all();
    }
}
