@php
    // $brand comes from App\Services\Invoice\InvoiceService (StoreBranding::all()).
    $brand ??= \App\Support\StoreBranding::all();
    $accent = $brand['brand'];
    $accentDark = $brand['brand_dark'];
    // Short, human-readable verification/tracking code derived from the full
    // signature hash — printed so the invoice can be authenticated and matched
    // to a download event.
    $verifyCode = strtoupper(substr($order->invoiceCode(), 0, 16));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* Even margins on every side. dompdf ignores @page margin reliably,
           so the visible margin comes from the .sheet padding below. */
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .sheet { padding: 40px 44px; }
        .band { background: {{ $accent }}; height: 6px; border-radius: 3px; }
        .head { width: 100%; margin-top: 18px; }
        .head td { vertical-align: top; }
        .brand-name { font-size: 20px; font-weight: bold; color: {{ $accentDark }}; }
        .muted { color: #6b7280; }
        .small { font-size: 10px; }
        .doc-title { font-size: 26px; font-weight: bold; color: #111827; letter-spacing: 1px; }
        .pill { display: inline-block; padding: 3px 9px; border-radius: 10px; background: #f3f4f6; color: #374151; font-size: 10px; font-weight: bold; }
        .meta { margin-top: 4px; }
        .addresses { width: 100%; margin-top: 24px; }
        .addresses td { border: 0; vertical-align: top; width: 50%; padding-right: 12px; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; margin-bottom: 4px; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 22px; }
        table.items th { text-align: left; background: {{ $accent }}; color: #ffffff; padding: 8px 8px; font-size: 10px; text-transform: uppercase; }
        table.items td { border-bottom: 1px solid #eef0f3; padding: 9px 8px; }
        .right { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { font-size: 15px; font-weight: bold; color: {{ $accentDark }}; border-top: 2px solid #e5e7eb; padding-top: 8px; }
        .verify { margin-top: 26px; border: 1px dashed #d1d5db; border-radius: 8px; padding: 12px 14px; background: #fafafa; }
        .verify .code { font-family: DejaVu Sans Mono, monospace; font-size: 13px; font-weight: bold; letter-spacing: 1px; color: #111827; }
        .foot { margin-top: 22px; border-top: 1px solid #eef0f3; padding-top: 12px; color: #9ca3af; font-size: 10px; }
        .thanks { margin-top: 20px; color: {{ $accentDark }}; font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="band"></div>

    <table class="head">
        <tr>
            <td style="width:60%;">
                @if($brand['logo_data_uri'])
                    <img src="{{ $brand['logo_data_uri'] }}" alt="{{ $brand['name'] }}" style="max-height:48px;">
                @else
                    <div class="brand-name">{{ $brand['name'] }}</div>
                @endif
                <div class="muted small meta">
                    @foreach($brand['address_lines'] as $line){{ $line }}<br>@endforeach
                    @if($brand['phone']){{ $brand['phone'] }}<br>@endif
                    @if($brand['email']){{ $brand['email'] }}@endif
                </div>
            </td>
            <td class="right" style="width:40%;">
                <div class="doc-title">INVOICE</div>
                <div class="meta"><span class="muted">Invoice&nbsp;</span><strong>#{{ $order->order_number }}</strong></div>
                <div class="meta muted small">Date: {{ $order->created_at->format('F j, Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="addresses">
        <tr>
            <td>
                <div class="label">Billed to</div>
                {{ $order->billing_address['first_name'] ?? '' }} {{ $order->billing_address['last_name'] ?? '' }}<br>
                @if(! empty($order->billing_address['address_line_1'])){{ $order->billing_address['address_line_1'] }}<br>@endif
                {{ $order->billing_address['city'] ?? '' }} {{ $order->billing_address['postal_code'] ?? '' }}<br>
                {{ $order->billing_address['country'] ?? '' }}<br>
                @if($order->customer_email)<span class="muted small">{{ $order->customer_email }}</span>@endif
            </td>
            <td>
                <div class="label">Shipped to</div>
                {{ $order->shipping_address['first_name'] ?? '' }} {{ $order->shipping_address['last_name'] ?? '' }}<br>
                @if(! empty($order->shipping_address['address_line_1'])){{ $order->shipping_address['address_line_1'] }}<br>@endif
                {{ $order->shipping_address['city'] ?? '' }} {{ $order->shipping_address['postal_code'] ?? '' }}<br>
                {{ $order->shipping_address['country'] ?? '' }}<br>
                @if($order->payment_method)<span class="muted small">Paid via {{ str((string) $order->payment_method)->headline() }}</span>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th><th>SKU</th><th class="right">Unit</th><th class="right">Qty</th><th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="muted">{{ $item->sku }}</td>
                    <td class="right">{{ price_format($item->unit_price) }}</td>
                    <td class="right">{{ $item->qty }}</td>
                    <td class="right">{{ price_format($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="right muted">Subtotal</td><td class="right">{{ price_format($order->subtotal) }}</td></tr>
        @if($order->discount_total > 0)
            <tr><td class="right muted">Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</td><td class="right">-{{ price_format($order->discount_total) }}</td></tr>
        @endif
        <tr><td class="right muted">Shipping</td><td class="right">{{ price_format($order->shipping_total) }}</td></tr>
        @if($order->tax_total > 0)
            <tr><td class="right muted">Tax</td><td class="right">{{ price_format($order->tax_total) }}</td></tr>
        @endif
        @if($order->payment_fee > 0)
            <tr><td class="right muted">{{ $order->payment_fee_label ?: 'Payment fee' }}</td><td class="right">{{ price_format($order->payment_fee) }}</td></tr>
        @endif
        <tr class="grand"><td class="right">Total</td><td class="right">{{ price_format($order->total) }}</td></tr>
    </table>

    <div class="thanks">Thank you for your order!</div>

    <div class="verify">
        <div class="label">Document verification &amp; tracking code</div>
        <span class="code">{{ $verifyCode }}</span>
        <span class="muted small">&nbsp;— verifies this invoice is genuine and lets us match your download to order #{{ $order->order_number }}.</span>
    </div>

    <div class="foot">
        {{ $brand['name'] }} · {{ parse_url($brand['url'], PHP_URL_HOST) ?: $brand['url'] }}
        @if($order->coupon_code) · Coupon {{ $order->coupon_code }} @endif
        <br>This invoice was generated on {{ now()->format('F j, Y') }}.
    </div>
</div>
</body>
</html>
