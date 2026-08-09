<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;

/** Builds the complete SeoData payload for each page type. */
class SeoManager
{
    public function __construct(protected SchemaGenerator $schema) {}

    protected function base(): array
    {
        return [
            $this->schema->organization(),
            $this->schema->website(),
            $this->schema->localBusiness(),
            ...$this->schema->localBusinessLocations(),
            ...$this->customGlobal(),
        ];
    }

    /** Owner-defined global JSON-LD blocks (every page). */
    protected function customGlobal(): array
    {
        return array_map([$this, 'stripContext'], \App\Models\CustomSchema::globalBlocks());
    }

    /**
     * Owner-defined JSON-LD blocks attached to one page (unlimited per
     * page). A block's own @context is stripped — the @graph wrapper
     * provides it once for the whole document.
     */
    protected function customFor(\Illuminate\Database\Eloquent\Model $model): array
    {
        return array_map([$this, 'stripContext'], \App\Models\CustomSchema::forModel($model));
    }

    protected function stripContext(array $block): array
    {
        return collect($block)->except('@context')->all();
    }

    public function forHome(): SeoData
    {
        // A homepage-specific social image, if set; otherwise SeoData falls
        // back to the global seo.default_og_image on its own.
        $ogImage = ($img = trim((string) setting('seo.homepage_og_image', '')))
            ? asset('storage/'.$img)
            : null;

        return new SeoData(
            title: (string) setting('seo.homepage_title', setting('seo.site_title', config('app.name'))),
            customTitle: (bool) setting('seo.homepage_title'),
            description: setting('seo.homepage_description'),
            canonical: url('/'),
            robots: setting('seo.homepage_noindex') ? 'noindex, follow' : null,
            ogTitle: ($t = trim((string) setting('seo.homepage_og_title', ''))) !== '' ? $t : null,
            ogDescription: ($d = trim((string) setting('seo.homepage_og_description', ''))) !== '' ? $d : null,
            ogImage: $ogImage,
            ogType: 'website',
            schemas: $this->base(),
        );
    }

    public function forProduct(Product $product): SeoData
    {
        $meta = $product->seoMeta;
        $template = $product->resolvedTemplate();

        $crumbs = [['name' => 'Home', 'url' => url('/')]];

        if ($category = $product->categories->first()) {
            foreach ($category->breadcrumbTrail() as $node) {
                $crumbs[] = ['name' => $node->name, 'url' => $node->url()];
            }
        }

        $crumbs[] = ['name' => $product->name, 'url' => null];

        // Schema emission is governed by the product's template toggles.
        $schemas = [];

        if ($template->schemaEnabled('organization')) {
            $schemas[] = $this->schema->organization();
        }
        if ($template->schemaEnabled('website')) {
            $schemas[] = $this->schema->website();
        }
        if ($template->schemaEnabled('localbusiness')) {
            $schemas[] = $this->schema->localBusiness();
            array_push($schemas, ...$this->schema->localBusinessLocations());
        }

        if (($meta?->schema_enabled ?? true) && $template->schemaEnabled('product')) {
            $schemas[] = $this->schema->product($product, includeReviews: $template->schemaEnabled('review'));
        }

        if ($template->schemaEnabled('faq')) {
            $schemas[] = $this->schema->faqPage($product->faqs, $product->url());
        }

        if ($template->schemaEnabled('breadcrumb')) {
            $schemas[] = $this->schema->breadcrumbs($crumbs);
        }

        array_push($schemas, ...$this->customGlobal(), ...$this->customFor($product));

        $schemas = array_values(array_filter($schemas));

        return new SeoData(
            title: $product->seoTitle(),
            customTitle: (bool) $meta?->title,
            description: $product->seoDescription(),
            canonical: $meta?->canonical_url ?: $product->url(),
            robots: $meta?->robotsContent(),
            ogTitle: $meta?->og_title,
            ogDescription: $meta?->og_description,
            ogImage: $meta?->og_image ? asset('storage/'.$meta->og_image) : $product->featuredImageUrl(),
            ogType: 'product',
            twitterTitle: $meta?->twitter_title,
            twitterDescription: $meta?->twitter_description,
            twitterImage: $meta?->twitter_image ? asset('storage/'.$meta->twitter_image) : null,
            schemas: $schemas,
            breadcrumbs: $crumbs,
            ogImageAlt: $product->featuredImageRecord()?->altText() ?: $product->name,
            // Facebook/Pinterest product card enrichment: price + stock.
            ogExtra: [
                'product:price:amount' => number_format($product->currentPrice(), 2, '.', ''),
                'product:price:currency' => (string) setting('general.currency', 'AED'),
                'product:availability' => $product->stock_status === 'in_stock' ? 'in stock' : 'out of stock',
            ],
        );
    }

    public function forCategory(Category $category, int $productCount = 0, int $page = 1, iterable $products = []): SeoData
    {
        $meta = $category->seoMeta;

        $crumbs = [['name' => 'Home', 'url' => url('/')]];

        foreach ($category->breadcrumbTrail() as $node) {
            $crumbs[] = ['name' => $node->name, 'url' => $node->id === $category->id ? null : $node->url()];
        }

        $canonical = $meta?->canonical_url ?: $category->url();

        if ($page > 1) {
            $canonical .= '?page='.$page;
        }

        // Filtered/sorted URLs (?sort=, ?min_price=, attribute filters…)
        // must not compete with the clean category URL: canonical already
        // points at it; also noindex,follow so crawl equity passes through
        // the links but the filtered variant never gets indexed.
        $robots = $meta?->robotsContent();

        if ($robots === null && collect(request()->query())->except('page')->isNotEmpty()) {
            $robots = 'noindex, follow';
        }

        $schemas = $this->base();

        if ($meta?->schema_enabled ?? true) {
            $schemas[] = $this->schema->category($category, $productCount, $products, $page);
            $schemas[] = $this->schema->faqPage($category->faqs, $category->url());
        }

        $schemas[] = $this->schema->breadcrumbs($crumbs);
        array_push($schemas, ...$this->customFor($category));

        return new SeoData(
            title: $category->seoTitle().($page > 1 ? " | Page {$page}" : ''),
            customTitle: (bool) $meta?->title,
            description: $category->seoDescription(),
            canonical: $canonical,
            robots: $robots,
            ogTitle: $meta?->og_title,
            ogDescription: $meta?->og_description,
            ogImage: $meta?->og_image ? asset('storage/'.$meta->og_image) : $category->imageUrl(),
            schemas: $schemas,
            breadcrumbs: $crumbs,
            ogImageAlt: $category->name.' product category',
        );
    }

    public function forPost(Post $post): SeoData
    {
        $meta = $post->seoMeta;

        $crumbs = [
            ['name' => 'Home', 'url' => url('/')],
            ['name' => 'Blog', 'url' => route('blog.index')],
            ['name' => $post->title, 'url' => null],
        ];

        $schemas = $this->base();

        if ($meta?->schema_enabled ?? true) {
            $schemas[] = $this->schema->article($post);

            // FAQ: prefer the stored relation; fall back to an inline bd-faq
            // block so a manually written article still emits FAQ schema.
            $faq = $this->schema->faqPage($post->faqs, $post->url())
                ?? $this->schema->faqFromContent($post, $post->url());
            $schemas[] = $faq;

            // HowTo from the article's own bd-steps block (only when present).
            if ($howTo = $this->schema->howTo($post)) {
                $schemas[] = $howTo;
            }

            if ($comparison = $this->schema->comparisonItemList($post)) {
                $schemas[] = $comparison;
            }
        }

        $schemas[] = $this->schema->breadcrumbs($crumbs);
        array_push($schemas, ...$this->customFor($post));

        return new SeoData(
            title: $post->seoMeta?->title ?: $post->title,
            customTitle: (bool) $post->seoMeta?->title,
            description: $post->seoDescription(),
            canonical: $meta?->canonical_url ?: $post->url(),
            robots: $meta?->robotsContent(),
            ogTitle: $meta?->og_title,
            ogDescription: $meta?->og_description,
            ogImage: $meta?->og_image ? asset('storage/'.$meta->og_image) : $post->featuredImageUrl(),
            ogType: 'article',
            schemas: $schemas,
            breadcrumbs: $crumbs,
            ogImageAlt: $post->featured_image_alt ?: $post->title,
            // article:* lets scrapers show freshness + section context.
            ogExtra: array_filter([
                'article:published_time' => $post->published_at?->toIso8601String(),
                'article:modified_time' => $post->updated_at?->toIso8601String(),
                'article:section' => $post->category?->name,
                'article:tag' => $post->tags->pluck('name')->all() ?: null,
            ]),
        );
    }

    public function forPage(Page $page): SeoData
    {
        $meta = $page->seoMeta;

        $crumbs = [
            ['name' => 'Home', 'url' => url('/')],
            ['name' => $page->title, 'url' => null],
        ];

        // Cart/checkout/account pages default to noindex.
        $noindexSlugs = ['cart', 'checkout', 'my-account'];
        $robots = $meta?->robotsContent();

        if (! $robots && in_array($page->slug, $noindexSlugs)) {
            $robots = 'noindex, nofollow';
        }

        $schemas = $this->base();

        if ($meta?->schema_enabled ?? true) {
            $schemas[] = $this->schema->webPage($page);
            $schemas[] = $this->schema->faqPage($page->faqs, $page->url());
        }

        $schemas[] = $this->schema->breadcrumbs($crumbs);
        array_push($schemas, ...$this->customFor($page));

        return new SeoData(
            title: $page->seoTitle(),
            customTitle: (bool) $meta?->title,
            description: $page->seoDescription(),
            canonical: $meta?->canonical_url ?: $page->url(),
            robots: $robots,
            schemas: $schemas,
            breadcrumbs: $crumbs,
        );
    }

    /** Author archive: indexable ProfilePage with the full Person entity. */
    public function forAuthor(\App\Models\User $author): SeoData
    {
        return new SeoData(
            title: 'Posts by '.$author->name,
            description: $author->bio ? str(strip_tags($author->bio))->limit(155)->toString() : null,
            canonical: $author->authorUrl(),
            schemas: [...$this->base(), $this->schema->profilePage($author)],
        );
    }

    /** Generic payload for search/other utility pages (noindex by default). */
    public function forUtility(string $title, bool $noindex = true, ?string $description = null, ?string $canonical = null): SeoData
    {
        return new SeoData(
            title: $title,
            // Fall back to the store's default description so indexable
            // utility pages (e.g. the shop index) never ship an empty meta.
            description: $description ?: setting('seo.default_description'),
            // Default to the query-stripped current URL; a paginated listing
            // passes its own ?page=N so page 2+ self-canonicalizes (never points
            // at page 1, which would deindex the deeper pages).
            canonical: $canonical ?: url()->current(),
            robots: $noindex ? 'noindex, follow' : null,
            schemas: $this->base(),
        );
    }

    /** Self-referencing canonical for a paginated listing (adds ?page=N when N>1). */
    public function paginatedCanonical(int $page): string
    {
        return url()->current().($page > 1 ? '?page='.$page : '');
    }

    public function jsonLd(SeoData $seo): string
    {
        return $this->schema->toJsonLd($seo->schemas);
    }
}
