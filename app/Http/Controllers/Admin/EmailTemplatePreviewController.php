<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\EmailTemplateResource;
use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Services\Email\EmailService;
use App\Support\StoreBranding;

/**
 * Renders a fully-branded preview of an email template — exactly what the
 * customer/admin receives — using a real recent order when available, or
 * representative sample data otherwise. Opened from the Email Templates screen.
 */
class EmailTemplatePreviewController extends Controller
{
    public function __invoke(EmailTemplate $template, EmailService $emails)
    {
        abort_unless(EmailTemplateResource::canAccess(), 403);

        $isCartTemplate = $template->key === 'abandoned_cart';
        $isAdmin = $template->recipient === 'admin';
        $order = Order::with('items')->latest()->first();
        $cartUrl = rtrim((string) config('app.url'), '/').'/cart';

        if ($order) {
            $vars = $emails->orderVars($order);
            $data = $emails->orderData($order);
        } else {
            $vars = $this->sampleVars();
            $data = $this->sampleData();
        }

        $vars += ['cart_url' => $cartUrl, 'item_count' => 2];

        if ($isCartTemplate) {
            // Cart reminder preview: saved cart + "Complete your order", NOT
            // order tracker/invoice — mirrors the real abandoned-cart email.
            $data = [
                'audience' => 'cart',
                'cart_url' => $cartUrl,
                'subtotal' => $data['subtotal'] ?? price_format(150),
                'items' => $data['items'] ?? $this->sampleData()['items'],
            ];
        } elseif ($isAdmin) {
            $data['audience'] = 'admin';
            if ($order) {
                $data = array_merge($data, $emails->adminExtras($order));
            } else {
                $data['billing_address'] = $data['shipping_address'];
                $data['customer'] = ['name' => 'Sam Shopper', 'email' => 'sam@example.com', 'phone' => '+971 50 000 0000', 'orders_count' => 4, 'lifetime_total' => price_format(600)];
            }
        } else {
            $data['audience'] = 'customer';
        }

        $rendered = $template->render($vars);

        return (new TemplatedMail(
            mailSubject: $rendered['subject'],
            heading: $rendered['heading'] ?: $rendered['subject'],
            body: $rendered['body'],
            order: $data,
        ))->render();
    }

    protected function sampleVars(): array
    {
        return [
            'customer_name' => 'Sam Shopper',
            'order_number' => 'SAMPLE-1001',
            'order_total' => price_format(150),
            'order_status' => 'Processing',
            'payment_method' => 'Card On Delivery',
            'store_name' => StoreBranding::name(),
            'store_url' => config('app.url'),
            'order_url' => config('app.url'),
            'invoice_url' => '#',
            'refund_amount' => price_format(150),
            'product_name' => 'IQOS TEREA Amber',
            'stock_qty' => 3,
        ];
    }

    protected function sampleData(): array
    {
        return [
            'number' => 'SAMPLE-1001',
            'url' => config('app.url'),
            'invoice_url' => '#',
            'step' => 2,
            'items' => [
                ['name' => 'IQOS TEREA Amber', 'qty' => 2, 'total' => price_format(120), 'options' => null, 'image' => null],
                ['name' => 'IQOS ILUMA One', 'qty' => 1, 'total' => price_format(30), 'options' => null, 'image' => null],
            ],
            'subtotal' => price_format(150),
            'discount' => null,
            'shipping' => price_format(0),
            'tax' => null,
            'payment_fee' => null,
            'payment_fee_label' => 'Payment fee',
            'total' => price_format(150),
            'shipping_address' => ['first_name' => 'Sam', 'last_name' => 'Shopper', 'address_line_1' => '123 Marina Walk', 'city' => 'Dubai', 'country' => 'UAE'],
            'payment_label' => 'Card On Delivery',
            'related' => [],
        ];
    }
}
