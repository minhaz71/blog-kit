<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Renders a clean, token-lean Markdown representation of a storefront entity
 * for AI answer engines — served via content negotiation (Accept: text/markdown)
 * and `.md` URLs. Built from STRUCTURED fields (facts, specs, price, FAQs) with
 * the body HTML converted to markdown, not a dump of the full page HTML: the
 * point is ~80% fewer tokens and easier, more accurate citation.
 *
 * Cached on the same version key as the sitemap/llms.txt, so any content change
 * regenerates it automatically.
 */
class MarkdownRenderer
{
    public const CACHE_TTL = 3600;

    /** Markdown for any supported model, cached; null when unsupported. */
    public function render(Model $model): ?string
    {
        $type = match (true) {
            $model instanceof Product => 'product',
            $model instanceof Category => 'category',
            $model instanceof Post => 'post',
            $model instanceof Page => 'page',
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $version = (int) Cache::get('sitemap.version', 1);

        return Cache::remember("md.{$type}.{$model->getKey()}.v{$version}", self::CACHE_TTL, function () use ($model, $type) {
            return match ($type) {
                'product' => $this->forProduct($model),
                'category' => $this->forCategory($model),
                'post' => $this->forPost($model),
                'page' => $this->forPage($model),
            };
        });
    }

    protected function forProduct(Product $product): string
    {
        $store = (string) setting('general.site_name', config('app.name'));
        $lines = ["# {$product->name}", ''];

        $short = $this->clean((string) $product->short_description);
        if ($short !== '') {
            $lines[] = '> '.$short;
            $lines[] = '';
        }

        // Key facts up top — the details an answer engine should quote.
        $price = $product->currentPrice();
        $facts = array_filter([
            $product->brand?->name ? 'Brand: '.$product->brand->name : null,
            $product->sku ? 'SKU: '.$product->sku : null,
            $price > 0 ? 'Price: '.store_currency().' '.number_format($price, 2)
                .($product->isOnSale() ? ' (on sale from '.store_currency().' '.number_format((float) $product->price, 2).')' : '') : null,
            'Availability: '.($product->inStock() ? 'In stock' : 'Out of stock'),
            $product->categories->isNotEmpty() ? 'Categories: '.$product->categories->pluck('name')->implode(', ') : null,
        ]);
        foreach ($facts as $fact) {
            $lines[] = '- '.$fact;
        }
        $lines[] = '';

        // Specifications (mirrors the on-page spec table + additionalProperty schema).
        $specs = $product->attributeFacts();
        if ($specs !== []) {
            $lines[] = '## Specifications';
            $lines[] = '';
            foreach ($specs as $label => $value) {
                $lines[] = "- **{$label}:** {$value}";
            }
            $lines[] = '';
        }

        $body = $this->htmlToMarkdown((string) $product->description);
        if ($body !== '') {
            $lines[] = '## Description';
            $lines[] = '';
            $lines[] = $body;
            $lines[] = '';
        }

        $this->appendFaqs($lines, $product);
        $this->appendFooter($lines, $product->url(), $store);

        return $this->finish($lines);
    }

    protected function forCategory(Category $category): string
    {
        $store = (string) setting('general.site_name', config('app.name'));
        $lines = ["# {$category->name}", ''];

        $desc = $this->clean((string) $category->description);
        if ($desc !== '') {
            $lines[] = '> '.$desc;
            $lines[] = '';
        }

        $body = $this->htmlToMarkdown((string) $category->content_block);
        if ($body !== '') {
            $lines[] = $body;
            $lines[] = '';
        }

        // A few representative products in this category.
        $products = $category->products()->where('products.status', 'published')->limit(20)->get(['products.id', 'products.name', 'products.slug']);
        if ($products->isNotEmpty()) {
            $lines[] = '## Products in this category';
            $lines[] = '';
            foreach ($products as $product) {
                $lines[] = "- [{$product->name}]({$product->url()})";
            }
            $lines[] = '';
        }

        $this->appendFaqs($lines, $category);
        $this->appendFooter($lines, $category->url(), $store);

        return $this->finish($lines);
    }

    protected function forPost(Post $post): string
    {
        $store = (string) setting('general.site_name', config('app.name'));
        $lines = ["# {$post->title}", ''];

        if ($post->published_at) {
            $lines[] = '*Published '.$post->published_at->toFormattedDateString()
                .($post->updated_at && $post->updated_at->gt($post->published_at) ? ', updated '.$post->updated_at->toFormattedDateString() : '').'*';
            $lines[] = '';
        }

        $excerpt = $this->clean((string) $post->excerpt);
        if ($excerpt !== '') {
            $lines[] = '> '.$excerpt;
            $lines[] = '';
        }

        $body = $this->htmlToMarkdown((string) $post->content);
        if ($body !== '') {
            $lines[] = $body;
            $lines[] = '';
        }

        $this->appendFaqs($lines, $post);
        $this->appendFooter($lines, $post->url(), $store);

        return $this->finish($lines);
    }

    protected function forPage(Page $page): string
    {
        $store = (string) setting('general.site_name', config('app.name'));
        $lines = ["# {$page->title}", ''];

        $body = $this->htmlToMarkdown((string) $page->content);
        if ($body !== '') {
            $lines[] = $body;
            $lines[] = '';
        }

        $this->appendFooter($lines, $page->url(), $store);

        return $this->finish($lines);
    }

    /** Append the model's FAQs as an H2 + Q/A blocks, if any. */
    protected function appendFaqs(array &$lines, Model $model): void
    {
        if (! method_exists($model, 'faqs')) {
            return;
        }

        $faqs = $model->faqs()->get();
        if ($faqs->isEmpty()) {
            return;
        }

        $lines[] = '## Frequently asked questions';
        $lines[] = '';
        foreach ($faqs as $faq) {
            $lines[] = '### '.$this->clean((string) $faq->question);
            $lines[] = '';
            $lines[] = $this->htmlToMarkdown((string) $faq->answer) ?: $this->clean((string) $faq->answer);
            $lines[] = '';
        }
    }

    protected function appendFooter(array &$lines, string $canonical, string $store): void
    {
        $lines[] = '---';
        $lines[] = "Source: [{$canonical}]({$canonical}) — {$store}.";
    }

    /** Tags → spaces → decode entities → collapse whitespace, one line. */
    protected function clean(string $html): string
    {
        // Replace tags with a space (not nothing) so adjacent blocks don't run
        // together — "…exhale.</p><p>Flavor:…" must become "…exhale. Flavor:…".
        $text = preg_replace('/<[^>]+>/', ' ', $html);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) Str::of($text)->replace("\u{00a0}", ' ')->squish();
    }

    /**
     * Compact HTML → Markdown for the rich-editor content the AI writers
     * produce (headings, paragraphs, lists, links, emphasis, quotes). Not a
     * general converter — deliberately small, with a plain-text fallback for
     * anything it doesn't recognise.
     */
    protected function htmlToMarkdown(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Normalise line breaks and block boundaries.
        $html = preg_replace('/\r\n?/', "\n", $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);

        // Headings (h2–h6 → ##..###### ; keep on-page H1 as the page title).
        $html = preg_replace_callback('#<h([2-6])[^>]*>(.*?)</h\1>#is', function ($m) {
            return "\n\n".str_repeat('#', (int) $m[1]).' '.trim(strip_tags($m[2]))."\n\n";
        }, $html);

        // Links, emphasis.
        $html = preg_replace('#<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '[$2]($1)', $html);
        $html = preg_replace('#<(strong|b)[^>]*>(.*?)</\1>#is', '**$2**', $html);
        $html = preg_replace('#<(em|i)[^>]*>(.*?)</\1>#is', '*$2*', $html);

        // List items → "- " (ordered lists collapse to bullets; fine for LLMs).
        $html = preg_replace('#<li[^>]*>(.*?)</li>#is', "- $1\n", $html);

        // Blockquotes.
        $html = preg_replace_callback('#<blockquote[^>]*>(.*?)</blockquote>#is', function ($m) {
            return "\n> ".trim(strip_tags($m[1]))."\n\n";
        }, $html);

        // Paragraphs / divs → block separation.
        $html = preg_replace('#</(p|div|ul|ol)>#i', "\n\n", $html);
        $html = preg_replace('#<(p|div|ul|ol)[^>]*>#i', '', $html);

        // Anything left, drop; decode entities.
        $text = html_entity_decode(strip_tags($html, '<a>'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text); // links already converted to markdown syntax above

        // Collapse 3+ blank lines, trim trailing spaces per line.
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    protected function finish(array $lines): string
    {
        return preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines))."\n";
    }
}
