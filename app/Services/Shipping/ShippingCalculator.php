<?php

namespace App\Services\Shipping;

use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;

class ShippingCalculator
{
    /** The most specific active zone covering the destination. */
    public function zoneFor(?string $country, ?string $state = null, ?string $city = null, ?string $postcode = null): ?ShippingZone
    {
        return ShippingZone::where('is_active', true)
            ->with('methods')
            ->get()
            ->filter(fn (ShippingZone $zone) => $zone->matches($country, $state, $city, $postcode))
            ->sortByDesc(fn (ShippingZone $zone) => [$zone->specificity(), -$zone->sort_order])
            ->first();
    }

    /**
     * Available shipping options for a cart + destination.
     *
     * @return array<int, array{id:int, title:string, cost:float, delivery_estimate:?string, type:string}>
     */
    public function optionsFor(Cart $cart, array $destination, bool $freeShippingCoupon = false): array
    {
        $zone = $this->zoneFor(
            $destination['country'] ?? null,
            $destination['state'] ?? null,
            $destination['city'] ?? null,
            $destination['postal_code'] ?? null,
        );

        if (! $zone) {
            return [];
        }

        $subtotal = $cart->subtotal();
        $weight = $cart->totalWeight();
        $totalQty = (int) $cart->items->sum('qty');
        $classSlugs = $cart->items
            ->map(fn ($item) => $item->product?->shippingClass?->slug)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $postcode = $destination['postal_code'] ?? null;
        $customerRole = auth()->user()?->getRoleNames()->first() ?? 'guest';

        return $zone->activeMethods()->get()
            ->map(function (ShippingMethod $method) use ($subtotal, $weight, $totalQty, $classSlugs, $postcode, $customerRole, $freeShippingCoupon) {
                // Table-rate conditions (qty, weight, class, role, day/time, postcode).
                if (! $method->matchesConditions($subtotal, $weight, $totalQty, $classSlugs, $customerRole, $postcode)) {
                    return null;
                }

                $cost = $method->costFor($subtotal, $weight, $classSlugs);

                if ($cost === null) {
                    return null;
                }

                if ($freeShippingCoupon && $method->type !== 'local_pickup') {
                    $cost = 0.0;
                }

                return [
                    'id' => $method->id,
                    'title' => $method->title,
                    'type' => $method->type,
                    'cost' => $cost,
                    'delivery_estimate' => $method->delivery_estimate,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
