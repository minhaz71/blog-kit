<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\Email\EmailService;

class SendOrderStatusEmail
{
    /** Order status → customer email template key. */
    protected array $map = [
        'processing' => 'order_processing',
        'completed' => 'order_completed',
        'cancelled' => 'order_cancelled',
        'refunded' => 'order_refunded',
        'failed' => 'order_failed',
        'on_hold' => 'order_on_hold',
    ];

    public function __construct(protected EmailService $emails) {}

    public function handle(OrderStatusChanged $event): void
    {
        $templateKey = $this->map[$event->to] ?? null;

        if ($templateKey) {
            $this->emails->sendOrderEmail($templateKey, $event->order);
        }
    }
}
