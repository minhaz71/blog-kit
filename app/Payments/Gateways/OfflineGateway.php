<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Payments\AbstractGateway;
use App\Payments\PaymentResult;

/**
 * Adapts an admin-defined PaymentMethod record to the PaymentGateway
 * contract, so custom offline methods (cash/card on delivery, bank transfer)
 * flow through the same checkout pipeline as the coded online gateways.
 *
 * "Offline" = no online capture: the order is confirmed immediately (payment
 * collected on delivery / manually) and the method's instructions are shown.
 */
class OfflineGateway extends AbstractGateway
{
    public function __construct(protected PaymentMethod $method) {}

    public function key(): string
    {
        return $this->method->key;
    }

    protected function defaultTitle(): string
    {
        return $this->method->name;
    }

    public function title(): string
    {
        return $this->method->name;
    }

    public function description(): ?string
    {
        return $this->method->description;
    }

    public function instructions(): ?string
    {
        return $this->method->instructions;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->method->is_active;
    }

    public function initiate(Order $order): PaymentResult
    {
        // Offline flow: confirm the order now; money is collected on delivery.
        $status = in_array($this->method->mark_as, ['pending', 'processing'], true)
            ? $this->method->mark_as
            : 'processing';

        $order->updateStatus($status);
        $this->logTransaction($order, 'payment', 'pending', null);

        return PaymentResult::pending(
            $this->method->instructions ?: 'Your order is confirmed. Payment will be collected on delivery.',
            $order,
        );
    }
}
