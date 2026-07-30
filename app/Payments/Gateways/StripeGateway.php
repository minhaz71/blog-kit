<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\AbstractGateway;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Checkout integration via the HTTP API (no SDK dependency).
 * Set payments.stripe_secret_key / stripe_webhook_secret in admin settings.
 */
class StripeGateway extends AbstractGateway
{
    protected string $apiBase = 'https://api.stripe.com/v1';

    public function key(): string
    {
        return 'stripe';
    }

    protected function defaultTitle(): string
    {
        return 'Credit / Debit Card (Stripe)';
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    protected function secretKey(): ?string
    {
        return $this->config('secret_key') ?: config('services.stripe.secret');
    }

    public function initiate(Order $order): PaymentResult
    {
        $params = [
            'mode' => 'payment',
            'client_reference_id' => (string) $order->id,
            'customer_email' => $order->customer_email,
            'success_url' => route('checkout.thank-you', $order->order_number),
            'cancel_url' => route('checkout.payment-failed', $order->order_number),
            'metadata' => ['order_number' => $order->order_number],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => (int) round($order->total * 100),
                    'product_data' => ['name' => 'Order '.$order->order_number],
                ],
            ]],
        ];

        $response = Http::asForm()
            ->withToken($this->secretKey())
            ->post("{$this->apiBase}/checkout/sessions", $params);

        if ($response->failed()) {
            Log::warning('Stripe checkout session failed', ['order' => $order->order_number, 'response' => $response->json()]);
            $this->logTransaction($order, 'payment', 'failed', null, (array) $response->json());

            return PaymentResult::failed('Unable to start the card payment. Please try again.');
        }

        $this->logTransaction($order, 'payment', 'pending', $response->json('id'));

        return PaymentResult::redirect($response->json('url'));
    }

    /** Verify the Stripe-Signature header (HMAC-SHA256 over "{t}.{payload}"). */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) $this->config('webhook_secret', config('services.stripe.webhook_secret'));
        $header = (string) $request->header('Stripe-Signature');

        if ($secret === '' || $header === '') {
            return false;
        }

        $parts = collect(explode(',', $header))->mapWithKeys(function ($part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');

            return [$k => $v];
        });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! $timestamp || ! $signature || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        if (! $this->verifyWebhookSignature($request)) {
            Log::warning('Stripe webhook signature verification failed', ['ip' => $request->ip()]);

            return PaymentResult::failed('Invalid signature');
        }

        $event = $request->json()->all();
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        if ($type === 'checkout.session.completed' && ($object['payment_status'] ?? '') === 'paid') {
            $order = Order::find($object['client_reference_id'] ?? 0);

            if (! $order) {
                return PaymentResult::failed('Order not found');
            }

            // Verify amount server-side: never trust an underpaid session.
            $paidAmount = (int) ($object['amount_total'] ?? 0);

            if ($paidAmount < (int) round($order->total * 100)) {
                $this->logTransaction($order, 'payment', 'failed', $object['payment_intent'] ?? null, ['reason' => 'amount_mismatch', 'paid' => $paidAmount]);

                return PaymentResult::failed('Paid amount does not match order total');
            }

            $order->markPaid($object['payment_intent'] ?? $object['id'] ?? null);
            $this->logTransaction($order, 'payment', 'success', $object['payment_intent'] ?? null);

            return PaymentResult::paid($object['payment_intent'] ?? null, $order);
        }

        return PaymentResult::pending('Event ignored');
    }

    public function refund(Order $order, float $amount): PaymentResult
    {
        if (! $order->transaction_id) {
            return PaymentResult::failed('No Stripe transaction on this order.');
        }

        $response = Http::asForm()
            ->withToken($this->secretKey())
            ->post("{$this->apiBase}/refunds", [
                'payment_intent' => $order->transaction_id,
                'amount' => (int) round($amount * 100),
            ]);

        if ($response->failed()) {
            $this->logTransaction($order, 'refund', 'failed', null, (array) $response->json(), $amount);

            return PaymentResult::failed($response->json('error.message', 'Refund failed.'));
        }

        $this->logTransaction($order, 'refund', 'success', $response->json('id'), [], $amount);

        $order->forceFill([
            'payment_status' => $amount >= (float) $order->total ? 'refunded' : 'partially_refunded',
        ])->save();

        return PaymentResult::paid($response->json('id'), $order);
    }
}
