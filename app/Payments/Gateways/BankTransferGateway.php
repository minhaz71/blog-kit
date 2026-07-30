<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\AbstractGateway;
use App\Payments\PaymentResult;

class BankTransferGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'bank_transfer';
    }

    protected function defaultTitle(): string
    {
        return 'Direct Bank Transfer';
    }

    public function instructions(): ?string
    {
        return $this->config('instructions', 'Transfer the order total to our bank account. Your order ships once the funds clear. Use your order number as the payment reference.');
    }

    public function initiate(Order $order): PaymentResult
    {
        $order->updateStatus('on_hold');
        $this->logTransaction($order, 'payment', 'pending', null);

        return PaymentResult::pending($this->instructions(), $order);
    }
}
