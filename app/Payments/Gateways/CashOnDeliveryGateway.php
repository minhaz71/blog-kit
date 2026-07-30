<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\AbstractGateway;
use App\Payments\PaymentResult;

class CashOnDeliveryGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'cod';
    }

    protected function defaultTitle(): string
    {
        return 'Cash on Delivery';
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config('enabled', true);
    }

    public function initiate(Order $order): PaymentResult
    {
        $order->updateStatus('processing');
        $this->logTransaction($order, 'payment', 'pending', null);

        return PaymentResult::pending('Pay with cash when your order is delivered.', $order);
    }
}
