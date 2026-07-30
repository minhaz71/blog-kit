<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    public const TYPES = ['flat_rate', 'free_shipping', 'local_pickup', 'weight_based', 'value_based'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'weight_tiers' => 'array',
            'class_costs' => 'array',
            'conditions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * True when this method's conditions (qty, weight, subtotal, class, role,
     * day/time, postcode) accept the given cart+destination.
     */
    public function matchesConditions(float $subtotal, float $weightKg, int $itemQty, array $classSlugs, ?string $customerRole, ?string $postcode): bool
    {
        $c = $this->conditions ?? [];
        if (empty($c) || ! is_array($c)) {
            return true;
        }

        if (isset($c['min_qty']) && $itemQty < (int) $c['min_qty']) {
            return false;
        }
        if (isset($c['max_qty']) && $itemQty > (int) $c['max_qty']) {
            return false;
        }
        if (isset($c['min_weight_kg']) && $weightKg < (float) $c['min_weight_kg']) {
            return false;
        }
        if (isset($c['max_weight_kg']) && $weightKg > (float) $c['max_weight_kg']) {
            return false;
        }
        if (isset($c['min_subtotal']) && $subtotal < (float) $c['min_subtotal']) {
            return false;
        }
        if (isset($c['max_subtotal']) && $subtotal > (float) $c['max_subtotal']) {
            return false;
        }
        if (! empty($c['allowed_shipping_class_slugs']) && is_array($c['allowed_shipping_class_slugs'])
            && empty(array_intersect($c['allowed_shipping_class_slugs'], $classSlugs))) {
            return false;
        }
        if (! empty($c['allowed_customer_roles']) && is_array($c['allowed_customer_roles'])
            && ! in_array($customerRole ?? 'guest', $c['allowed_customer_roles'], true)) {
            return false;
        }
        if (! empty($c['day_of_week']) && is_array($c['day_of_week'])) {
            $today = (int) now()->dayOfWeekIso;  // 1=Mon..7=Sun
            if (! in_array($today, array_map('intval', $c['day_of_week']), true)) {
                return false;
            }
        }
        if (! empty($c['time_start']) && ! empty($c['time_end'])) {
            $now = now()->format('H:i');
            if ($now < (string) $c['time_start'] || $now > (string) $c['time_end']) {
                return false;
            }
        }
        if (! empty($c['allowed_postcodes']) && is_array($c['allowed_postcodes'])) {
            if (! $this->postcodeMatches($postcode, $c['allowed_postcodes'])) {
                return false;
            }
        }
        if (! empty($c['blocked_postcodes']) && is_array($c['blocked_postcodes'])) {
            if ($this->postcodeMatches($postcode, $c['blocked_postcodes'])) {
                return false;
            }
        }

        return true;
    }

    /** Exact or prefix (trailing "*") match against a postcode list. */
    protected function postcodeMatches(?string $postcode, array $patterns): bool
    {
        if (! $postcode) {
            return false;
        }
        $postcode = strtoupper(trim($postcode));
        foreach ($patterns as $pattern) {
            $pattern = strtoupper(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($postcode, rtrim($pattern, '*'))) {
                    return true;
                }
            } elseif ($pattern === $postcode) {
                return true;
            }
        }

        return false;
    }

    /** Returns the cost for this cart, or null when the method doesn't apply. */
    public function costFor(float $subtotal, float $weightKg, array $shippingClassSlugs = []): ?float
    {
        $cost = match ($this->type) {
            'free_shipping' => ($this->min_order_amount === null || $subtotal >= (float) $this->min_order_amount) ? 0.0 : null,
            'local_pickup' => (float) $this->cost,
            'flat_rate' => (float) $this->cost,
            'weight_based' => $this->weightTierCost($weightKg),
            'value_based' => ($this->min_order_amount === null || $subtotal >= (float) $this->min_order_amount) ? (float) $this->cost : null,
            default => null,
        };

        if ($cost === null) {
            return null;
        }

        foreach ($shippingClassSlugs as $slug) {
            $cost += (float) ($this->class_costs[$slug] ?? 0);
        }

        return round($cost, 2);
    }

    protected function weightTierCost(float $weightKg): ?float
    {
        $tiers = collect($this->weight_tiers ?? [])->sortBy('up_to_kg');

        if ($tiers->isEmpty()) {
            return (float) $this->cost;
        }

        foreach ($tiers as $tier) {
            if ($weightKg <= (float) $tier['up_to_kg']) {
                return (float) $tier['cost'];
            }
        }

        // Heavier than the last tier: last tier price applies.
        return (float) $tiers->last()['cost'];
    }
}
