<?php

namespace App\Models;

use App\Models\Concerns\HasSeoMeta;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;

class PostCategory extends Model
{
    use HasSeoMeta, HasSlug;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Deleting a blog category must NEVER delete its posts. The FK is
        // nullOnDelete, which would leave posts uncategorised — instead we
        // move them onto the default blog category first, so every post
        // keeps a category.
        static::deleting(function (PostCategory $category): void {
            // Only resolve (and possibly auto-create) the default when this
            // category actually has posts to move.
            if (! $category->posts()->exists()) {
                return;
            }

            $target = self::reassignmentTargetExcluding($category->id);

            $category->posts()->update(['post_category_id' => $target?->id]);
        });
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    // ── Hierarchy (mother → sub), mirroring the product Category tree ──

    public function parent()
    {
        return $this->belongsTo(PostCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(PostCategory::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Mother categories only (no parent). */
    public function scopeRoot($q)
    {
        return $q->whereNull('parent_id');
    }

    /** IDs of this category and all descendants (for archive post queries). */
    public function descendantIdsWithSelf(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier) {
            $frontier = PostCategory::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** Breadcrumb trail from the mother down to this category. */
    public function breadcrumbTrail(): array
    {
        $trail = [$this];
        $node = $this;

        while ($node->parent_id && ($node = $node->parent)) {
            array_unshift($trail, $node);
        }

        return $trail;
    }

    /** Public archive URL for this category. */
    public function url(): string
    {
        return route('blog.category', $this->slug);
    }

    /**
     * The blog's fallback category: the admin-chosen default
     * (blog.default_post_category_id) when it still exists, otherwise a
     * find-or-created "Uncategorized".
     */
    public static function defaultCategory(): self
    {
        $id = (int) setting('blog.default_post_category_id');

        if ($id > 0 && ($category = self::find($id))) {
            return $category;
        }

        return self::firstOrCreate(
            ['slug' => 'uncategorized'],
            ['name' => 'Uncategorized'],
        );
    }

    /**
     * Where posts go when $excludeId is being deleted — the default, unless
     * the default IS the one being deleted (then any other category, or null
     * when none exists, which the FK turns into an uncategorised post).
     */
    public static function reassignmentTargetExcluding(int $excludeId): ?self
    {
        $default = self::defaultCategory();

        if ($default->id !== $excludeId) {
            return $default;
        }

        $fallback = self::firstOrCreate(
            ['slug' => 'uncategorized'],
            ['name' => 'Uncategorized'],
        );

        return $fallback->id !== $excludeId
            ? $fallback
            : self::where('id', '!=', $excludeId)->orderBy('id')->first();
    }
}
