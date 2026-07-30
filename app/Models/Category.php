<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSeoMeta;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, HasFaqs, HasSeoMeta, HasSlug, \App\Models\Concerns\MovesInlineStylesToCustomCss;

    protected $guarded = [];

    protected function styleExtractionColumns(): array
    {
        return ['content_block'];
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Deleting a category must NEVER lose products. Before the pivot
        // rows cascade away, move any product that would be left with no
        // category at all onto the default ("Uncategorized" unless the
        // admin picked one). Products that also sit in other categories
        // simply lose this one — they stay categorised.
        static::deleting(function (Category $category): void {
            $orphanIds = $category->products()
                ->whereDoesntHave('categories', fn ($q) => $q->whereKeyNot($category->id))
                ->pluck('products.id');

            // Resolve (and only then possibly auto-create) the default just
            // when there is actually a product that would be orphaned.
            if ($orphanIds->isEmpty() || ! ($target = self::reassignmentTargetExcluding($category->id))) {
                return;
            }

            $orphanIds->each(fn ($id) => $target->products()->syncWithoutDetaching([$id]));
        });
    }

    /**
     * The catalog's fallback category: the admin-chosen default
     * (catalog.default_category_id) when it still exists, otherwise a
     * find-or-created "Uncategorized". Always returns a real category so
     * nothing is ever left uncategorised.
     */
    public static function defaultCategory(): self
    {
        $id = (int) setting('catalog.default_category_id');

        if ($id > 0 && ($category = self::find($id))) {
            return $category;
        }

        return self::firstOrCreate(
            ['slug' => 'uncategorized'],
            ['name' => 'Uncategorized', 'is_active' => true],
        );
    }

    /**
     * Where orphaned products go when $excludeId is being deleted — the
     * default, unless the default IS the one being deleted (then any other
     * category, or null when none exists).
     */
    public static function reassignmentTargetExcluding(int $excludeId): ?self
    {
        $default = self::defaultCategory();

        if ($default->id !== $excludeId) {
            return $default;
        }

        $fallback = self::firstOrCreate(
            ['slug' => 'uncategorized'],
            ['name' => 'Uncategorized', 'is_active' => true],
        );

        return $fallback->id !== $excludeId
            ? $fallback
            : self::where('id', '!=', $excludeId)->orderBy('id')->first();
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeRoot($q)
    {
        return $q->whereNull('parent_id');
    }

    /** IDs of this category and all descendants (for product queries). */
    public function descendantIdsWithSelf(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier) {
            $frontier = Category::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** Breadcrumb trail from root to this category. */
    public function breadcrumbTrail(): array
    {
        $trail = [$this];
        $node = $this;

        while ($node->parent_id && ($node = $node->parent)) {
            array_unshift($trail, $node);
        }

        return $trail;
    }

    public function url(): string
    {
        return \App\Support\Permalinks::category($this->slug);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.ltrim($this->image, '/')) : null;
    }
}
