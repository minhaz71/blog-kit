<?php

namespace App\Services\Email;

use App\Mail\TemplatedMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EmailService
{
    /**
     * Send an admin-editable template. $vars are substituted into
     * {{placeholders}}; $orderData (optional) renders the order table.
     *
     * $to may be a single address, an array, or a comma/semicolon/space
     * separated list (the admin-recipients field accepts up to 20 addresses).
     *
     * Mail is sent SYNCHRONOUSLY (not queued): shared/LiteSpeed hosting has no
     * always-on queue worker, so a queued mail would sit in the `jobs` table
     * and never be delivered. Sending inline is the same path the working
     * "test email" uses, so order mail now behaves identically.
     */
    public function send(string $templateKey, string|array $to, array $vars = [], array $orderData = []): bool
    {
        $template = EmailTemplate::where('key', $templateKey)->where('is_active', true)->first();

        if (! $template) {
            return false;
        }

        // Base recipients, plus any fixed "custom recipients" configured on the
        // template itself (e.g. a warehouse/accounts inbox that should always
        // receive this email). De-duped + capped by normalizeRecipients.
        $recipients = static::normalizeRecipients(array_merge(
            is_array($to) ? $to : [$to],
            $template->custom_recipients ? [$template->custom_recipients] : [],
        ));

        if ($recipients === []) {
            return false;
        }

        $rendered = $template->render($vars);
        $logTo = Str::limit(implode(', ', $recipients), 250, '');

        try {
            Mail::to($recipients)->send(new TemplatedMail(
                mailSubject: $rendered['subject'],
                heading: $rendered['heading'] ?: $rendered['subject'],
                body: $rendered['body'],
                order: $orderData,
            ));

            EmailLog::create(['to_email' => $logTo, 'subject' => $rendered['subject'], 'template_key' => $templateKey, 'status' => 'sent']);

            return true;
        } catch (Throwable $e) {
            EmailLog::create(['to_email' => $logTo, 'subject' => $rendered['subject'], 'template_key' => $templateKey, 'status' => 'failed', 'error' => $e->getMessage()]);
            report($e);

            return false;
        }
    }

    /**
     * Turn a single address / array / delimited list into a clean, de-duped
     * list of valid email addresses (capped at 20 — the admin-recipients cap).
     */
    public static function normalizeRecipients(string|array $to): array
    {
        // Accept a single address, an array, OR array elements that are
        // themselves comma/semicolon/space-delimited lists — split them all.
        $raw = [];
        foreach ((is_array($to) ? $to : [$to]) as $item) {
            foreach (preg_split('/[,;\s]+/', (string) $item, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                $raw[] = $part;
            }
        }

        $emails = [];
        foreach ($raw as $addr) {
            $addr = trim((string) $addr);
            $key = strtolower($addr);
            if ($addr !== '' && ! isset($emails[$key]) && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $emails[$key] = $addr; // keep first occurrence; dedupe case-insensitively
            }
        }

        return array_values(array_slice($emails, 0, 20));
    }

    public function sendOrderEmail(string $templateKey, Order $order, string|array|null $to = null): bool
    {
        $data = $this->orderData($order);

        // Admin notifications skip the customer-facing extras (promo, invoice
        // button, "you may also like") and instead carry full customer +
        // address + order-history detail so staff can act on the order.
        $data['audience'] = (str_contains($templateKey, 'admin') || $templateKey === 'low_stock') ? 'admin' : 'customer';

        if ($data['audience'] === 'admin') {
            $data = array_merge($data, $this->adminExtras($order));
        }

        return $this->send(
            $templateKey,
            $to ?? $order->customer_email,
            $this->orderVars($order),
            $data,
        );
    }

    /** Extra data for admin order emails: billing address + customer history. */
    public function adminExtras(Order $order): array
    {
        $history = Order::where('customer_email', $order->customer_email);

        return [
            'billing_address' => $order->billing_address ?? [],
            'customer' => [
                'name' => $order->customerName() ?: '—',
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'orders_count' => (clone $history)->count(),
                'lifetime_total' => price_format((float) (clone $history)->sum('total')),
            ],
        ];
    }

    public function orderVars(Order $order): array
    {
        return [
            'customer_name' => $order->customerName() ?: 'Customer',
            'order_number' => $order->order_number,
            'order_total' => price_format($order->total),
            'order_status' => str($order->status)->headline()->toString(),
            'payment_method' => str((string) $order->payment_method)->headline()->toString(),
            'store_name' => \App\Support\StoreBranding::name(),
            'store_url' => config('app.url'),
            'order_url' => $this->orderUrl($order),
            'invoice_url' => $order->invoiceUrl('email'),
        ];
    }

    /** Public, guest-safe link to the order (the thank-you / status page). */
    protected function orderUrl(Order $order): string
    {
        try {
            return route('checkout.thank-you', $order->order_number);
        } catch (Throwable) {
            return (string) config('app.url');
        }
    }

    public function orderData(Order $order): array
    {
        $order->loadMissing('items.product.images');

        return [
            'number' => $order->order_number,
            'url' => $this->orderUrl($order),
            'invoice_url' => $order->invoiceUrl('email'),
            // Fulfilment tracker: 1 confirmed, 2 shipped/processing, 3 delivered.
            'step' => $order->fulfilmentStep(),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'qty' => $item->qty,
                'total' => price_format($item->total),
                'options' => $this->optionsText($item),
                'image' => $item->product?->featuredImageUrl(),
            ])->all(),
            'subtotal' => price_format($order->subtotal),
            'discount' => (float) $order->discount_total > 0 ? price_format($order->discount_total) : null,
            'shipping' => price_format($order->shipping_total),
            'tax' => (float) $order->tax_total > 0 ? price_format($order->tax_total) : null,
            'payment_fee' => (float) $order->payment_fee > 0 ? price_format((float) $order->payment_fee) : null,
            'payment_fee_label' => $order->payment_fee_label ?: 'Payment fee',
            'total' => price_format($order->total),
            'shipping_address' => $order->shipping_address ?? [],
            'payment_label' => str((string) $order->payment_method)->headline()->toString(),
            'related' => $this->relatedProducts($order),
        ];
    }

    /** Human-readable "Color: Blue · Size: L" from an item's option snapshot. */
    protected function optionsText(\App\Models\OrderItem $item): ?string
    {
        $options = $item->options;
        if (! is_array($options) || $options === []) {
            return null;
        }

        $parts = [];
        foreach ($options as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $parts[] = str((string) $key)->headline().': '.$value;
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * A few published+visible products related to what was ordered (same
     * category), excluding the ordered items, topped up with random picks.
     * Best-effort — never throws into the email pipeline.
     */
    public function relatedProducts(Order $order, int $limit = 3): array
    {
        try {
            $orderedIds = $order->items->pluck('product_id')->filter()->unique();

            $catIds = $orderedIds->isEmpty() ? collect() : Product::whereIn('id', $orderedIds)
                ->with('categories:id')->get()
                ->pluck('categories')->flatten()->pluck('id')->unique();

            $picked = $catIds->isEmpty() ? Product::query()->whereRaw('1 = 0')->get() : Product::visible()
                ->with('images')
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $catIds))
                ->whereNotIn('id', $orderedIds)
                ->inRandomOrder()->limit($limit)->get();

            if ($picked->count() < $limit) {
                $fill = Product::visible()
                    ->with('images')
                    ->whereNotIn('id', $orderedIds->merge($picked->pluck('id')))
                    ->inRandomOrder()->limit($limit - $picked->count())->get();
                $picked = $picked->merge($fill);
            }

            return $picked->map(fn (Product $p) => [
                'name' => $p->name,
                'url' => $p->url(),
                'image' => $p->featuredImageUrl(),
                'price' => price_format($p->currentPrice()),
            ])->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
