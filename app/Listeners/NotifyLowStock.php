<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\Email\EmailService;

class NotifyLowStock
{
    public function __construct(protected EmailService $emails) {}

    public function handle(OrderPlaced $event): void
    {
        $adminEmail = (string) setting('general.admin_email', config('mail.from.address'));

        if (! $adminEmail) {
            return;
        }

        foreach ($event->order->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->manage_stock) {
                continue;
            }

            $product->refresh();

            if ($product->stock_qty <= 0) {
                $this->emails->send('out_of_stock_admin', $adminEmail, [
                    'product_name' => $product->name,
                    'sku' => (string) $product->sku,
                    'stock_qty' => (string) $product->stock_qty,
                    'store_name' => (string) setting('general.store_name', config('app.name')),
                ]);
            } elseif ($product->isLowStock()) {
                $this->emails->send('low_stock_admin', $adminEmail, [
                    'product_name' => $product->name,
                    'sku' => (string) $product->sku,
                    'stock_qty' => (string) $product->stock_qty,
                    'store_name' => (string) setting('general.store_name', config('app.name')),
                ]);
            }
        }
    }
}
