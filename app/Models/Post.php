<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\MovesInlineStylesToCustomCss;
use App\Models\Concerns\HasSeoMeta;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, HasFaqs, HasSeoMeta, HasSlug, MovesInlineStylesToCustomCss, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'show_toc' => 'boolean',
            'compared_product_ids' => 'array',
        ];
    }

    protected function slugSource(): string
    {
        return $this->title;
    }

    /**
     * The products a comparison article reviews, in stored order — empty
     * collection for normal posts. Backs both the visible compared-products
     * box and the ItemList schema, so page text and structured data agree.
     */
    public function comparedProducts()
    {
        $ids = array_values(array_map('intval', (array) $this->compared_product_ids));

        if ($ids === []) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('status', 'published')
            ->with(['brand', 'images'])
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids, true))
            ->values();
    }

    /** The canonical topic cluster this post belongs to (pillar + spokes). */
    public function cluster()
    {
        return $this->belongsTo(ContentCluster::class, 'content_cluster_id');
    }

    /** The pillar/hub post a spoke supports (null for a pillar or an unplanned post). */
    public function pillarPost()
    {
        return $this->belongsTo(Post::class, 'pillar_post_id');
    }

    /** Sibling spokes in the same cluster (excludes this post). */
    public function clusterSpokes()
    {
        if (! $this->content_cluster_id) {
            return Post::query()->whereRaw('1 = 0'); // no cluster → no siblings
        }

        return Post::query()
            ->where('content_cluster_id', $this->content_cluster_id)
            ->where('status', 'published')
            ->whereKeyNot($this->getKey());
    }

    public function isPillar(): bool
    {
        return $this->content_role === 'pillar';
    }

    /** Keep at most this many revisions per post (WordPress-style history). */
    public const MAX_REVISIONS = 30;

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            $words = str_word_count(strip_tags($post->content ?? ''));
            $post->reading_time = max(1, (int) ceil($words / 200));
        });

        // Revision trail: when the written content changes, snapshot the
        // version being replaced, stamped with WHO replaced it (null = the
        // AI writer or a console process). Runs here, not in the observer,
        // so every code path (admin, AI publisher, tinker) is captured.
        static::updating(function (Post $post): void {
            if (! $post->isDirty(['title', 'excerpt', 'content'])) {
                return;
            }

            $post->last_edited_by = auth()->id();

            $post->revisions()->create([
                'user_id' => auth()->id(),
                'title' => (string) $post->getOriginal('title'),
                'excerpt' => $post->getOriginal('excerpt'),
                'content' => $post->getOriginal('content'),
                'created_at' => now(),
            ]);

            // Bound the trail so a busy post can't grow the table forever.
            $post->revisions()
                ->orderByDesc('created_at')->orderByDesc('id')
                ->skip(self::MAX_REVISIONS)->take(PHP_INT_MAX)
                ->pluck('id')
                ->whenNotEmpty(fn ($ids) => PostRevision::whereIn('id', $ids)->delete());
        });

        static::creating(function (Post $post): void {
            $post->last_edited_by ??= auth()->id();
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastEditor()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function revisions()
    {
        return $this->hasMany(PostRevision::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Roll the post back to a revision (the current version is snapshotted first by updating()). */
    public function restoreRevision(PostRevision $revision): void
    {
        $this->update([
            'title' => $revision->title,
            'excerpt' => $revision->excerpt,
            'content' => $revision->content,
        ]);
    }

    public function category()
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }

    public function featuredImageUrl(): ?string
    {
        return $this->featured_image ? asset('storage/'.ltrim($this->featured_image, '/')) : null;
    }
}
