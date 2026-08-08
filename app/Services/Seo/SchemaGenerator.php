<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;

/**
 * Generates schema.org JSON-LD. Ratings/reviews are only emitted when real
 * approved reviews exist — fake review data is never generated.
 */
class SchemaGenerator
{
    public function organization(): array
    {
        $schema = [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => (string) setting('seo.organization_name', setting('general.store_name', config('app.name'))),
            'url' => url('/'),
        ];

        if ($logo = setting('seo.organization_logo')) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => asset('storage/'.ltrim($logo, '/')),
            ];
        }

        // sameAs comes from the individual social-profile settings the SEO
        // settings page actually stores (there is no seo.social_profiles key).
        $profiles = array_values(array_filter([
            setting('seo.social_facebook'),
            setting('seo.social_instagram'),
            setting('seo.social_twitter'),
            setting('seo.social_youtube'),
        ]));

        if ($profiles) {
            $schema['sameAs'] = $profiles;
        }

        return $schema;
    }

    public function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => (string) setting('seo.site_title', config('app.name')),
            'url' => url('/'),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Full GMB-parity LocalBusiness schema per Google's Local Business
     * structured-data guidelines: identity, complete postal address, geo
     * coordinates, structured opening hours, price range, accepted
     * payments, service area, and the Google Maps listing link.
     */
    public function localBusiness(): ?array
    {
        if (! setting('seo.local_business_enabled', false)) {
            return null;
        }

        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => setting('seo.local_business_address'),
            'addressLocality' => setting('seo.local_business_city'),
            'addressRegion' => setting('seo.local_business_region'),
            'postalCode' => setting('seo.local_business_postal_code'),
            'addressCountry' => setting('seo.local_business_country'),
        ]);

        $lat = setting('seo.local_business_latitude');
        $lng = setting('seo.local_business_longitude');

        return array_filter([
            '@type' => (string) setting('seo.local_business_type', 'Store'),
            '@id' => url('/').'#localbusiness',
            'name' => (string) setting('seo.organization_name', config('app.name')),
            'description' => setting('seo.local_business_description'),
            'url' => url('/'),
            'telephone' => setting('seo.local_business_phone'),
            'email' => setting('seo.local_business_email'),
            'image' => setting('seo.local_business_image')
                ? asset('storage/'.ltrim((string) setting('seo.local_business_image'), '/'))
                : null,
            'address' => count($address) > 1 ? $address : null,
            'geo' => ($lat && $lng) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ] : null,
            'hasMap' => setting('seo.local_business_map_url'),
            'openingHoursSpecification' => $this->openingHours((array) setting('seo.local_business_hours', [])),
            'priceRange' => setting('seo.local_business_price_range'),
            'paymentAccepted' => setting('seo.local_business_payment'),
            'currenciesAccepted' => store_currency(),
            'areaServed' => $this->areaServed((string) setting('seo.local_business_area_served', '')),
            'parentOrganization' => ['@id' => url('/').'#organization'],
        ]);
    }

    /**
     * Structured opening hours (Google's preferred format) from repeater
     * rows: [{days: [Monday,…], opens: "09:00", closes: "23:00"}].
     *
     * @return array<int, array>|null
     */
    protected function openingHours(array $rows): ?array
    {
        $specs = collect($rows)
            ->filter(fn ($row) => ! empty($row['days']) && ! empty($row['opens']) && ! empty($row['closes']))
            ->map(fn ($row) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values((array) $row['days']),
                'opens' => $row['opens'],
                'closes' => $row['closes'],
            ])
            ->values()
            ->all();

        return $specs !== [] ? $specs : null;
    }

    /** "Dubai, Sharjah, Ajman" → City entities Google can disambiguate. */
    protected function areaServed(string $csv): ?array
    {
        $cities = collect(explode(',', $csv))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->map(fn ($c) => ['@type' => 'City', 'name' => $c])
            ->values()
            ->all();

        return $cities !== [] ? $cities : null;
    }

    /**
     * Multi-location local SEO: one LocalBusiness block per configured
     * location (Dubai, Ajman, Sharjah, Abu Dhabi…), each a branch of the
     * main Organization. Configured in SEO settings → Local locations.
     *
     * @return array<int, array>
     */
    public function localBusinessLocations(): array
    {
        return collect((array) setting('seo.locations', []))
            ->filter(fn ($loc) => filled($loc['name'] ?? null) && filled($loc['city'] ?? null))
            ->values()
            ->map(fn (array $loc, int $i) => array_filter([
                '@type' => (string) (filled($loc['type'] ?? null) ? $loc['type'] : 'Store'),
                '@id' => url('/').'#location-'.($i + 1),
                'name' => $loc['name'],
                'url' => filled($loc['url'] ?? null) ? $loc['url'] : url('/'),
                'telephone' => $loc['phone'] ?? null,
                'parentOrganization' => ['@id' => url('/').'#organization'],
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $loc['address'] ?? null,
                    'addressLocality' => $loc['city'],
                    'postalCode' => $loc['postal_code'] ?? null,
                    'addressCountry' => filled($loc['country'] ?? null) ? $loc['country'] : 'AE',
                ]),
                'geo' => (filled($loc['latitude'] ?? null) && filled($loc['longitude'] ?? null)) ? [
                    '@type' => 'GeoCoordinates',
                    'latitude' => (float) $loc['latitude'],
                    'longitude' => (float) $loc['longitude'],
                ] : null,
                'hasMap' => $loc['map_url'] ?? null,
                'openingHours' => $loc['opening_hours'] ?? null,
                'priceRange' => $loc['price_range'] ?? null,
            ]))
            ->all();
    }

    public function product(Product $product, bool $includeReviews = true): array
    {
        $meta = $product->seoMeta;

        $schema = [
            '@type' => 'Product',
            '@id' => $product->url().'#product',
            'name' => $product->name,
            'url' => $product->url(),
            'description' => $this->plainText($product->short_description ?: $product->description, 500),
            // Freshness signal for AI answer engines — most AI citations come
            // from pages updated within the last year.
            'dateModified' => $product->updated_at?->toIso8601String(),
        ];

        if ($product->sku) {
            $schema['sku'] = $product->sku;
        }

        if ($product->gtin) {
            $schema['gtin'] = $product->gtin;
        }

        if ($product->brand) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }

        if ($category = $product->categories->first()) {
            // Google's documented format for Product.category is a ">"-joined
            // breadcrumb string, not just the leaf name — this preserves the
            // full hierarchy (e.g. "TEREA UAE > TEREA Yellow").
            $schema['category'] = collect($category->breadcrumbTrail())->pluck('name')->implode(' > ');
        }

        // Structured attributes (semantic SEO): flavor family, cooling
        // level, strength, etc. — schema.org's PropertyValue mechanism for
        // facts a keyword can't convey. Empty degrades silently.
        if ($product->attributeValues->isNotEmpty()) {
            $schema['additionalProperty'] = $product->attributeValues
                ->filter(fn ($value) => $value->attribute)
                ->map(fn ($value) => [
                    '@type' => 'PropertyValue',
                    'name' => $value->attribute->name,
                    'value' => $value->value,
                ])->values()->all();
        }

        $images = $product->images->map->url()->all();

        if ($product->featured_image) {
            array_unshift($images, $product->featuredImageUrl());
        }

        if ($images) {
            $schema['image'] = array_values(array_unique($images));
        }

        $schema['offers'] = $this->offer($product);

        // Real reviews only — never fabricate ratings.
        if ($includeReviews && $product->reviews_count > 0 && (float) $product->avg_rating > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $product->avg_rating,
                'reviewCount' => $product->reviews_count,
                'bestRating' => '5',
                'worstRating' => '1',
            ];

            $schema['review'] = $product->approvedReviews()
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($review) => [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => $review->author_name],
                    'datePublished' => $review->created_at->toDateString(),
                    'reviewBody' => str($review->body)->limit(500)->toString(),
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => (string) $review->rating,
                        'bestRating' => '5',
                        'worstRating' => '1',
                    ],
                ])->all();
        }

        // Admin-provided overrides win over generated fields.
        if ($meta?->schema_overrides) {
            $schema = array_replace($schema, $meta->schema_overrides);
        }

        return $schema;
    }

    public function offer(Product $product): array
    {
        [$minPrice, $maxPrice] = $product->priceRange();

        $offer = [
            '@type' => $minPrice !== $maxPrice ? 'AggregateOffer' : 'Offer',
            'url' => $product->url(),
            'priceCurrency' => store_currency(),
            'availability' => $product->isInStock()
                ? 'https://schema.org/InStock'
                : ($product->stock_status === 'on_backorder' ? 'https://schema.org/BackOrder' : 'https://schema.org/OutOfStock'),
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        if ($minPrice !== $maxPrice) {
            $offer['lowPrice'] = number_format($minPrice, 2, '.', '');
            $offer['highPrice'] = number_format($maxPrice, 2, '.', '');
            $offer['offerCount'] = $product->activeVariations->count();
        } else {
            $offer['price'] = number_format($minPrice, 2, '.', '');
        }

        // Sale prices expire when the sale does; stable prices get a one-year
        // horizon — Search Console warns when priceValidUntil is absent.
        $offer['priceValidUntil'] = $product->isOnSale() && $product->sale_ends_at
            ? $product->sale_ends_at->toDateString()
            : now()->addYear()->toDateString();

        $offer['seller'] = ['@id' => url('/').'#organization'];

        if ($days = setting('seo.return_policy_days')) {
            $offer['hasMerchantReturnPolicy'] = array_filter([
                '@type' => 'MerchantReturnPolicy',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => (int) $days,
                'applicableCountry' => $this->shippingCountry(),
                // Full merchant policy per Google's docs — who pays and how.
                'returnFees' => match ((string) setting('seo.return_fees')) {
                    'free' => 'https://schema.org/FreeReturn',
                    'customer' => 'https://schema.org/ReturnFeesCustomerResponsibility',
                    default => null,
                },
                'returnMethod' => match ((string) setting('seo.return_method')) {
                    'mail' => 'https://schema.org/ReturnByMail',
                    'store' => 'https://schema.org/ReturnInStore',
                    default => null,
                },
            ]);
        }

        // OfferShippingDetails per Google's product structured-data docs:
        // delivery cost + destination + speed. Emitted once the admin sets
        // a delivery fee (0 = free delivery) in SEO settings.
        $rate = setting('seo.shipping_rate');

        if ($rate !== null && $rate !== '') {
            $offer['shippingDetails'] = [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => [
                    '@type' => 'MonetaryAmount',
                    'value' => number_format((float) $rate, 2, '.', ''),
                    'currency' => store_currency(),
                ],
                'shippingDestination' => [
                    '@type' => 'DefinedRegion',
                    'addressCountry' => $this->shippingCountry(),
                ],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => 0,
                        'maxValue' => max(0, (int) setting('seo.shipping_handling_days', 0)),
                        'unitCode' => 'DAY',
                    ],
                    'transitTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => 0,
                        'maxValue' => max(0, (int) setting('seo.shipping_transit_days', 1)),
                        'unitCode' => 'DAY',
                    ],
                ],
            ];
        }

        return $offer;
    }

    /**
     * Ship-to country: the first specifically-selected selling country,
     * else the local-business country, else the configured store country.
     */
    protected function shippingCountry(): string
    {
        if (setting('general.sell_to_mode') === 'specific') {
            $picked = array_values((array) setting('general.sell_to_countries', []));

            if ($picked !== []) {
                return (string) $picked[0];
            }
        }

        return (string) (setting('seo.local_business_country') ?: setting('general.store_country', 'US'));
    }

    /** @param  iterable<Product>  $products  the current page's products, for a real ItemList */
    public function category(Category $category, int $productCount = 0, iterable $products = [], int $page = 1, int $perPage = 24): array
    {
        $itemListElement = collect($products)->values()->map(fn (Product $product, int $i) => [
            '@type' => 'ListItem',
            'position' => (($page - 1) * $perPage) + $i + 1,
            'url' => $product->url(),
        ])->all();

        return [
            '@type' => 'CollectionPage',
            '@id' => $category->url().'#collection',
            'name' => $category->seoTitle(),
            'url' => $category->url(),
            'description' => $this->plainText($category->description, 300),
            'isPartOf' => ['@id' => url('/').'#website'],
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $productCount,
                ...($itemListElement !== [] ? ['itemListElement' => $itemListElement] : []),
            ],
        ];
    }

    public function article(Post $post): array
    {
        $schema = [
            '@type' => $post->seoMeta?->schema_type ?: 'BlogPosting',
            '@id' => $post->url().'#article',
            'headline' => str($post->title)->limit(110)->toString(),
            'url' => $post->url(),
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            // E-E-A-T author byline. A post with no/deleted author must NOT
            // crash the whole JSON-LD graph — fall back to the Organization
            // as author (person() type-hints a non-null User).
            'author' => $post->author
                ? $this->person($post->author)
                : ['@id' => url('/').'#organization'],
            'publisher' => ['@id' => url('/').'#organization'],
            'mainEntityOfPage' => $post->url(),
            // Ties the article into the site entity graph (GEO / knowledge graph).
            'isPartOf' => ['@id' => url('/').'#website'],
        ];

        if ($post->featured_image) {
            // ImageObject with intrinsic dimensions when known (better for
            // Google image/Article rich results and AI answer surfaces);
            // falls back to the bare URL array form otherwise.
            $dimensions = image_dimensions($post->featured_image);
            $schema['image'] = $dimensions
                ? [
                    '@type' => 'ImageObject',
                    'url' => $post->featuredImageUrl(),
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                ]
                : [$post->featuredImageUrl()];
        }

        // Speakable: the headline + summary are safe to read aloud / lift as
        // the answer — a first-class signal for voice and AI answer engines.
        $schema['speakable'] = [
            '@type' => 'SpeakableSpecification',
            'cssSelector' => ['h1', '.bd-article > p:first-of-type'],
        ];

        if ($post->excerpt) {
            $schema['description'] = $this->plainText($post->excerpt, 300);
        }

        if ($post->category) {
            $schema['articleSection'] = $post->category->name;
        }

        if ($post->tags->isNotEmpty()) {
            $schema['keywords'] = $post->tags->pluck('name')->implode(', ');
        }

        if ($words = str_word_count(strip_tags((string) $post->content))) {
            $schema['wordCount'] = $words;
        }

        $schema['inLanguage'] = str_replace('_', '-', app()->getLocale());

        return $schema;
    }

    /**
     * Lightweight ItemList for a comparison article ("X vs Y") — points at
     * each compared product's own @id rather than duplicating the full
     * Product block (which already renders on that product's own page).
     */
    public function comparisonItemList(Post $post): ?array
    {
        $ids = (array) ($post->compared_product_ids ?? []);

        if ($ids === []) {
            return null;
        }

        $products = Product::query()->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values();

        if ($products->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'ItemList',
            '@id' => $post->url().'#compared',
            'itemListElement' => $products->map(fn (Product $product, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => [
                    '@type' => 'Product',
                    '@id' => $product->url().'#product',
                    'name' => $product->name,
                    'url' => $product->url(),
                ],
            ])->values()->all(),
        ];
    }

    /**
     * HowTo schema derived from the article's own <ol class="bd-steps"> block
     * (the numbered how-to markup the writer already emits). Only built when a
     * genuine step list exists (2+ steps) — never fabricated. Gives step-based
     * articles a shot at HowTo rich results and clean AI-answer extraction.
     */
    public function howTo(Post $post): ?array
    {
        $html = (string) $post->content;

        if (! str_contains($html, 'bd-steps')) {
            return null;
        }

        $dom = $this->domFromHtml($html);
        if (! $dom) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $list = $xpath->query("//ol[contains(concat(' ', normalize-space(@class), ' '), ' bd-steps ')]")->item(0);

        if (! $list) {
            return null;
        }

        $steps = [];
        foreach ($list->childNodes as $li) {
            if (! ($li instanceof \DOMElement) || strtolower($li->nodeName) !== 'li') {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', (string) $li->textContent));
            if ($text === '') {
                continue;
            }
            $position = count($steps) + 1;
            $steps[] = array_filter([
                '@type' => 'HowToStep',
                'position' => $position,
                // A short leading clause names the step; the full text is the detail.
                'name' => str(preg_split('/(?<=[.!?:])\s/', $text)[0] ?? $text)->limit(80)->toString(),
                'text' => str($text)->limit(500)->toString(),
                'url' => $post->url().'#step-'.$position,
            ]);
        }

        if (count($steps) < 2) {
            return null;
        }

        return [
            '@type' => 'HowTo',
            '@id' => $post->url().'#howto',
            'name' => str($post->title)->limit(110)->toString(),
            'step' => $steps,
        ];
    }

    /**
     * FAQPage derived from an inline <div class="bd-faq"> block when the post
     * has NO stored FAQ relation — so a manually written or older article with
     * an in-body FAQ still emits FAQ schema. Questions are the block's headings
     * (h2-h4 or a lone <strong>); each answer is the text up to the next
     * question. Requires 2+ well-formed pairs.
     */
    public function faqFromContent(Post $post, string $url): ?array
    {
        $html = (string) $post->content;

        if (! str_contains($html, 'bd-faq')) {
            return null;
        }

        $dom = $this->domFromHtml($html);
        if (! $dom) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $block = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' bd-faq ')]")->item(0);

        if (! $block) {
            return null;
        }

        $pairs = [];
        $question = null;
        $answer = '';

        $flush = function () use (&$pairs, &$question, &$answer): void {
            $q = trim((string) $question);
            $a = trim(preg_replace('/\s+/', ' ', $answer));
            if ($q !== '' && $a !== '') {
                $pairs[] = ['q' => $q, 'a' => $a];
            }
            $question = null;
            $answer = '';
        };

        foreach ($block->childNodes as $node) {
            if (! ($node instanceof \DOMElement)) {
                continue;
            }
            $tag = strtolower($node->nodeName);
            $isHeading = in_array($tag, ['h2', 'h3', 'h4', 'h5'], true)
                || ($tag === 'p' && $node->getElementsByTagName('strong')->length === 1 && trim($node->textContent) === trim($node->getElementsByTagName('strong')->item(0)->textContent));

            if ($isHeading) {
                $flush();
                $question = trim(preg_replace('/\s+/', ' ', (string) $node->textContent));
            } else {
                $answer .= ' '.$node->textContent;
            }
        }
        $flush();

        if (count($pairs) < 2) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $url.'#faq',
            'mainEntity' => collect($pairs)->map(fn ($p) => [
                '@type' => 'Question',
                'name' => str($p['q'])->limit(300)->toString(),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => str($p['a'])->limit(1200)->toString()],
            ])->all(),
        ];
    }

    /** Parse an HTML fragment into a DOMDocument (UTF-8 safe), or null. */
    protected function domFromHtml(string $html): ?\DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new \DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $dom : null;
    }

    public function webPage(Page $page): array
    {
        // Admin can override the page type via seo_meta.schema_type —
        // AboutPage / ContactPage / etc. E-E-A-T: the About page anchors
        // the entity graph by pointing mainEntity at the Organization.
        $type = $page->seoMeta?->schema_type ?: 'WebPage';

        return array_filter([
            '@type' => $type,
            '@id' => $page->url().'#webpage',
            'name' => $page->seoTitle(),
            'url' => $page->url(),
            'isPartOf' => ['@id' => url('/').'#website'],
            'mainEntity' => in_array($type, ['AboutPage', 'ContactPage'], true)
                ? ['@id' => url('/').'#organization']
                : null,
        ]);
    }

    /**
     * Full Person entity for an author — E-E-A-T byline schema with
     * credentials, photo and profile links when the user filled them.
     */
    public function person(\App\Models\User $author): array
    {
        return array_filter([
            '@type' => 'Person',
            '@id' => $author->authorUrl().'#person',
            'name' => $author->publicName(),
            'url' => $author->authorUrl(),
            'jobTitle' => $author->job_title,
            'description' => $this->plainText($author->bio, 300),
            'image' => $author->avatarUrl(),
            'sameAs' => array_values(array_filter((array) $author->social_links)) ?: null,
            'worksFor' => ['@id' => url('/').'#organization'],
        ]);
    }

    /** Author archive page → ProfilePage wrapping the Person entity. */
    public function profilePage(\App\Models\User $author): array
    {
        return [
            '@type' => 'ProfilePage',
            '@id' => $author->authorUrl().'#profilepage',
            'url' => $author->authorUrl(),
            'name' => 'Posts by '.$author->publicName(),
            'mainEntity' => $this->person($author),
            'isPartOf' => ['@id' => url('/').'#website'],
        ];
    }

    /**
     * HTML → clean plain text for schema description fields. Block/line-break
     * boundaries become spaces first (so "</p><p>" doesn't glue words into
     * "exhale.Flavor:"), THEN tags are stripped and HTML entities decoded
     * (&quot; → ", &#039; → '). Whitespace is squished. Returns null when the
     * result is empty. $limit (chars) is applied last when given.
     */
    protected function plainText(?string $html, ?int $limit = null): ?string
    {
        $text = (string) $html;

        if ($text === '') {
            return null;
        }

        $text = preg_replace(
            '/<\/?(p|div|br|li|ul|ol|h[1-6]|tr|td|th|table|section|article|header|footer|blockquote)\b[^>]*>/i',
            ' ',
            $text
        ) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str($text)->squish()->toString();

        if ($text === '') {
            return null;
        }

        return $limit ? str($text)->limit($limit)->toString() : $text;
    }

    /** @param iterable<\App\Models\Faq> $faqs */
    public function faqPage(iterable $faqs, string $url): ?array
    {
        $questions = collect($faqs)->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq->question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $this->plainText($faq->answer) ?? '',
            ],
        ])->values();

        if ($questions->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $url.'#faq',
            'mainEntity' => $questions->all(),
        ];
    }

    /** @param array<int, array{name:string, url:?string}> $crumbs */
    public function breadcrumbs(array $crumbs): ?array
    {
        if (count($crumbs) < 2) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn ($crumb, $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'] ?? null,
            ]))->all(),
        ];
    }

    /** Wrap graphs in a single JSON-LD @graph document. */
    public function toJsonLd(array $schemas): string
    {
        $document = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter($schemas)),
        ];

        return json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** Validation used by the admin schema preview tool. */
    public function validateProductSchema(Product $product): array
    {
        $issues = [];

        if (blank($product->short_description) && blank($product->description)) {
            $issues[] = ['field' => 'description', 'message' => 'Product has no description.'];
        }

        if (! $product->featured_image && $product->images->isEmpty()) {
            $issues[] = ['field' => 'image', 'message' => 'Product has no image.'];
        }

        if ((float) $product->price <= 0 && $product->type !== 'variable') {
            $issues[] = ['field' => 'price', 'message' => 'Product price is missing or zero.'];
        }

        if ($product->type === 'variable' && $product->activeVariations->isEmpty()) {
            $issues[] = ['field' => 'offers', 'message' => 'Variable product has no active variations.'];
        }

        if (! $product->sku) {
            $issues[] = ['field' => 'sku', 'message' => 'SKU is recommended for product schema.'];
        }

        if ($product->reviews_count > 0 && ((float) $product->avg_rating < 1 || (float) $product->avg_rating > 5)) {
            $issues[] = ['field' => 'aggregateRating', 'message' => 'Rating value is outside the 1–5 range.'];
        }

        return $issues;
    }
}
