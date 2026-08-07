@php
    use App\Support\StoreBranding;

    $b = StoreBranding::emailBranding();
    $brand = $b['brand'];
    $brandDark = $b['brand_dark'];
    $headerText = $b['header_text_color'] ?? '#ffffff';
    $host = parse_url($b['url'], PHP_URL_HOST) ?: $b['url'];

    // Rich order sections only render for customer ORDER emails. A CART
    // reminder (abandoned cart) is a different context: the shopper hasn't
    // ordered yet, so it shows their saved cart + a "Complete your order"
    // button — never an order tracker, invoice, or "View your order".
    $isCart = ($order['audience'] ?? null) === 'cart' && ! empty($order['items']);
    $isCustomerOrder = ($order['audience'] ?? null) === 'customer' && ! empty($order['items']);
    $isAdmin = ($order['audience'] ?? null) === 'admin';
    $cartUrl = $order['cart_url'] ?? $b['url'];
    $showTracker  = $isCustomerOrder && setting('emails.email_show_tracker', true);
    $showInvoice  = $isCustomerOrder && setting('emails.email_show_invoice_button', true) && ! empty($order['invoice_url']);
    $showRelated  = $isCustomerOrder && setting('emails.email_show_related', true) && ! empty($order['related']);
    $showPromo    = $isCustomerOrder && setting('emails.email_show_promo', true);

    $step = (int) ($order['step'] ?? 1);
    $steps = ['Order Confirmed', 'Shipped', 'Delivered'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $heading }}</title>
    {{-- Mobile spacing/alignment. Clients that support embedded styles (Apple
         Mail, Gmail, most webmail) tighten padding and stack two-column blocks
         so nothing feels crowded; Outlook desktop ignores this and keeps the
         roomy desktop layout, which is a fine fallback. --}}
    <style>
        @media only screen and (max-width:600px) {
            /* Comfortable side gutters instead of the desktop 36px. */
            .em-pad { padding-left:20px !important; padding-right:20px !important; }
            .em-header { padding-left:20px !important; padding-right:20px !important; }
            /* Two-column blocks (addresses, related products) stack full-width. */
            .em-col { display:block !important; width:100% !important;
                      padding-left:0 !important; padding-right:0 !important;
                      padding-bottom:12px !important; box-sizing:border-box; }
            .em-col:last-child { padding-bottom:0 !important; }
            .em-h1 { font-size:21px !important; line-height:1.3 !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">
    <span style="display:none; max-height:0; overflow:hidden; font-size:1px; color:#f1f5f9;">{{ $heading }}</span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:28px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">

                {{-- Brand header: logo if set, else store name --}}
                <tr>
                    <td class="em-header" style="background:{{ $brand }}; background-image:linear-gradient(135deg,{{ $brand }},{{ $brandDark }}); border-radius:14px 14px 0 0; padding:24px 36px;" align="center">
                        <a href="{{ $b['url'] }}" style="text-decoration:none;">
                            @if($b['logo_url'])
                                <img src="{{ $b['logo_url'] }}" alt="{{ $b['name'] }}" height="40" style="height:40px; max-height:40px; display:inline-block; border:0;">
                            @else
                                <span style="color:{{ $headerText }}; font-size:22px; font-weight:800; letter-spacing:-0.02em;">{{ $b['name'] }}</span>
                            @endif
                        </a>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td class="em-pad" style="background:#ffffff; padding:34px 36px 8px;">
                        <h1 style="margin:0 0 14px; font-size:23px; line-height:1.3; color:#0f172a; font-weight:800;">{{ $heading }}</h1>
                        <div style="font-size:15px; line-height:1.7; color:#334155;">{!! $body !!}</div>
                    </td>
                </tr>

                {{-- Fulfilment tracker --}}
                @if($showTracker)
                    <tr><td class="em-pad" style="background:#ffffff; padding:20px 36px 6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                @foreach($steps as $i => $label)
                                    @php $n = $i + 1; $done = $n <= $step; @endphp
                                    <td align="center" style="width:33.33%;">
                                        <div style="width:34px; height:34px; line-height:34px; margin:0 auto; border-radius:50%; font-size:15px; font-weight:700; color:#ffffff; background:{{ $done ? $brand : '#cbd5e1' }};">
                                            {{ $done ? '✓' : $n }}
                                        </div>
                                        <div style="margin-top:8px; font-size:11px; font-weight:600; color:{{ $done ? '#0f172a' : '#94a3b8' }};">{{ $label }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td></tr>
                @endif

                {{-- Cart reminder CTA: back to their saved cart --}}
                @if($isCart)
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 6px;" align="center">
                        <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block;">
                            <tr><td style="border-radius:10px; background:{{ $brand }};">
                                <a href="{{ $cartUrl }}" style="display:inline-block; padding:14px 30px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">Complete your order &rarr;</a>
                            </td></tr>
                        </table>
                        <p style="margin:10px 0 0; font-size:12px; color:#94a3b8;">Your cart is saved — pick up right where you left off.</p>
                    </td></tr>
                @endif

                {{-- Order CTAs: View order + (highlighted) invoice download --}}
                @if($isCustomerOrder && (! empty($order['url']) || $showInvoice))
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 6px;" align="center">
                        @if(! empty($order['url']))
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block; margin:0 6px 10px;">
                                <tr><td style="border-radius:10px; background:{{ $brand }};">
                                    <a href="{{ $order['url'] }}" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">View Your Order &rarr;</a>
                                </td></tr>
                            </table>
                        @endif
                        @if($showInvoice)
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block; margin:0 6px 10px;">
                                <tr><td style="border-radius:10px; background:#0f172a;">
                                    <a href="{{ $order['invoice_url'] }}" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">⬇ Download PDF Invoice</a>
                                </td></tr>
                            </table>
                            <p style="margin:2px 0 0; font-size:12px; color:#94a3b8;">Need your invoice PDF? Download it any time — no login required.</p>
                        @endif
                    </td></tr>
                @endif

                {{-- Order details with product thumbnails --}}
                @if(! empty($order['items']))
                    <tr><td class="em-pad" style="background:#ffffff; padding:26px 36px 8px;">
                        @if($isCart)
                            <p style="margin:0 0 12px; font-size:13px; font-weight:700; color:#0f172a;">Your saved cart</p>
                        @elseif(! empty($order['number']))
                            <p style="margin:0 0 12px; font-size:13px; color:#64748b;">Order <strong style="color:#0f172a;">#{{ $order['number'] }}</strong></p>
                        @endif
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                            @foreach($order['items'] as $item)
                                <tr>
                                    <td width="56" style="padding:10px 12px 10px 0; vertical-align:top;">
                                        @if(! empty($item['image']))
                                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" width="52" height="52" style="width:52px; height:52px; border-radius:8px; border:1px solid #e2e8f0; object-fit:cover;">
                                        @else
                                            <div style="width:52px; height:52px; border-radius:8px; background:#f1f5f9; border:1px solid #e2e8f0;"></div>
                                        @endif
                                    </td>
                                    <td style="padding:10px 0; vertical-align:top; border-bottom:1px solid #f1f5f9;">
                                        <div style="font-size:14px; font-weight:600; color:#0f172a;">{{ $item['name'] }}</div>
                                        @if(! empty($item['options']))<div style="font-size:12px; color:#64748b; margin-top:2px;">{{ $item['options'] }}</div>@endif
                                        <div style="font-size:12px; color:#94a3b8; margin-top:2px;">Qty: {{ $item['qty'] }}</div>
                                    </td>
                                    <td align="right" style="padding:10px 0; vertical-align:top; border-bottom:1px solid #f1f5f9; font-size:14px; font-weight:600; color:#0f172a; white-space:nowrap;">{{ $item['total'] }}</td>
                                </tr>
                            @endforeach
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;">
                            @if($isCart)
                                {{-- Cart: no shipping/tax yet — just the cart value. --}}
                                <tr><td align="right" style="padding:8px 0 0; font-size:16px; font-weight:800; color:#0f172a;">Cart total</td><td align="right" width="90" style="padding:8px 0 0; font-size:16px; font-weight:800; color:{{ $brandDark }};">{{ $order['subtotal'] }}</td></tr>
                            @else
                                <tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;">Subtotal</td><td align="right" width="90" style="padding:3px 0; font-size:14px; color:#0f172a;">{{ $order['subtotal'] }}</td></tr>
                                @if(! empty($order['discount']))<tr><td align="right" style="padding:3px 0; font-size:14px; color:#059669;">Discount</td><td align="right" style="padding:3px 0; font-size:14px; color:#059669;">−{{ $order['discount'] }}</td></tr>@endif
                                <tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;">Shipping</td><td align="right" style="padding:3px 0; font-size:14px; color:#0f172a;">{{ $order['shipping'] }}</td></tr>
                                @if(! empty($order['tax']))<tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;">Tax</td><td align="right" style="padding:3px 0; font-size:14px; color:#0f172a;">{{ $order['tax'] }}</td></tr>@endif
                                @if(! empty($order['payment_fee']))<tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;">{{ $order['payment_fee_label'] }}</td><td align="right" style="padding:3px 0; font-size:14px; color:#0f172a;">{{ $order['payment_fee'] }}</td></tr>@endif
                                <tr><td align="right" style="padding:10px 0 0; font-size:16px; font-weight:800; color:#0f172a;">Total</td><td align="right" style="padding:10px 0 0; font-size:16px; font-weight:800; color:{{ $brandDark }};">{{ $order['total'] }}</td></tr>
                            @endif
                        </table>
                    </td></tr>

                    {{-- Shipping address --}}
                    @if($isCustomerOrder && ! empty($order['shipping_address']['address_line_1'] ?? $order['shipping_address']['first_name'] ?? null))
                        @php $sa = $order['shipping_address']; @endphp
                        <tr><td class="em-pad" style="background:#ffffff; padding:18px 36px 6px;">
                            <div style="border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px;">
                                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Shipping to</div>
                                <div style="font-size:13px; line-height:1.6; color:#334155;">
                                    <strong style="color:#0f172a;">{{ trim(($sa['first_name'] ?? '').' '.($sa['last_name'] ?? '')) }}</strong><br>
                                    {{ $sa['address_line_1'] ?? '' }}{{ ! empty($sa['address_line_2']) ? ', '.$sa['address_line_2'] : '' }}<br>
                                    {{ trim(($sa['city'] ?? '').' '.($sa['postal_code'] ?? '')) }}{{ ! empty($sa['country']) ? ', '.$sa['country'] : '' }}
                                    @if(! empty($order['payment_label']))<br><span style="color:#64748b;">Paid via {{ $order['payment_label'] }}</span>@endif
                                </div>
                            </div>
                        </td></tr>
                    @endif
                @endif

                {{-- Admin: customer summary + billing/shipping addresses --}}
                @if($isAdmin)
                    @php $cust = $order['customer'] ?? []; $ba = $order['billing_address'] ?? []; $sa = $order['shipping_address'] ?? []; @endphp
                    <tr><td class="em-pad" style="background:#ffffff; padding:18px 36px 6px;">
                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:12px;">
                            <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Customer</div>
                            <div style="font-size:14px; font-weight:700; color:#0f172a;">{{ $cust['name'] ?? '—' }}</div>
                            <div style="font-size:13px; color:#334155;">{{ $cust['email'] ?? '' }}@if(! empty($cust['phone'])) · {{ $cust['phone'] }}@endif</div>
                            <div style="margin-top:8px; font-size:13px; color:#334155;">
                                <strong style="color:{{ $brandDark }};">{{ $cust['orders_count'] ?? 1 }}</strong> order(s) placed
                                @if(! empty($cust['lifetime_total'])) · lifetime spend <strong style="color:#0f172a;">{{ $cust['lifetime_total'] }}</strong>@endif
                            </div>
                        </div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                            <td class="em-col" width="50%" style="vertical-align:top; padding-right:6px;">
                                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Billing address</div>
                                    <div style="font-size:13px; line-height:1.6; color:#334155;">
                                        <strong style="color:#0f172a;">{{ trim(($ba['first_name'] ?? '').' '.($ba['last_name'] ?? '')) ?: '—' }}</strong><br>
                                        @if(! empty($ba['address_line_1'])){{ $ba['address_line_1'] }}{{ ! empty($ba['address_line_2']) ? ', '.$ba['address_line_2'] : '' }}<br>@endif
                                        {{ trim(($ba['city'] ?? '').' '.($ba['postal_code'] ?? '')) }}@if(! empty($ba['country'])), {{ $ba['country'] }}@endif
                                        {{-- Phone + email here too, so the whole block copies straight to the courier. --}}
                                        @if(! empty($cust['phone']))<br><a href="tel:{{ $cust['phone'] }}" style="color:{{ $brandDark }}; text-decoration:none;">{{ $cust['phone'] }}</a>@endif
                                        @if(! empty($cust['email']))<br><a href="mailto:{{ $cust['email'] }}" style="color:{{ $brandDark }}; text-decoration:none;">{{ $cust['email'] }}</a>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="em-col" width="50%" style="vertical-align:top; padding-left:6px;">
                                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Shipping address</div>
                                    <div style="font-size:13px; line-height:1.6; color:#334155;">
                                        <strong style="color:#0f172a;">{{ trim(($sa['first_name'] ?? '').' '.($sa['last_name'] ?? '')) ?: '—' }}</strong><br>
                                        @if(! empty($sa['address_line_1'])){{ $sa['address_line_1'] }}{{ ! empty($sa['address_line_2']) ? ', '.$sa['address_line_2'] : '' }}<br>@endif
                                        {{ trim(($sa['city'] ?? '').' '.($sa['postal_code'] ?? '')) }}@if(! empty($sa['country'])), {{ $sa['country'] }}@endif
                                    </div>
                                </div>
                            </td>
                        </tr></table>
                    </td></tr>
                @endif

                {{-- Contact options --}}
                @if(($isCustomerOrder || $isCart) && ($b['email'] || $b['phone']))
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                @if($b['email'])
                                    <td class="em-col" width="50%" style="padding:6px;">
                                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                            <div style="font-size:13px; font-weight:700; color:#0f172a;">✉ Email us</div>
                                            <a href="mailto:{{ $b['email'] }}" style="font-size:12px; color:{{ $brandDark }}; text-decoration:none;">{{ $b['email'] }}</a>
                                        </div>
                                    </td>
                                @endif
                                @if($b['phone'])
                                    <td class="em-col" width="50%" style="padding:6px;">
                                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                            <div style="font-size:13px; font-weight:700; color:#0f172a;">☎ Call us</div>
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $b['phone']) }}" style="font-size:12px; color:{{ $brandDark }}; text-decoration:none;">{{ $b['phone'] }}</a>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        </table>
                    </td></tr>
                @endif

                {{-- Promo banner --}}
                @if($showPromo)
                    <tr><td class="em-pad" style="background:#ffffff; padding:16px 36px 6px;">
                        <div style="background:#fff7ed; border-radius:12px; padding:20px 22px; text-align:center;">
                            <div style="font-size:18px; font-weight:800; color:#0f172a;">{{ setting('emails.promo_heading', 'Free delivery on your next order') }}</div>
                            <p style="margin:6px 0 14px; font-size:13px; color:#64748b;">{{ setting('emails.promo_text', 'Thanks for reading — fresh articles and guides, straight to your inbox.') }}</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block;">
                                <tr><td style="border-radius:10px; background:{{ $brand }};">
                                    <a href="{{ $b['url'] }}" style="display:inline-block; padding:11px 24px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">{{ setting('emails.promo_cta', 'Shop Now') }}</a>
                                </td></tr>
                            </table>
                        </div>
                    </td></tr>
                @endif

                {{-- Related products --}}
                @if($showRelated)
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; margin-bottom:14px;">You may also like</div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                @foreach($order['related'] as $rp)
                                    <td width="33.33%" style="padding:0 6px; vertical-align:top;" align="center">
                                        <a href="{{ $rp['url'] }}" style="text-decoration:none; color:inherit;">
                                            @if(! empty($rp['image']))
                                                <img src="{{ $rp['image'] }}" alt="{{ $rp['name'] }}" width="100%" style="width:100%; max-width:160px; border-radius:10px; border:1px solid #e2e8f0;">
                                            @else
                                                <div style="width:100%; max-width:160px; height:110px; border-radius:10px; background:#f1f5f9; border:1px solid #e2e8f0; margin:0 auto;"></div>
                                            @endif
                                            <div style="font-size:12px; font-weight:600; color:#0f172a; margin-top:8px; line-height:1.3;">{{ \Illuminate\Support\Str::limit($rp['name'], 40) }}</div>
                                            <div style="font-size:12px; font-weight:700; color:{{ $brandDark }}; margin-top:2px;">{{ $rp['price'] }}</div>
                                        </a>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td></tr>
                @endif

                {{-- Support line (plain non-order, non-cart emails still get this) --}}
                @if(! $isCustomerOrder && ! $isCart && $b['email'])
                    <tr><td class="em-pad" style="background:#ffffff; padding:6px 36px 20px;">
                        <p style="margin:20px 0 0; padding-top:18px; border-top:1px solid #f1f5f9; font-size:13px; color:#64748b;">
                            Questions? Reach us at <a href="mailto:{{ $b['email'] }}" style="color:{{ $brandDark }}; font-weight:600;">{{ $b['email'] }}</a>.
                        </p>
                    </td></tr>
                @endif

                <tr><td class="em-pad" style="background:#ffffff; padding:10px 36px 30px;"></td></tr>

                {{-- Footer --}}
                <tr>
                    <td class="em-pad" style="background:#0f172a; border-radius:0 0 14px 14px; padding:26px 36px; text-align:center;">
                        @if(count($b['socials']))
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block; margin-bottom:14px;"><tr>
                                @foreach($b['socials'] as $s)
                                    <td style="padding:0 5px;">
                                        <a href="{{ $s['url'] }}" style="display:inline-block; width:30px; height:30px; line-height:30px; border-radius:50%; background:#1e293b; color:#ffffff; font-size:13px; font-weight:700; text-decoration:none;" title="{{ $s['label'] }}">{{ $s['initial'] }}</a>
                                    </td>
                                @endforeach
                            </tr></table>
                        @endif
                        @if(! empty($b['footer_text']))
                            <div style="font-size:13px; line-height:1.6; color:#cbd5e1; margin-bottom:10px;">{{ $b['footer_text'] }}</div>
                        @endif
                        <div style="font-size:12px; line-height:1.7; color:#94a3b8;">
                            <strong style="color:#e2e8f0;">{{ $b['name'] }}</strong><br>
                            @foreach($b['address_lines'] as $line){{ $line }}<br>@endforeach
                            @if($b['phone']){{ $b['phone'] }} · @endif<a href="{{ $b['url'] }}" style="color:#cbd5e1; text-decoration:none;">{{ $host }}</a>
                        </div>
                        <div style="font-size:11px; color:#64748b; margin-top:12px;">© {{ date('Y') }} {{ $b['name'] }}. All rights reserved.</div>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
