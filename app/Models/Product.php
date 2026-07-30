<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSeoMeta;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, HasFaqs, HasSeoMeta, HasSlug, \App\Models\Concerns\MovesInlineStylesToCustomCss, Searchable, SoftDeletes;

    public const TYPES = ['simple', 'variable', 'grouped', 'digital', 'external'];
    public const STOCK_STATUSES = ['in_stock', 'out_of_stock', 'on_backorder'];

    protected $guarded = [];

    protected function styleExtractionColumns(): array
    {
        return ['description', 'short_description'];
    }

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'manage_stock' => 'boolean',
            'is_featured' => 'boolean',
            'is_new_arrival' => 'boolean',
            'is_best_seller' => 'boolean',
            'weight' => 'decimal:3',
            'avg_rating' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function shippingClass()
    {
        return $this->belongsTo(ShippingClass::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * The media record backing the featured image (kept in sync by the
     * observer), falling back to the first gallery image — so featured-image
     * alt/title always come from editable media data.
     */
    public function featuredImageRecord(): ?ProductImage
    {
        return $this->images->firstWhere('path', $this->featured_image) ?? $this->images->first();
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
            ->withPivot(['is_variation', 'is_visible']);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values');
    }

    /**
     * Taxonomy attributes as visible label/value rows for spec tables —
     * multi-value attributes (e.g. device compatibility) joined into one
     * row. Keeps on-page text in sync with the additionalProperty schema:
     * structured data must describe content the visitor can actually see.
     *
     * @return array<string, string> label => value
     */
    public function attributeFacts(): array
    {
        return $this->attributeValues
            ->loadMissing('attribute')
            ->filter(fn ($value) => $value->attribute !== null)
            ->groupBy(fn ($value) => $value->attribute->name)
            ->map(fn ($values) => $values->pluck('value')->implode(', '))
            ->all();
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function activeVariations()
    {
        return $this->variations()->where('is_active', true);
    }

    public function template()
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    /** The resolved single-product layout (override → default → code fallback). */
    public function resolvedTemplate(): ProductTemplate
    {
        return ProductTemplate::resolve($this);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews()
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function relatedProducts()
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', 'related');
    }

    public function upsells()
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', 'upsell');
    }

    public function crossSells()
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', 'cross_sell');
    }

    public function groupedChildren()
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', 'grouped');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->published()->whereIn('visibility', ['visible', 'catalog']);
    }

    public function scopeSearchable(Builder $q): Builder
    {
        return $q->published()->whereIn('visibility', ['visible', 'search']);
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where('stock_status', 'in_stock');
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }

    public function scopeOnSale(Builder $q): Builder
    {
        return $q->whereNotNull('sale_price')
            ->where(fn ($q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now()));
    }

    // ── Pricing / stock ────────────────────────────────────────────

    public function isOnSale(): bool
    {
        if ($this->sale_price === null) {
            return false;
        }
        if ($this->sale_starts_at && $this->sale_starts_at->isFuture()) {
            return false;
        }
        if ($this->sale_ends_at && $this->sale_ends_at->isPast()) {
            return false;
        }

        return (float) $this->sale_price < (float) $this->price;
    }

    /** The price a customer actually pays right now. */
    public function currentPrice(): float
    {
        return $this->isOnSale() ? (float) $this->sale_price : (float) $this->price;
    }

    public function discountPercent(): ?int
    {
        if (! $this->isOnSale() || (float) $this->price <= 0) {
            return null;
        }

        return (int) round((1 - (float) $this->sale_price / (float) $this->price) * 100);
    }

    /** Min/max current price across active variations, for "from $X" display. */
    public function priceRange(): array
    {
        if ($this->type !== 'variable' || $this->activeVariations->isEmpty()) {
            $p = $this->currentPrice();

            return [$p, $p];
        }

        $prices = $this->activeVariations->map->currentPrice();

        return [(float) $prices->min(), (float) $prices->max()];
    }

    public function isInStock(): bool
    {
        if ($this->type === 'variable') {
            return $this->activeVariations->contains(fn ($v) => $v->isInStock());
        }

        return $this->stock_status === 'in_stock' && (! $this->manage_stock || $this->stock_qty > 0);
    }

    public function isLowStock(): bool
    {
        return $this->manage_stock && $this->stock_qty > 0 && $this->stock_qty <= $this->low_stock_threshold;
    }

    public function decrementStock(int $qty): void
    {
        if (! $this->manage_stock) {
            return;
        }

        $this->decrement('stock_qty', $qty);

        if ($this->fresh()->stock_qty <= 0) {
            $this->update(['stock_status' => 'out_of_stock']);
        }
    }

    public function featuredImageUrl(): ?string
    {
        if ($this->featured_image) {
            return asset('storage/'.ltrim($this->featured_image, '/'));
        }

        return $this->images->first()?->url();
    }

    /**
     * WebP variant of the featured image when a twin exists, else the
     * original. Card/grid + gallery-fallback thumbnails call this so listing
     * pages ship the smaller WebP (better LCP) instead of the raw JPEG/PNG.
     */
    public function featuredImageWebpUrl(): ?string
    {
        return $this->featuredImageRecord()?->webpUrl() ?? $this->featuredImageUrl();
    }

    public function url(): string
    {
        return \App\Support\Permalinks::product($this->slug);
    }

    public function recalculateRating(): void
    {
        $stats = $this->approvedReviews()
            ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg_rating')
            ->first();

        $this->forceFill([
            'reviews_count' => (int) $stats->cnt,
            'avg_rating' => round((float) $stats->avg_rating, 2),
        ])->saveQuietly();
    }

    // ── Scout ──────────────────────────────────────────────────────

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'short_description' => strip_tags($this->short_description ?? ''),
            'brand' => $this->brand?->name,
            'categories' => $this->categories->pluck('name')->implode(' '),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published' && in_array($this->visibility, ['visible', 'search']);
    }
}
