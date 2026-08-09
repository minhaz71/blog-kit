<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\Seo\SeoManager;

class BlogController extends Controller
{
    public function index(SeoManager $seo)
    {
        $page = max(1, (int) request('page', 1));

        return view('blog.index', [
            'posts' => Post::published()->with(['author', 'category'])->latest('published_at')->paginate(12),
            'categories' => $this->navCategories(),
            'heading' => 'Blog',
            'seo' => $seo->forUtility(
                'Blog'.($page > 1 ? " — Page {$page}" : ''),
                noindex: false,
                canonical: $seo->paginatedCanonical($page),
            ),
        ]);
    }

    public function category(PostCategory $postCategory, SeoManager $seo)
    {
        // A mother category aggregates posts from all its sub-categories; a sub
        // shows just its own. descendantIdsWithSelf() handles both.
        $ids = $postCategory->descendantIdsWithSelf();

        // Sub-category chips: the children of this category's mother (so
        // siblings stay visible when browsing a sub), only those with posts.
        $mother = $postCategory->parent_id ? ($postCategory->parent ?? $postCategory) : $postCategory;
        $subcategories = $mother->children()->active()->where('show_in_menu', true)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn (PostCategory $c) => Post::published()->whereIn('post_category_id', $c->descendantIdsWithSelf())->exists())
            ->values();

        return view('blog.index', [
            'posts' => Post::published()->whereIn('post_category_id', $ids)->with(['author', 'category'])->latest('published_at')->paginate(12),
            'categories' => $this->navCategories(),
            'subcategories' => $subcategories,
            'activeCategory' => $postCategory,
            'heading' => $postCategory->name,
            'seo' => $seo->forUtility(
                $postCategory->name.' — Blog'.(($p = max(1, (int) request('page', 1))) > 1 ? " — Page {$p}" : ''),
                noindex: false,
                canonical: $seo->paginatedCanonical($p),
            ),
        ]);
    }

    /**
     * Top-level (mother) categories that have at least one published post
     * anywhere in their subtree — the primary blog filter. A flat, pre-
     * hierarchy install has every category at root level, so this is
     * backward-compatible.
     */
    protected function navCategories()
    {
        return PostCategory::root()->active()->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn (PostCategory $c) => Post::published()->whereIn('post_category_id', $c->descendantIdsWithSelf())->exists())
            ->values();
    }

    public function author(User $author, SeoManager $seo)
    {
        $posts = $author->posts()->published()->with(['author', 'category'])->latest('published_at')->paginate(12);

        abort_if($posts->isEmpty() && ! $author->posts()->exists(), 404);

        return view('blog.index', [
            'posts' => $posts,
            'categories' => $this->navCategories(),
            'heading' => 'Posts by '.$author->publicName(),
            'author' => $author,
            'seo' => $seo->forAuthor($author),
        ]);
    }

    public function show(Post $post, SeoManager $seo)
    {
        $isPublic = $post->status === 'published' && $post->published_at?->isPast();
        $isPreview = ! $isPublic && (request()->user()?->can('manage content') ?? false);

        abort_unless($isPublic || $isPreview, 404);

        $post->load(['author', 'category', 'tags', 'faqs', 'seoMeta']);

        return view('blog.show', [
            'isPreview' => $isPreview,
            'post' => $post,
            'toc' => $post->show_toc ? $this->buildToc($post->content ?? '') : [],
            'related' => Post::published()
                ->whereKeyNot($post->id)
                ->when($post->post_category_id, fn ($q) => $q->where('post_category_id', $post->post_category_id))
                ->latest('published_at')
                ->take(3)
                ->get(),
            'seo' => $seo->forPost($post),
        ]);
    }

    /** Extract H2/H3 headings for the table of contents. */
    protected function buildToc(string $content): array
    {
        preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>/i', $content, $matches, PREG_SET_ORDER);

        return collect($matches)->map(fn ($m) => [
            'level' => (int) $m[1],
            'text' => strip_tags($m[2]),
            'anchor' => str(strip_tags($m[2]))->slug()->toString(),
        ])->all();
    }
}
