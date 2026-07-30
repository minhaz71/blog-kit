<?php

namespace App\Models;

use App\Models\Concerns\NormalizesJsonLists;
use Illuminate\Database\Eloquent\Model;

class PaymentRule extends Model
{
    use NormalizesJsonLists;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allowed_countries' => 'array',
            'blocked_countries' => 'array',
            'allowed_cities' => 'array',
            'blocked_cities' => 'array',
            'allowed_shipping_methods' => 'array',
            'blocked_shipping_methods' => 'array',
            'is_active' => 'boolean',
            'first_order_only' => 'boolean',
            'disallow_coupons' => 'boolean',
            'free_shipping' => 'boolean',
            'fee_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Rules that could match this payment method (wildcard * counts). */
    public function scopeForPayment($q, string $paymentMethod)
    {
        return $q->where(function ($qq) use ($paymentMethod) {
            $qq->where('payment_method', $paymentMethod)
                ->orWhere('payment_method', '*');
        });
    }

    /**
     * Whether this rule allows a given (payment, cart, destination) combination.
     * Returns [ok, reason].
     */
    public function matches(
        string $paymentMethod,
        float $subtotal,
        array $destination,
        ?int $shippingMethodId,
        bool $isFirstOrder,
    ): array {
        if ($this->payment_method !== '*' && $this->payment_method !== $paymentMethod) {
            return [false, 'payment_method_mismatch'];
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return [false, 'below_min_order'];
        }
        if ($this->max_order_amount !== null && $subtotal > (float) $this->max_order_amount) {
            return [false, 'above_max_order'];
        }

        $country = strtoupper((string) ($destination['country'] ?? ''));
        if (! $this->listAllows($this->allowed_countries, $country)) {
            return [false, 'country_not_allowed'];
        }
        if ($this->listBlocks($this->blocked_countries, $country)) {
            return [false, 'country_blocked'];
        }

        $city = strtolower((string) ($destination['city'] ?? ''));
        if (! $this->listAllows($this->allowed_cities, $city, caseInsensitive: true)) {
            return [false, 'city_not_allowed'];
        }
        if ($this->listBlocks($this->blocked_cities, $city, caseInsensitive: true)) {
            return [false, 'city_blocked'];
        }

        if ($shippingMethodId) {
            if (! $this->idListAllows($this->allowed_shipping_methods, $shippingMethodId)) {
                return [false, 'shipping_method_not_allowed'];
            }
            if ($this->idListBlocks($this->blocked_shipping_methods, $shippingMethodId)) {
                return [false, 'shipping_method_blocked'];
            }
        }

        if ($this->first_order_only && ! $isFirstOrder) {
            return [false, 'not_first_order'];
        }

        return [true, null];
    }

    protected function listAllows(?array $list, string $needle, bool $caseInsensitive = false): bool
    {
        $list = $this->normalized($list);
        if (! $list) {
            return true;
        }
        if ($caseInsensitive) {
            return in_array(strtolower($needle), array_map('strtolower', $list), true);
        }

        return in_array($needle, $list, true);
    }

    protected function listBlocks(?array $list, string $needle, bool $caseInsensitive = false): bool
    {
        $list = $this->normalized($list);
        if (! $list) {
            return false;
        }
        if ($caseInsensitive) {
            return in_array(strtolower($needle), array_map('strtolower', $list), true);
        }

        return in_array($needle, $list, true);
    }

    protected function idListAllows(?array $list, int $id): bool
    {
        $list = $this->normalized($list);
        if (! $list) {
            return true;
        }

        return in_array((int) $id, array_map('intval', $list), true);
    }

    protected function idListBlocks(?array $list, int $id): bool
    {
        $list = $this->normalized($list);
        if (! $list) {
            return false;
        }

        return in_array((int) $id, array_map('intval', $list), true);
    }
}
