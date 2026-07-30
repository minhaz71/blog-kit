<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /** Validate a coupon for the given cart; throws ValidationException when invalid. */
    public function validate(Coupon $coupon, Cart $cart, ?User $user, ?string $email = null): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['coupon' => $message]);

        if (! $coupon->is_active) {
            $fail('This coupon is not valid.');
        }

        if (! $coupon->isWithinDates()) {
            $fail('This coupon has expired or is not active yet.');
        }

        if (! $coupon->hasUsagesLeft()) {
            $fail('This coupon has reached its usage limit.');
        }

        if ($coupon->usage_limit_per_user !== null
            && $coupon->usagesByCustomer($user?->id, $email ?? $user?->email) >= $coupon->usage_limit_per_user) {
            $fail('You have already used this coupon the maximum number of times.');
        }

        $subtotal = $cart->subtotal();

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            $fail('A minimum order of '.price_format($coupon->min_order_amount).' is required for this coupon.');
        }

        if ($coupon->max_order_amount !== null && $subtotal > (float) $coupon->max_order_amount) {
            $fail('This coupon only applies to orders up to '.price_format($coupon->max_order_amount).'.');
        }

        if (($coupon->first_order_only || $coupon->type === 'first_order')) {
            $hasOrders = $user
                ? $user->orders()->whereNotIn('status', ['cancelled', 'failed'])->exists()
                : ($email && \App\Models\Order::where('customer_email', $email)->whereNotIn('status', ['cancelled', 'failed'])->exists());

            if ($hasOrders) {
                $fail('This coupon is only valid on your first order.');
            }
        }

        if ($coupon->allowedUsers()->exists() && (! $user || ! $coupon->allowedUsers()->whereKey($user->id)->exists())) {
            $fail('This coupon is not available for your account.');
        }

        if ($this->eligibleItems($coupon, $cart)->isEmpty()) {
            $fail('This coupon does not apply to any item in your cart.');
        }
    }

    /**
     * Calculate the discount amount for the cart. Never trusts client totals —
     * everything derives from live catalog prices.
     */
    public function discountFor(Coupon $coupon, Cart $cart): float
    {
        $eligible = $this->eligibleItems($coupon, $cart);
        $eligibleTotal = $eligible->sum(fn (CartItem $item) => $item->lineTotal());
        $cartSubtotal = $cart->subtotal();

        $discount = match ($coupon->type) {
            'percent' => $eligibleTotal * ((float) $coupon->value / 100),
            'fixed_cart', 'first_order' => min((float) $coupon->value, $cartSubtotal),
            'fixed_product' => $eligible->sum(fn (CartItem $item) => min((float) $coupon->value, $item->unitPrice()) * $item->qty),
            'bxgy' => $this->buyXGetYDiscount($coupon, $eligible),
            'free_shipping' => 0.0, // shipping handled by ShippingCalculator via freeShipping()
            default => 0.0,
        };

        return round(min($discount, $cartSubtotal), 2);
    }

    public function grantsFreeShipping(Coupon $coupon): bool
    {
        return $coupon->free_shipping || $coupon->type === 'free_shipping';
    }

    /** Items the coupon may discount, honoring product/category allow+exclude lists. */
    public function eligibleItems(Coupon $coupon, Cart $cart)
    {
        $allowedProducts = $coupon->products->where('pivot.is_excluded', false)->pluck('id');
        $excludedProducts = $coupon->products->where('pivot.is_excluded', true)->pluck('id');
        $allowedCategories = $coupon->categories->where('pivot.is_excluded', false)->pluck('id');
        $excludedCategories = $coupon->categories->where('pivot.is_excluded', true)->pluck('id');

        return $cart->items->filter(function (CartItem $item) use ($allowedProducts, $excludedProducts, $allowedCategories, $excludedCategories) {
            $productId = $item->product_id;
            $categoryIds = $item->product?->categories->pluck('id') ?? collect();

            if ($excludedProducts->contains($productId)) {
                return false;
            }

            if ($excludedCategories->intersect($categoryIds)->isNotEmpty()) {
                return false;
            }

            if ($allowedProducts->isNotEmpty() && ! $allowedProducts->contains($productId)) {
                return false;
            }

            if ($allowedCategories->isNotEmpty() && $allowedCategories->intersect($categoryIds)->isEmpty()) {
                return false;
            }

            return true;
        });
    }

    /** Buy X get Y: for every (X+Y) eligible units, the Y cheapest are free. */
    protected function buyXGetYDiscount(Coupon $coupon, $eligibleItems): float
    {
        $buy = (int) ($coupon->buy_qty ?? 0);
        $get = (int) ($coupon->get_qty ?? 0);

        if ($buy < 1 || $get < 1) {
            return 0.0;
        }

        // Expand to unit prices, cheapest first.
        $units = collect();
        foreach ($eligibleItems as $item) {
            for ($i = 0; $i < $item->qty; $i++) {
                $units->push($item->unitPrice());
            }
        }

        $sets = intdiv($units->count(), $buy + $get);
        $freeUnits = $sets * $get;

        return (float) $units->sort()->take($freeUnits)->sum();
    }
}
