<?php

namespace App\Services\Tax;

use App\Models\TaxRate;

class TaxCalculator
{
    /** Find the best-matching active tax rate for a destination + tax class. */
    public function rateFor(array $destination, string $taxClass = 'standard'): ?TaxRate
    {
        $matches = TaxRate::where('is_active', true)
            ->where('tax_class', $taxClass)
            ->get()
            ->filter(function (TaxRate $rate) use ($destination) {
                $eq = fn (?string $ruleValue, ?string $destValue) => $ruleValue === null
                    || strcasecmp($ruleValue, (string) $destValue) === 0;

                return $eq($rate->country, $destination['country'] ?? null)
                    && $eq($rate->state, $destination['state'] ?? null)
                    && $eq($rate->city, $destination['city'] ?? null)
                    && $eq($rate->postal_code, $destination['postal_code'] ?? null);
            });

        return $matches->sortBy('priority')->first();
    }

    public function taxFor(float $taxableAmount, float $shippingAmount, array $destination, string $taxClass = 'standard'): float
    {
        $rate = $this->rateFor($destination, $taxClass);

        if (! $rate) {
            return 0.0;
        }

        $base = $taxableAmount + ($rate->applies_to_shipping ? $shippingAmount : 0);

        return round($base * ((float) $rate->rate / 100), 2);
    }
}
