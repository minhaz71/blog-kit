<?php

namespace App\Services\Checkout;

use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ShippingMethod;
use App\Services\Cart\CouponService;
use App\Services\Payments\PaymentRuleService;
use App\Services\Shipping\ShippingCalculator;
use App\Services\Tax\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected CouponService $coupons,
        protected ShippingCalculator $shipping,
        protected TaxCalculator $tax,
        protected PaymentRuleService $paymentRules,
    ) {}

    /**
     * Compute all order totals server-side. Client-sent totals are never used.
     * When $paymentMethod is passed, payment-based fees/discounts and free-shipping
     * rules are folded in.
     *
     * @return array{subtotal:float, discount:float, shipping:float, tax:float, total:float, shipping_title:?string, payment_adjustment:float, payment_label:?string}
     */
    public function totals(Cart $cart, array $destination, ?int $shippingMethodId, ?string $paymentMethod = null): array
    {
        $subtotal = $cart->subtotal();

        // Payment-based adjustment (fee or discount) evaluated first — it may also
        // grant free shipping or block coupons.
        $paymentAdjust = ['amount' => 0.0, 'fee' => 0.0, 'discount' => 0.0, 'free_shipping' => false, 'disallow_coupons' => false, 'label' => null];
        if ($paymentMethod) {
            $paymentAdjust = $this->paymentRules->adjustmentFor(
                $paymentMethod, $cart, $destination, $shippingMethodId,
                auth()->user(), $destination['email'] ?? null,
            );

            // The payment method's OWN surcharge (e.g. "Card payment charge")
            // stacks on top of any rule adjustment and names the summary line.
            $method = \App\Models\PaymentMethod::active()->where('key', $paymentMethod)->first();
            if ($method && $method->hasFee()) {
                $methodFee = $method->feeFor($subtotal);
                $paymentAdjust['fee'] += $methodFee;
                if ($methodFee > 0) {
                    $paymentAdjust['label'] = $method->feeLabel();
                }
            }
        }

        $discount = 0.0;
        $freeShipping = $paymentAdjust['free_shipping'];

        if ($cart->coupon && ! $paymentAdjust['disallow_coupons']) {
            $discount = $this->coupons->discountFor($cart->coupon, $cart);
            if ($this->coupons->grantsFreeShipping($cart->coupon)) {
                $freeShipping = true;
            }
        }

        // Payment-side discounts stack on top of coupon discounts.
        $discount += $paymentAdjust['discount'];

        $shippingCost = 0.0;
        $shippingTitle = null;

        if ($shippingMethodId) {
            $options = collect($this->shipping->optionsFor($cart, $destination, $freeShipping));
            $selected = $options->firstWhere('id', $shippingMethodId);

            if (! $selected) {
                throw ValidationException::withMessages(['shipping_method' => 'The selected shipping method is not available for your address.']);
            }

            $shippingCost = (float) $selected['cost'];
            $shippingTitle = $selected['title'];
        }

        $taxable = max(0, $subtotal - $discount);
        $taxAmount = $this->tax->taxFor($taxable, $shippingCost, $destination);

        $total = max(0, $subtotal - $discount) + $shippingCost + $taxAmount + $paymentAdjust['fee'];

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping' => round($shippingCost, 2),
            'tax' => round($taxAmount, 2),
            'payment_adjustment' => round($paymentAdjust['fee'] - $paymentAdjust['discount'], 2),
            'payment_label' => $paymentAdjust['label'],
            'payment_fee' => round($paymentAdjust['fee'], 2),
            'payment_fee_label' => $paymentAdjust['fee'] > 0 ? ($paymentAdjust['label'] ?: 'Payment fee') : null,
            'total' => round(max(0, $total), 2),
            'shipping_title' => $shippingTitle,
        ];
    }

    /**
     * Place an order from the cart. Locks stock rows, revalidates the coupon,
     * snapshots prices, and empties the cart — all in one transaction.
     */
    public function placeOrder(Cart $cart, array $data): Order
    {
        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        // Duplicate-submission guard: same idempotency key returns the same order.
        if (! empty($data['idempotency_key'])) {
            $existing = Order::where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($cart, $data) {
            $destination = $data['shipping_address'];

            // Re-validate coupon at order time (limits/dates may have changed).
            if ($cart->coupon) {
                $this->coupons->validate($cart->coupon, $cart, auth()->user(), $data['email'] ?? null);
            }

            // Verify the chosen payment method is allowed for this cart+destination+shipping.
            if (! $this->paymentRules->isPaymentAllowed(
                $data['payment_method'], $cart, $destination, $data['shipping_method_id'] ?? null,
                auth()->user(), $data['email'] ?? null,
            )) {
                throw ValidationException::withMessages([
                    'payment_method' => 'This payment method is not available for your order.',
                ]);
            }

            $totals = $this->totals(
                $cart,
                $destination,
                $data['shipping_method_id'] ?? null,
                $data['payment_method'] ?? null,
            );

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => auth()->id(),
                'status' => 'pending',
                'currency' => store_currency(),
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'shipping_total' => $totals['shipping'],
                'tax_total' => $totals['tax'],
                'payment_fee' => $totals['payment_fee'],
                'payment_fee_label' => $totals['payment_fee_label'],
                'total' => $totals['total'],
                'coupon_id' => $cart->coupon?->id,
                'coupon_code' => $cart->coupon?->code,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'shipping_method' => $totals['shipping_title'],
                'billing_address' => $data['billing_address'],
                'shipping_address' => $data['shipping_address'],
                'customer_email' => $data['email'],
                'customer_phone' => $data['phone'] ?? null,
                'customer_note' => $data['note'] ?? null,
                'ip_address' => request()?->ip(),
                'user_agent' => str((string) request()?->userAgent())->limit(495, '')->toString(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($cart->items as $item) {
                // Lock and revalidate stock inside the transaction.
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $variation = $item->product_variation_id
                    ? ProductVariation::lockForUpdate()->findOrFail($item->product_variation_id)
                    : null;

                $available = $variation
                    ? ($variation->isInStock() && $variation->stock_qty >= $item->qty)
                    : (! $product->manage_stock || ($product->stock_status === 'in_stock' && $product->stock_qty >= $item->qty));

                if (! $available) {
                    throw ValidationException::withMessages([
                        'cart' => "\"{$product->name}\" went out of stock while you were checking out.",
                    ]);
                }

                $unitPrice = $variation?->currentPrice() ?? $product->currentPrice();

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variation_id' => $variation?->id,
                    'name' => $product->name,
                    'sku' => $variation?->sku ?? $product->sku,
                    'qty' => $item->qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => round($unitPrice * $item->qty, 2),
                    'total' => round($unitPrice * $item->qty, 2),
                    'options' => $variation?->optionMap(),
                ]);

                $variation ? $variation->decrementStock($item->qty) : $product->decrementStock($item->qty);
                $product->increment('sales_count', $item->qty);
            }

            if ($cart->coupon) {
                $cart->coupon->recordUsage(auth()->user(), $data['email'], $order->id);
            }

            $cart->items()->delete();
            $cart->update([
                'status' => 'converted',
                'coupon_id' => null,
                'order_id' => $order->id,
                // If this cart had already received a reminder, this order is a
                // recovered sale — the dashboard tracks recovered revenue by it.
                'recovered_at' => $cart->reminder_stage > 0 ? now() : $cart->recovered_at,
            ]);

            DB::afterCommit(fn () => event(new OrderPlaced($order)));

            return $order;
        });
    }
}
