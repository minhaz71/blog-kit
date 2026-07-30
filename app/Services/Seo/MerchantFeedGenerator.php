<?php

namespace App\Services\Seo;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Google Shopping product feed (RSS 2.0 + g: namespace) for FREE organic
 * listings in Google's Shopping tab — Bing Merchant Center accepts the
 * identical format, so one endpoint feeds both.
 *
 * Served at /feeds/products.xml, cached on the sitemap's version key so any
 * product change invalidates it the same way sitemaps invalidate. Products
 * can be excluded via seo.feed_exclude_product_ids (comma-separated ids),
 * mirroring the sitemap exclusion setting.
 */
class MerchantFeedGenerator
{
    public static function enabled(): bool
    {
        return (bool) setting('seo.feed_enabled', true);
    }

    public function xml(): string
    {
        // Same versioned-key trick as the sitemaps: content changes bump the
        // version, so this cache entry is abandoned automatically.
        $key = 'sitemap.v'.(int) Cache::get('sitemap.version', 1).'.merchant-feed';

        return Cache::remember($key, 3600, fn () => $this->build());
    }

    protected function build(): string
    {
        $excluded = collect(explode(',', (string) setting('seo.feed_exclude_product_ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->all();

        $products = Product::query()
            ->where('status', 'published')
            ->whereNotIn('id', $excluded ?: [0])
            ->with(['brand', 'images', 'categories'])
            ->orderBy('id')
            ->get();

        $items = $products->map(fn (Product $p) => $this->item($p))->filter()->implode("\n");

        $title = e((string) setting('general.site_name', config('app.name')));
        $link = e(url('/'));

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
<title>{$title}</title>
<link>{$link}</link>
<description>Product feed for Google Merchant Center and Bing Merchant Center (free listings).</description>
{$items}
</channel>
</rss>
XML;
    }

    protected function item(Product $p): ?string
    {
        $image = $p->featuredImageUrl();

        // Merchant Center rejects items without an image — skip rather than
        // ship a guaranteed disapproval.
        if (! $image) {
            return null;
        }

        $currency = (string) setting('general.currency', 'AED');
        $price = number_format((float) $p->price, 2, '.', '').' '.$currency;
        $salePrice = $p->isOnSale() ? number_format((float) $p->currentPrice(), 2, '.', '').' '.$currency : null;

        $availability = $p->isInStock() ? 'in_stock' : 'out_of_stock';

        $description = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags(
            (string) ($p->short_description ?: $p->description)
        )), 0, 4990));

        $category = $p->categories->first()?->name;

        $lines = [
            '<item>',
            '<g:id>'.e($p->sku ?: 'P'.$p->id).'</g:id>',
            '<g:title>'.e(mb_substr($p->name, 0, 150)).'</g:title>',
            '<g:description>'.e($description ?: $p->name).'</g:description>',
            '<g:link>'.e($p->url()).'</g:link>',
            '<g:image_link>'.e($image).'</g:image_link>',
            '<g:price>'.e($price).'</g:price>',
        ];

        if ($salePrice) {
            $lines[] = '<g:sale_price>'.e($salePrice).'</g:sale_price>';
        }

        $lines[] = '<g:availability>'.$availability.'</g:availability>';
        $lines[] = '<g:condition>new</g:condition>';

        if ($p->brand?->name) {
            $lines[] = '<g:brand>'.e($p->brand->name).'</g:brand>';
        }

        // GTIN when known; otherwise declare "no identifier" so Merchant
        // Center doesn't hold the item waiting for one.
        if ($p->gtin) {
            $lines[] = '<g:gtin>'.e($p->gtin).'</g:gtin>';
        } else {
            $lines[] = '<g:identifier_exists>no</g:identifier_exists>';
        }

        if ($category) {
            $lines[] = '<g:product_type>'.e($category).'</g:product_type>';
        }

        // UAE shipping: express service, price from the same setting the
        // Product schema's shippingDetails uses.
        $shippingRate = number_format((float) setting('seo.shipping_rate', 0), 2, '.', '');
        $lines[] = '<g:shipping><g:country>AE</g:country><g:service>Express (1-2 hours Dubai, Sharjah, Ajman)</g:service><g:price>'.$shippingRate.' '.$currency.'</g:price></g:shipping>';

        $lines[] = '</item>';

        return implode("\n", $lines);
    }
}
