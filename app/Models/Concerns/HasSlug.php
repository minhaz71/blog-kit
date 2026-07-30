<?php

namespace App\Models\Concerns;

use App\Models\SlugHistory;
use Illuminate\Support\Str;

/**
 * Auto-generates a unique slug from the model's name/title and records
 * slug changes in slug_histories so old URLs 301-redirect to the new slug.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (blank($model->slug)) {
                $model->slug = $model->generateUniqueSlug($model->slugSource());
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('slug') && filled($model->getOriginal('slug'))) {
                SlugHistory::create([
                    'sluggable_type' => $model->getMorphClass(),
                    'sluggable_id' => $model->getKey(),
                    'old_slug' => $model->getOriginal('slug'),
                ]);

                // If the model ever re-adopts an old slug, drop the stale history row.
                SlugHistory::where('sluggable_type', $model->getMorphClass())
                    ->where('old_slug', $model->slug)
                    ->delete();
            }
        });
    }

    protected function slugSource(): string
    {
        return $this->name ?? $this->title ?? Str::random(8);
    }

    public function generateUniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        while (static::withoutGlobalScopes()
            ->where('slug', $slug)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function slugHistories()
    {
        return $this->morphMany(SlugHistory::class, 'sluggable');
    }
}
