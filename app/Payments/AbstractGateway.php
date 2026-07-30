<?php

namespace App\Payments;

use App\Models\Order;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;

abstract class AbstractGateway implements PaymentGateway
{
    /** Read a gateway setting: payments.{key}_{field}, e.g. payments.stripe_enabled */
    protected function config(string $field, mixed $default = null): mixed
    {
        return setting("payments.{$this->key()}_{$field}", $default);
    }

    public function title(): string
    {
        return (string) $this->config('title', $this->defaultTitle());
    }

    public function description(): ?string
    {
        return $this->config('description');
    }

    public function instructions(): ?string
    {
        return $this->config('instructions');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config('enabled', false);
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        return PaymentResult::failed('Webhooks not supported by '.$this->key());
    }

    public function refund(Order $order, float $amount): PaymentResult
    {
        return PaymentResult::failed('Refunds not supported by '.$this->key());
    }

    abstract protected function defaultTitle(): string;

    protected function logTransaction(Order $order, string $type, string $status, ?string $transactionId, array $payload = [], ?float $amount = null): void
    {
        $order->transactions()->create([
            'gateway' => $this->key(),
            'type' => $type,
            'amount' => $amount ?? (float) $order->total,
            'currency' => $order->currency,
            'transaction_id' => $transactionId,
            'status' => $status,
            'payload' => $payload ?: null,
        ]);
    }
}
