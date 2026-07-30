<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        // stock_qty is NOT NULL — coerce a null/blank (from any write path:
        // admin form, import, API) to 0 so a missing quantity never 500s.
        static::saving(function (self $variation): void {
            if ($variation->stock_qty === null || $variation->stock_qty === '') {
                $variation->stock_qty = 0;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'weight' => 'decimal:3',
            'schema_overrides' => 'array',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variation_values');
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price < (float) $this->price;
    }

    public function currentPrice(): float
    {
        return $this->isOnSale() ? (float) $this->sale_price : (float) $this->price;
    }

    public function isInStock(): bool
    {
        return $this->stock_status === 'in_stock' && $this->stock_qty > 0;
    }

    public function decrementStock(int $qty): void
    {
        $this->decrement('stock_qty', $qty);

        if ($this->fresh()->stock_qty <= 0) {
            $this->update(['stock_status' => 'out_of_stock']);
        }
    }

    /** e.g. "Color: Red / Size: XL" */
    public function label(): string
    {
        return $this->attributeValues
            ->map(fn ($v) => $v->attribute->name.': '.$v->value)
            ->implode(' / ');
    }

    /** e.g. {"color":"red","size":"xl"} for frontend matching */
    public function optionMap(): array
    {
        return $this->attributeValues
            ->mapWithKeys(fn ($v) => [$v->attribute->slug => $v->slug])
            ->all();
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.ltrim($this->image, '/')) : $this->product?->featuredImageUrl();
    }
}
