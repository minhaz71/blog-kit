<?php

namespace App\Payments;

use App\Models\Order;

class PaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $redirectUrl = null,
        public ?string $transactionId = null,
        public ?string $message = null,
        public ?Order $order = null,
        /** True when payment is confirmed (vs. merely initiated / pending offline). */
        public bool $paid = false,
    ) {}

    public static function redirect(string $url): self
    {
        return new self(success: true, redirectUrl: $url);
    }

    public static function pending(?string $message = null, ?Order $order = null): self
    {
        return new self(success: true, message: $message, order: $order);
    }

    public static function paid(?string $transactionId = null, ?Order $order = null): self
    {
        return new self(success: true, transactionId: $transactionId, order: $order, paid: true);
    }

    public static function failed(string $message): self
    {
        return new self(success: false, message: $message);
    }
}
