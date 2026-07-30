<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    /**
     * Current unit price, always resolved server-side from the catalog.
     * Defensive: an orphaned item (product deleted after being added to a
     * cart) prices at 0 rather than crashing — the cart is pruned of these on
     * normal load (CartService::current()), but anything that queries carts
     * directly (e.g. an admin report) can still encounter one.
     */
    public function unitPrice(): float
    {
        return $this->variation?->currentPrice() ?? $this->product?->currentPrice() ?? 0.0;
    }

    public function lineTotal(): float
    {
        return round($this->unitPrice() * $this->qty, 2);
    }

    public function displayName(): string
    {
        // Defensive: the cart is pruned of orphaned items on load, but never
        // let a missing product hard-crash a view that reaches this.
        $name = $this->product?->name ?? 'Unavailable item';

        if ($this->variation) {
            $name .= ' ('.$this->variation->label().')';
        }

        return $name;
    }
}
