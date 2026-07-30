<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ProductTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'settings' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Every block type the single-product builder offers. Grouped by where
     * it usually lives so the editor and docs read clearly.
     */
    public const BLOCK_TYPES = [
        'breadcrumbs' => 'Breadcrumbs',
        'gallery' => 'Product gallery',
        'title' => 'Product title',
        'rating' => 'Rating stars',
        'price' => 'Price',
        'key_facts' => 'Key facts (bullets)',
        'short_description' => 'Short description',
        'variations' => 'Variation selectors',
        'add_to_cart' => 'Add to cart',
        'categories' => 'Category links',
        'payment' => 'Payment / bulk discount',
        'delivery_info' => 'Delivery info boxes',
        'description' => 'Long description / tabs',
        'specifications' => 'Specifications table',
        'faq' => 'FAQ accordion',
        'reviews' => 'Reviews',
        'related' => 'Related products',
        'cross_sells' => 'Frequently bought together',
        'upsells' => 'Upsells',
        'heading' => 'Custom heading',
        'html' => 'Custom HTML block',
        'divider' => 'Divider',
        'spacer' => 'Spacer',
    ];

    public const FONT_SIZES = [
        'xs' => 'Extra small', 'sm' => 'Small', 'base' => 'Normal',
        'lg' => 'Large', 'xl' => 'Extra large', '2xl' => '2X large', '3xl' => '3X large',
    ];

    /** Which schema documents this template emits (all on by default). */
    public function schemaEnabled(string $key): bool
    {
        return (bool) data_get($this->settings, "schema.{$key}", true);
    }

    /** Default gallery output width in px (image quality/size lever). */
    public function galleryWidth(): int
    {
        return (int) (data_get($this->settings, 'gallery_image_width') ?: 700);
    }

    public function containerClass(): string
    {
        return match (data_get($this->settings, 'container', '7xl')) {
            'full' => 'max-w-full',
            '5xl' => 'max-w-5xl',
            '6xl' => 'max-w-6xl',
            default => 'max-w-7xl',
        };
    }

    /**
     * Resolve the template that governs a product: its explicit override,
     * else the default row, else a code-defined fallback so the storefront
     * always renders (fresh installs, tests, deleted templates).
     */
    public static function resolve(Product $product): self
    {
        if ($product->product_template_id) {
            // safe_cache() guards against Eloquent models rehydrating to
            // __PHP_Incomplete_Class in the file/database cache stores.
            $template = safe_cache(
                "product_template.{$product->product_template_id}",
                3600,
                fn () => self::find($product->product_template_id),
            );

            if ($template instanceof self) {
                return $template;
            }
        }

        return self::default();
    }

    public static function default(): self
    {
        $template = safe_cache('product_template.default', 3600, function () {
            return self::where('is_default', true)->first();
        });

        return $template instanceof self ? $template : self::codeDefault();
    }

    public static function forgetCache(): void
    {
        Cache::forget('product_template.default');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'product_template_id');
    }

    protected static function booted(): void
    {
        // Only one template may be the default at a time.
        static::saving(function (self $t): void {
            if ($t->is_default && $t->isDirty('is_default')) {
                static::where('is_default', true)->whereKeyNot($t->getKey())->update(['is_default' => false]);
            }
        });

        static::saved(fn (self $t) => self::clearCaches($t));
        static::deleted(fn (self $t) => self::clearCaches($t));
    }

    protected static function clearCaches(self $t): void
    {
        Cache::forget('product_template.default');
        Cache::forget("product_template.{$t->id}");
    }

    /**
     * The built-in layout, used when no DB template exists. Mirrors the
     * seeded default so behaviour is identical with or without seeding.
     */
    public static function codeDefault(): self
    {
        return (new self)->forceFill([
            'name' => 'Default',
            'is_default' => true,
            'settings' => self::defaultSettings(),
            'blocks' => self::defaultBlocks(),
        ]);
    }

    public static function defaultSettings(): array
    {
        return [
            'container' => '7xl',
            'gallery_image_width' => 700,
            'schema' => [
                'product' => true,
                'organization' => true,
                'website' => true,
                'localbusiness' => true,
                'breadcrumb' => true,
                'faq' => true,
                'review' => true,
            ],
        ];
    }

    /** @return array<int, array{type:string, data:array}> */
    public static function defaultBlocks(): array
    {
        $b = fn (string $type, array $data = []) => ['type' => $type, 'data' => $data];

        return [
            $b('breadcrumbs', ['column' => 'full']),
            $b('gallery', ['column' => 'left', 'show_thumbnails' => true, 'rounded' => true]),
            $b('title', ['column' => 'right', 'show_brand' => true]),
            $b('rating', ['column' => 'right']),
            // Short description ABOVE the price: the buyer reads what it is
            // before what it costs.
            $b('short_description', ['column' => 'right']),
            $b('price', ['column' => 'right', 'font_size' => '2xl']),
            $b('key_facts', ['column' => 'right', 'use_specifications' => true]),
            $b('variations', ['column' => 'right']),
            $b('add_to_cart', ['column' => 'right', 'button_text' => 'Add to cart', 'show_wishlist' => true]),
            $b('categories', ['column' => 'right']),
            $b('payment', [
                'column' => 'right',
                'heading' => 'Pay on delivery',
                'note' => 'We accept cash on delivery or card payment on delivery, anywhere in the UAE.',
                'methods' => ['cash', 'card', 'visa', 'mastercard', 'applepay', 'gpay'],
            ]),
            $b('delivery_info', [
                'column' => 'right',
                'boxes' => [
                    ['icon' => '🚚', 'title' => 'Free Delivery Over AED 300', 'body' => 'Spend AED 300 or more and delivery is free anywhere in the UAE.', 'bg_color' => '#0f766e'],
                    ['icon' => '⚡', 'title' => '1-2 Hour Express Delivery', 'body' => 'Dubai, Sharjah and Ajman orders arrive at your door in 1 to 2 hours.', 'bg_color' => '#0d9488'],
                    ['icon' => '🕐', 'title' => 'Same-Day, UAE-Wide', 'body' => 'Abu Dhabi, Al Ain, RAK and UAQ within 12 hours. Order before 12:30 PM for same-day dispatch; later orders ship next day.', 'bg_color' => '#115e59'],
                ],
            ]),
            $b('html', [
                'column' => 'right',
                'content' => '<div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-medium text-gray-500">'
                    .'<span>✅ 100% genuine, factory-sealed</span>'
                    .'<span>🔞 18+ only</span>'
                    .'<span>🔒 Secure checkout</span>'
                    .'</div>',
            ]),
            $b('description', ['column' => 'full', 'layout' => 'tabs', 'show_reviews_tab' => true]),
            $b('specifications', ['column' => 'full', 'heading' => 'Specifications']),
            $b('faq', ['column' => 'full']),
            $b('reviews', ['column' => 'full', 'show_form' => true]),
            $b('related', ['column' => 'full', 'heading' => 'Related products', 'limit' => 4]),
            $b('cross_sells', ['column' => 'full', 'heading' => 'Frequently bought together']),
        ];
    }

    /** Normalised block list with data defaults, for rendering. */
    public function resolvedBlocks(): array
    {
        $blocks = $this->blocks ?: self::defaultBlocks();

        return array_values(array_filter($blocks, fn ($block) => is_array($block)
            && isset($block['type'])
            && array_key_exists($block['type'], self::BLOCK_TYPES)));
    }
}
