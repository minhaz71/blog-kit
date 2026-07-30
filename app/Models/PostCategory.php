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
