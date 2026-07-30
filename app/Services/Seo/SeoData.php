<?php

namespace App\Services\Seo;

/** Page-level SEO payload consumed by the layout's <head> partial. */
class SeoData
{
    public function __construct(
        public string $title = '',
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $robots = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public string $ogType = 'website',
        public ?string $twitterTitle = null,
        public ?string $twitterDescription = null,
        public ?string $twitterImage = null,
        /** @var array<int, array<string, mixed>> JSON-LD graphs */
        public array $schemas = [],
        /** @var array<int, array{name:string, url:?string}> */
        public array $breadcrumbs = [],
        /** True when the title is a deliberately crafted meta title (SEO meta / AI) — used verbatim, no site suffix. */
        public bool $customTitle = false,
        /** Accessible description of the OG image (og:image:alt / twitter:image:alt). */
        public ?string $ogImageAlt = null,
        /**
         * Extra OG property tags per page type — article:published_time,
         * product:price:amount, … Values may be arrays for repeated
         * properties (article:tag).
         *
         * @var array<string, string|array<int, string>>
         */
        public array $ogExtra = [],
    ) {}

    public function fullTitle(): string
    {
        // A crafted meta title had its 60 characters budgeted on purpose —
        // appending a site suffix would blow the limit and dilute the keyword.
        if ($this->customTitle && $this->title !== '') {
            return $this->title;
        }

        $siteName = (string) setting('seo.site_title', config('app.name'));
        $separator = (string) setting('seo.title_separator', '|');

        if ($this->title === '' || $this->title === $siteName) {
            $tagline = (string) setting('seo.site_tagline', '');

            return $tagline ? "{$siteName} {$separator} {$tagline}" : $siteName;
        }

        return "{$this->title} {$separator} {$siteName}";
    }

    public function resolvedOgTitle(): string
    {
        // OG guideline: og:title is the CONTENT title — og:site_name already
        // carries the brand, so the "| Site" suffix would just duplicate it.
        return $this->ogTitle ?: ($this->title !== '' ? $this->title : $this->fullTitle());
    }

    public function resolvedOgDescription(): ?string
    {
        return $this->ogDescription ?: $this->description;
    }

    public function resolvedOgImage(): ?string
    {
        return $this->ogImage ?: (setting('seo.default_og_image') ? asset('storage/'.setting('seo.default_og_image')) : null);
    }

    public function resolvedTwitterTitle(): string
    {
        return $this->twitterTitle ?: $this->resolvedOgTitle();
    }

    public function resolvedTwitterDescription(): ?string
    {
        return $this->twitterDescription ?: $this->resolvedOgDescription();
    }

    public function resolvedTwitterImage(): ?string
    {
        return $this->twitterImage ?: $this->resolvedOgImage();
    }

    /** og:locale format: language_TERRITORY (en → en_US, ar-AE → ar_AE). */
    public function ogLocale(): string
    {
        $locale = str_replace('-', '_', app()->getLocale());

        return str_contains($locale, '_') ? $locale : ($locale === 'en' ? 'en_US' : $locale);
    }

    /**
     * Pixel dimensions of the resolved OG image when it lives on this
     * server — og:image:width/height let scrapers render the card on the
     * FIRST share instead of after a re-crawl (Facebook guideline).
     * Remote/unknown images return null and the tags are simply omitted.
     *
     * @return array{0: int, 1: int}|null
     */
    public function ogImageDimensions(): ?array
    {
        $url = $this->resolvedOgImage();

        if (! $url || ! str_contains($url, '/storage/')) {
            return null;
        }

        $relative = urldecode(substr($url, strpos($url, '/storage/') + strlen('/storage/')));
        $path = public_path('storage/'.$relative);

        if (! is_file($path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Cache::remember(
            'ogdim.'.md5($path.'|'.(int) @filemtime($path)),
            now()->addWeek(),
            function () use ($path): ?array {
                $size = @getimagesize($path);

                return $size ? [(int) $size[0], (int) $size[1]] : null;
            }
        );
    }
}
