<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\Email\EmailService;

class SendOrderPlacedEmails
{
    public function __construct(protected EmailService $emails) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        // Customer confirmation
        $this->emails->sendOrderEmail('order_confirmed', $order);

        // Admin notification — the "Admin notification recipients" field in
        // Email settings, which accepts a comma-separated list (up to 20
        // addresses). Falls back to the sender / mail-from address.
        $admins = (string) (
            setting('emails.admin_recipient')
            ?: setting('emails.from_email')
            ?: config('mail.from.address')
        );

        if (trim($admins) !== '') {
            // EmailService splits the comma-separated list and mails every
            // valid address.
            $this->emails->sendOrderEmail('new_order_admin', $order, $admins);
        }
    }
}
