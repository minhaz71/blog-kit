<?php

namespace App\Services\Payments;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentRule;
use App\Models\User;

/**
 * Filters payment methods by rule + computes payment-based fee/discount
 * adjustments to the checkout total.
 */
class PaymentRuleService
{
    /** Rules that apply to the given (payment, cart, destination, shipping) context. */
    public function applicableRules(
        string $paymentMethod,
        Cart $cart,
        array $destination,
        ?int $shippingMethodId,
        ?User $user = null,
        ?string $email = null,
    ): array {
        $subtotal = $cart->subtotal();
        $isFirst = $this->isFirstOrder($user, $email);

        return PaymentRule::active()
            ->forPayment($paymentMethod)
            ->orderBy('priority')
            ->get()
            ->filter(function (PaymentRule $rule) use ($paymentMethod, $subtotal, $destination, $shippingMethodId, $isFirst) {
                [$ok] = $rule->matches($paymentMethod, $subtotal, $destination, $shippingMethodId, $isFirst);

                return $ok;
            })
            ->all();
    }

    /**
     * True when the given payment method is allowed for the current cart context.
     * If ANY active rule for this payment method exists, at least one must match.
     */
    public function isPaymentAllowed(
        string $paymentMethod,
        Cart $cart,
        array $destination,
        ?int $shippingMethodId,
        ?User $user = null,
        ?string $email = null,
    ): bool {
        $hasAnyRule = PaymentRule::active()->forPayment($paymentMethod)->exists();
        if (! $hasAnyRule) {
            return true;  // Unconstrained payment methods are always allowed.
        }

        return count($this->applicableRules($paymentMethod, $cart, $destination, $shippingMethodId, $user, $email)) > 0;
    }

    /**
     * Compute total adjustment (positive fee, negative discount) plus flags:
     * [amount:float, free_shipping:bool, disallow_coupons:bool, label:?string]
     * All applicable rules stack.
     */
    public function adjustmentFor(
        string $paymentMethod,
        Cart $cart,
        array $destination,
        ?int $shippingMethodId,
        ?User $user = null,
        ?string $email = null,
    ): array {
        $rules = $this->applicableRules($paymentMethod, $cart, $destination, $shippingMethodId, $user, $email);

        $fee = 0.0;
        $discount = 0.0;
        $freeShipping = false;
        $disallowCoupons = false;
        $labels = [];

        $subtotal = $cart->subtotal();

        foreach ($rules as $rule) {
            $fee += (float) $rule->fee_amount;
            $discount += (float) $rule->discount_amount;
            if ((float) $rule->discount_percent > 0) {
                $discount += $subtotal * ((float) $rule->discount_percent / 100);
            }
            if ($rule->free_shipping) {
                $freeShipping = true;
            }
            if ($rule->disallow_coupons) {
                $disallowCoupons = true;
            }
            if ($rule->customer_message) {
                $labels[] = $rule->customer_message;
            }
        }

        return [
            'amount' => round($fee - $discount, 2),
            'fee' => round($fee, 2),
            'discount' => round($discount, 2),
            'free_shipping' => $freeShipping,
            'disallow_coupons' => $disallowCoupons,
            'label' => $labels ? implode(' · ', $labels) : null,
        ];
    }

    /** Available payment methods for this cart+destination, from the given candidate list. */
    public function availablePaymentMethods(
        array $paymentMethods,
        Cart $cart,
        array $destination,
        ?int $shippingMethodId,
        ?User $user = null,
        ?string $email = null,
    ): array {
        return array_values(array_filter(
            $paymentMethods,
            fn (string $key) => $this->isPaymentAllowed($key, $cart, $destination, $shippingMethodId, $user, $email),
        ));
    }

    /**
     * Shipping methods available for this payment method: the payment rules that
     * apply may restrict / block certain shipping methods.
     */
    public function filterShippingMethodsForPayment(array $shippingOptions, string $paymentMethod, Cart $cart, array $destination, ?User $user = null, ?string $email = null): array
    {
        $rules = PaymentRule::active()->forPayment($paymentMethod)->orderBy('priority')->get();
        if ($rules->isEmpty()) {
            return $shippingOptions;
        }

        $isFirst = $this->isFirstOrder($user, $email);
        $subtotal = $cart->subtotal();

        return array_values(array_filter($shippingOptions, function (array $opt) use ($rules, $paymentMethod, $subtotal, $destination, $isFirst) {
            foreach ($rules as $rule) {
                [$ok] = $rule->matches($paymentMethod, $subtotal, $destination, $opt['id'], $isFirst);
                if (! $ok) {
                    // If ANY rule with an explicit block-list rejects this method, filter it out.
                    if ($rule->blocked_shipping_methods && in_array((int) $opt['id'], array_map('intval', $rule->blocked_shipping_methods ?? []), true)) {
                        return false;
                    }
                    if ($rule->allowed_shipping_methods && ! in_array((int) $opt['id'], array_map('intval', $rule->allowed_shipping_methods ?? []), true)) {
                        return false;
                    }
                }
            }

            return true;
        }));
    }

    protected function isFirstOrder(?User $user, ?string $email): bool
    {
        if ($user) {
            return ! $user->orders()->whereNotIn('status', ['cancelled', 'failed'])->exists();
        }
        if ($email) {
            return ! Order::where('customer_email', $email)->whereNotIn('status', ['cancelled', 'failed'])->exists();
        }

        return true;
    }
}
