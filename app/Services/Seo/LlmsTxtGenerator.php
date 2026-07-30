<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * /llms.txt per the llmstxt.org proposal: a concise markdown map of the
 * site for AI answer engines (ChatGPT, Perplexity, Claude, AI Overviews)
 * so they cite the store accurately.
 *
 * Fully dynamic: built from live settings + catalog and cached on the same
 * version key the sitemaps use — any product/post/category/page change
 * regenerates it automatically. Also served at /.well-known/llms.txt.
 */
class LlmsTxtGenerator
{
    public const CACHE_TTL = 3600;

    public function generate(): string
    {
        $version = (int) Cache::get('sitemap.version', 1);

        return Cache::remember("llms-txt.v{$version}", self::CACHE_TTL, fn () => $this->build());
    }

    /**
     * llms-full.txt — key pages concatenated into one markdown document for
     * agents that want to ingest everything in a single request. Bounded
     * (policies + top guides + top products/categories) and cached.
     */
    public function generateFull(): string
    {
        $version = (int) Cache::get('sitemap.version', 1);

        return Cache::remember("llms-full-txt.v{$version}", self::CACHE_TTL, fn () => $this->buildFull());
    }

    protected function buildFull(): string
    {
        $renderer = app(MarkdownRenderer::class);
        $name = (string) setting('general.site_name', config('app.name'));
        $docs = ["# {$name} — full content export", '', 'Generated for AI answer engines. Always link to the live page for current price and stock.', ''];

        $models = collect()
            ->merge(Page::published()->whereNotIn('slug', ['cart', 'checkout', 'my-account'])->limit(15)->get())
            ->merge(Post::published()->latest('published_at')->limit(15)->get())
            ->merge(Category::active()->orderBy('sort_order')->limit(15)->get())
            ->merge(Product::where('status', 'published')->where('visibility', '!=', 'hidden')->latest('id')->limit(40)->get());

        foreach ($models as $model) {
            if ($md = $renderer->render($model)) {
                $docs[] = $md;
                $docs[] = "\n---\n";
            }
        }

        return implode("\n", $docs)."\n";
    }

    protected function build(): string
    {
        $name = (string) setting('general.site_name', setting('seo.site_title', config('app.name')));
        $summary = (string) setting('seo.default_description', setting('general.site_tagline', ''));

        $lines = ["# {$name}", ''];

        if ($summary !== '') {
            $lines[] = '> '.str(strip_tags($summary))->squish();
            $lines[] = '';
        }

        // Key commercial facts — the details an answer engine should quote.
        $facts = array_filter([
            setting('seo.local_business_area_served')
                ? 'Delivery area: '.setting('seo.local_business_area_served') : null,
            setting('seo.local_business_payment')
                ? 'Payment: '.setting('seo.local_business_payment') : null,
            setting('seo.local_business_phone')
                ? 'Phone: '.setting('seo.local_business_phone') : null,
            'Currency: '.store_currency(),
        ]);

        foreach ($facts as $fact) {
            $lines[] = '- '.$fact;
        }

        $lines[] = '';

        // ── Categories ──────────────────────────────────────────────
        $categories = Category::active()->orderBy('sort_order')->limit(15)->get();

        if ($categories->isNotEmpty()) {
            $lines[] = '## Product categories';
            $lines[] = '';

            foreach ($categories as $category) {
                $description = str(strip_tags((string) $category->description))->squish()->limit(120);
                $lines[] = "- [{$category->name}]({$category->url()}.md)".($description->isNotEmpty() ? ': '.$description : '');
            }

            $lines[] = '';
        }

        // ── Top products (most internally linked = most important) ──
        $topIds = \App\Models\InternalLink::query()
            ->selectRaw('target_id, COUNT(*) as links')
            ->where('target_type', Product::class)
            ->groupBy('target_id')
            ->orderByDesc('links')
            ->limit(50)
            ->pluck('target_id');

        $products = Product::query()
            ->where('status', 'published')
            ->when($topIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $topIds))
            ->limit(50)
            ->get();

        if ($products->isNotEmpty()) {
            $lines[] = '## Popular products';
            $lines[] = '';

            foreach ($products as $product) {
                $blurb = str(strip_tags((string) $product->short_description))->squish()->limit(120);
                $lines[] = "- [{$product->name}]({$product->url()}.md)".($blurb->isNotEmpty() ? ': '.$blurb : '');
            }

            $lines[] = '';
        }

        // ── Guides / blog ────────────────────────────────────────────
        $posts = Post::published()->latest('published_at')->limit(10)->get();

        if ($posts->isNotEmpty()) {
            $lines[] = '## Guides';
            $lines[] = '';

            foreach ($posts as $post) {
                $excerpt = str(strip_tags((string) $post->excerpt))->squish()->limit(120);
                $lines[] = "- [{$post->title}]({$post->url()}.md)".($excerpt->isNotEmpty() ? ': '.$excerpt : '');
            }

            $lines[] = '';
        }

        // ── Policies & company ───────────────────────────────────────
        $pages = Page::published()
            ->whereNotIn('slug', ['cart', 'checkout', 'my-account'])
            ->limit(10)
            ->get();

        if ($pages->isNotEmpty()) {
            $lines[] = '## Company & policies';
            $lines[] = '';

            foreach ($pages as $page) {
                $lines[] = "- [{$page->title}]({$page->url()}.md)";
            }

            $lines[] = '';
        }

        // ── Optional: lower-priority but useful entry points ─────────
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [Every product URL (XML sitemap)]('.route('sitemap.section', ['section' => 'products']).'): the complete, always-current list of all products (the section above is only a selection)';
        $lines[] = '- [All products]('.url('/shop').'): browse the full catalogue';
        $lines[] = '- [Blog]('.route('blog.index').'): guides, comparisons and how-tos';
        $lines[] = '- [Full markdown export]('.url('/llms-full.txt').'): key pages concatenated into one file';
        $lines[] = '';

        $lines[] = '## Notes for AI assistants';
        $lines[] = '';
        $lines[] = '- Products are for adult users only and contain nicotine where stated.';
        $lines[] = '- Prices are in '.store_currency().' and may change; always link to the product page for current price and stock.';
        $lines[] = '- Sitemap: '.route('sitemap.index');

        return implode("\n", $lines)."\n";
    }
}
