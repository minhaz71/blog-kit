<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class CartService
{
    protected ?Cart $cart = null;

    /** Get (or lazily create) the active cart for the current user/session. */
    public function current(bool $create = true): ?Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        // items.product.categories is needed by CouponService::eligibleItems()
        // (category allow/exclude) — eager-load it so coupon validation never
        // N+1s over cart items.
        $query = Cart::active()->with([
            'items.product.categories',
            'items.variation.attributeValues.attribute',
            'coupon',
        ]);

        $cart = auth()->check()
            ? $query->where('user_id', auth()->id())->latest()->first()
            : $query->where('session_id', Session::getId())->whereNull('user_id')->latest()->first();

        if (! $cart && $create) {
            $cart = Cart::create([
                'user_id' => auth()->id(),
                'session_id' => Session::getId(),
            ]);
        }

        if ($cart) {
            $this->pruneOrphanedItems($cart);
        }

        return $this->cart = $cart;
    }

    /**
     * Drop cart items whose product no longer exists (soft-deleted or removed).
     * Such an item can't be purchased and would otherwise crash every view and
     * the subtotal (they all dereference $item->product). Deleted from the DB
     * and from the in-memory collection so the current request renders cleanly.
     */
    protected function pruneOrphanedItems(Cart $cart): void
    {
        $orphanIds = $cart->items
            ->filter(fn (CartItem $item) => $item->product === null)
            ->pluck('id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        $cart->items()->whereKey($orphanIds)->delete();
        $cart->setRelation(
            'items',
            $cart->items->reject(fn (CartItem $item) => $item->product === null)->values()
        );
    }

    public function add(Product $product, int $qty = 1, ?ProductVariation $variation = null): CartItem
    {
        $qty = max(1, $qty);

        if ($product->type === 'external') {
            throw ValidationException::withMessages(['product' => 'External products cannot be added to the cart.']);
        }

        if ($product->type === 'variable' && ! $variation) {
            throw ValidationException::withMessages(['variation' => 'Please choose the product options.']);
        }

        if ($variation && $variation->product_id !== $product->id) {
            throw ValidationException::withMessages(['variation' => 'Invalid product options.']);
        }

        $cart = $this->current();

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variation_id', $variation?->id)
            ->first();

        $newQty = ($item?->qty ?? 0) + $qty;
        $this->assertStock($product, $variation, $newQty);

        if ($item) {
            $item->update(['qty' => $newQty]);
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'product_variation_id' => $variation?->id,
                'qty' => $qty,
            ]);
        }

        $cart->touch();
        $this->cart = null; // force reload with fresh relations

        return $item;
    }

    public function updateQty(int $itemId, int $qty): void
    {
        $cart = $this->current(false);
        $item = $cart?->items()->with(['product', 'variation'])->findOrFail($itemId);

        if ($qty <= 0) {
            $item->delete();
        } else {
            $this->assertStock($item->product, $item->variation, $qty);
            $item->update(['qty' => $qty]);
        }

        $cart->touch();
        $this->cart = null;
    }

    public function remove(int $itemId): void
    {
        $cart = $this->current(false);
        $cart?->items()->whereKey($itemId)->delete();
        $cart?->touch();
        $this->cart = null;
    }

    public function clear(): void
    {
        $cart = $this->current(false);
        $cart?->items()->delete();
        $cart?->update(['coupon_id' => null]);
        $this->cart = null;
    }

    /** Merge the guest cart into the user's cart after login. */
    public function mergeGuestCart(string $sessionId): void
    {
        if (! auth()->check()) {
            return;
        }

        $guestCart = Cart::active()->where('session_id', $sessionId)->whereNull('user_id')->first();

        if (! $guestCart) {
            return;
        }

        $userCart = Cart::active()->where('user_id', auth()->id())->latest()->first();

        if (! $userCart) {
            $guestCart->update(['user_id' => auth()->id()]);

            return;
        }

        foreach ($guestCart->items as $item) {
            $existing = $userCart->items()
                ->where('product_id', $item->product_id)
                ->where('product_variation_id', $item->product_variation_id)
                ->first();

            if ($existing) {
                $existing->update(['qty' => $existing->qty + $item->qty]);
            } else {
                $item->update(['cart_id' => $userCart->id]);
            }
        }

        $guestCart->delete();
        $this->cart = null;
    }

    protected function assertStock(Product $product, ?ProductVariation $variation, int $qty): void
    {
        $purchasable = $variation
            ? $variation->is_active && $variation->isInStock() && $variation->stock_qty >= $qty
            : ($product->manage_stock ? $product->stock_qty >= $qty && $product->stock_status === 'in_stock' : $product->stock_status === 'in_stock');

        if ($product->status !== 'published' || ! $purchasable) {
            throw ValidationException::withMessages([
                'qty' => "\"{$product->name}\" is not available in the requested quantity.",
            ]);
        }
    }
}
