@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold">Order {{ $order->order_number }}</h1>
        <a href="{{ route('account.invoice', $order->order_number) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Download invoice (PDF)</a>
    </div>
    <p class="mt-1 text-sm text-gray-500">Placed {{ $order->created_at->format('F j, Y \a\t g:ia') }} · Status: {{ str($order->status)->headline() }} · Payment: {{ str($order->payment_status)->headline() }}</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-lg border border-gray-200">
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach($order->items as $item)
                        <li class="flex justify-between gap-2 p-4">
                            <div>
                                <p class="font-medium">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">
                                    @if($item->sku) SKU: {{ $item->sku }} · @endif
                                    {{ price_format($item->unit_price) }} × {{ $item->qty }}
                                </p>
                            </div>
                            <p class="font-medium">{{ price_format($item->total) }}</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-1 border-t border-gray-200 p-4 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ price_format($order->subtotal) }}</dd></div>
                    @if($order->discount_total > 0)
                        <div class="flex justify-between text-green-600"><dt>Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</dt><dd>−{{ price_format($order->discount_total) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt>Shipping @if($order->shipping_method)({{ $order->shipping_method }})@endif</dt><dd>{{ price_format($order->shipping_total) }}</dd></div>
                    @if($order->tax_total > 0)
                        <div class="flex justify-between"><dt>Tax</dt><dd>{{ price_format($order->tax_total) }}</dd></div>
                    @endif
                    @if($order->payment_fee > 0)
                        <div class="flex justify-between"><dt>{{ $order->payment_fee_label ?: 'Payment fee' }}</dt><dd>{{ price_format($order->payment_fee) }}</dd></div>
                    @endif
                    <div class="flex justify-between pt-1 text-base font-bold"><dt>Total</dt><dd>{{ price_format($order->total) }}</dd></div>
                </dl>
            </div>

            @if($order->notes->isNotEmpty())
                <div class="mt-4 rounded-lg border border-gray-200 p-4">
                    <h2 class="text-sm font-semibold">Updates</h2>
                    <ul class="mt-2 space-y-2 text-sm text-gray-600">
                        @foreach($order->notes as $note)
                            <li>
                                <time class="text-xs text-gray-400">{{ $note->created_at->format('M j, Y g:ia') }}</time>
                                <p>{{ $note->note }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="space-y-4 text-sm">
            @foreach(['Shipping address' => $order->shipping_address, 'Billing address' => $order->billing_address] as $label => $address)
                <div class="rounded-lg border border-gray-200 p-4">
                    <h2 class="font-semibold">{{ $label }}</h2>
                    <address class="mt-2 not-italic text-gray-600">
                        {{ $address['first_name'] ?? '' }} {{ $address['last_name'] ?? '' }}<br>
                        {{ $address['address_line_1'] ?? '' }}<br>
                        @if(!empty($address['address_line_2'])){{ $address['address_line_2'] }}<br>@endif
                        {{ $address['city'] ?? '' }}@if(!empty($address['state'])), {{ $address['state'] }}@endif {{ $address['postal_code'] ?? '' }}<br>
                        {{ $address['country'] ?? '' }}
                    </address>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
