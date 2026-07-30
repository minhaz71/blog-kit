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
        return view('blog.index', [
            'posts' => Post::published()->with(['author', 'category'])->latest('published_at')->paginate(12),
            'categories' => PostCategory::has('posts')->get(),
            'heading' => 'Blog',
            'seo' => $seo->forUtility('Blog', noindex: false),
        ]);
    }

    public function category(PostCategory $postCategory, SeoManager $seo)
    {
        return view('blog.index', [
            'posts' => $postCategory->posts()->published()->with(['author', 'category'])->latest('published_at')->paginate(12),
            'categories' => PostCategory::has('posts')->get(),
            'heading' => $postCategory->name,
            'seo' => $seo->forUtility($postCategory->name.' — Blog', noindex: false),
        ]);
    }

    public function author(User $author, SeoManager $seo)
    {
        $posts = $author->posts()->published()->with(['author', 'category'])->latest('published_at')->paginate(12);

        abort_if($posts->isEmpty() && ! $author->posts()->exists(), 404);

        return view('blog.index', [
            'posts' => $posts,
            'categories' => PostCategory::has('posts')->get(),
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
