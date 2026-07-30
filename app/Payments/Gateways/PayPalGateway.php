<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\AbstractGateway;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayPal Orders v2 API via HTTP (no SDK dependency).
 * Set payments.paypal_client_id / paypal_secret / paypal_mode (sandbox|live)
 * and paypal_webhook_id in admin settings.
 */
class PayPalGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'paypal';
    }

    protected function defaultTitle(): string
    {
        return 'PayPal';
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    protected function apiBase(): string
    {
        return $this->config('mode', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    protected function accessToken(): ?string
    {
        $response = Http::asForm()
            ->withBasicAuth((string) $this->config('client_id'), (string) $this->config('secret'))
            ->post($this->apiBase().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->successful() ? $response->json('access_token') : null;
    }

    public function initiate(Order $order): PaymentResult
    {
        $token = $this->accessToken();

        if (! $token) {
            return PaymentResult::failed('PayPal is temporarily unavailable.');
        }

        $response = Http::withToken($token)->post($this->apiBase().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order->id,
                'custom_id' => $order->order_number,
                'amount' => [
                    'currency_code' => $order->currency,
                    'value' => number_format((float) $order->total, 2, '.', ''),
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url' => route('checkout.paypal-return', $order->order_number),
                        'cancel_url' => route('checkout.payment-failed', $order->order_number),
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            Log::warning('PayPal order creation failed', ['order' => $order->order_number, 'response' => $response->json()]);

            return PaymentResult::failed('Unable to start the PayPal payment.');
        }

        $approveUrl = collect($response->json('links', []))->firstWhere('rel', 'payer-action')['href']
            ?? collect($response->json('links', []))->firstWhere('rel', 'approve')['href']
            ?? null;

        if (! $approveUrl) {
            return PaymentResult::failed('Unable to start the PayPal payment.');
        }

        $this->logTransaction($order, 'payment', 'pending', $response->json('id'));

        return PaymentResult::redirect($approveUrl);
    }

    /** Capture after the buyer approves (return URL flow). */
    public function capture(Order $order, string $paypalOrderId): PaymentResult
    {
        $token = $this->accessToken();

        if (! $token) {
            return PaymentResult::failed('PayPal is temporarily unavailable.');
        }

        $response = Http::withToken($token)
            ->withBody('', 'application/json')
            ->post($this->apiBase()."/v2/checkout/orders/{$paypalOrderId}/capture");

        $capture = $response->json('purchase_units.0.payments.captures.0', []);

        if ($response->failed() || ($capture['status'] ?? '') !== 'COMPLETED') {
            $this->logTransaction($order, 'payment', 'failed', $paypalOrderId, (array) $response->json());

            return PaymentResult::failed('PayPal payment was not completed.');
        }

        // Amount verification before marking paid.
        $paid = (float) ($capture['amount']['value'] ?? 0);

        if ($paid + 0.001 < (float) $order->total) {
            $this->logTransaction($order, 'payment', 'failed', $paypalOrderId, ['reason' => 'amount_mismatch', 'paid' => $paid]);

            return PaymentResult::failed('Paid amount does not match order total.');
        }

        $order->markPaid($capture['id'] ?? $paypalOrderId);
        $this->logTransaction($order, 'payment', 'success', $capture['id'] ?? $paypalOrderId);

        return PaymentResult::paid($capture['id'] ?? $paypalOrderId, $order);
    }

    /** Verify webhook authenticity via PayPal's verify-webhook-signature API. */
    public function handleWebhook(Request $request): PaymentResult
    {
        $token = $this->accessToken();
        $webhookId = (string) $this->config('webhook_id');

        if (! $token || $webhookId === '') {
            return PaymentResult::failed('Webhook not configured');
        }

        $verify = Http::withToken($token)->post($this->apiBase().'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('Paypal-Auth-Algo'),
            'cert_url' => $request->header('Paypal-Cert-Url'),
            'transmission_id' => $request->header('Paypal-Transmission-Id'),
            'transmission_sig' => $request->header('Paypal-Transmission-Sig'),
            'transmission_time' => $request->header('Paypal-Transmission-Time'),
            'webhook_id' => $webhookId,
            'webhook_event' => $request->json()->all(),
        ]);

        if ($verify->json('verification_status') !== 'SUCCESS') {
            Log::warning('PayPal webhook verification failed', ['ip' => $request->ip()]);

            return PaymentResult::failed('Invalid signature');
        }

        $event = $request->json()->all();

        if (($event['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED') {
            $orderNumber = $event['resource']['custom_id'] ?? null;
            $order = $orderNumber ? Order::where('order_number', $orderNumber)->first() : null;

            if ($order && ! $order->isPaid()) {
                $order->markPaid($event['resource']['id'] ?? null);
                $this->logTransaction($order, 'payment', 'success', $event['resource']['id'] ?? null);
            }

            return PaymentResult::paid($event['resource']['id'] ?? null, $order);
        }

        return PaymentResult::pending('Event ignored');
    }

    public function refund(Order $order, float $amount): PaymentResult
    {
        $token = $this->accessToken();

        if (! $token || ! $order->transaction_id) {
            return PaymentResult::failed('Refund unavailable for this order.');
        }

        $response = Http::withToken($token)->post(
            $this->apiBase()."/v2/payments/captures/{$order->transaction_id}/refund",
            ['amount' => ['currency_code' => $order->currency, 'value' => number_format($amount, 2, '.', '')]],
        );

        if ($response->failed()) {
            $this->logTransaction($order, 'refund', 'failed', null, (array) $response->json(), $amount);

            return PaymentResult::failed('PayPal refund failed.');
        }

        $this->logTransaction($order, 'refund', 'success', $response->json('id'), [], $amount);
        $order->forceFill([
            'payment_status' => $amount >= (float) $order->total ? 'refunded' : 'partially_refunded',
        ])->save();

        return PaymentResult::paid($response->json('id'), $order);
    }
}
