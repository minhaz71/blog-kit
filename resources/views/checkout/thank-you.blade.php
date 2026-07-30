@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
    <div class="text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl">✓</div>
        <h1 class="mt-4 text-3xl font-bold">Thank you for your order!</h1>
        <p class="mt-2 text-gray-600">Order <strong>{{ $order->order_number }}</strong> has been received.
            A confirmation email is on its way to {{ $order->customer_email }}.</p>
    </div>

    @if($instructions)
        <div class="mt-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
            <p class="font-semibold">Payment instructions</p>
            <p class="mt-1">{{ $instructions }}</p>
        </div>
    @endif

    <div class="mt-8 rounded-lg border border-gray-200 p-4">
        <h2 class="font-semibold">Order summary</h2>
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @foreach($order->items as $item)
                <li class="flex justify-between py-2">
                    <span>{{ $item->name }} <span class="text-gray-400">× {{ $item->qty }}</span></span>
                    <span class="font-medium">{{ price_format($item->total) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="mt-3 space-y-1 border-t border-gray-200 pt-3 text-sm">
            <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ price_format($order->subtotal) }}</dd></div>
            @if($order->discount_total > 0)
                <div class="flex justify-between text-green-600"><dt>Discount</dt><dd>−{{ price_format($order->discount_total) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt>Shipping</dt><dd>{{ price_format($order->shipping_total) }}</dd></div>
            @if($order->tax_total > 0)
                <div class="flex justify-between"><dt>Tax</dt><dd>{{ price_format($order->tax_total) }}</dd></div>
            @endif
            @if($order->payment_fee > 0)
                <div class="flex justify-between"><dt>{{ $order->payment_fee_label ?: 'Payment fee' }}</dt><dd>{{ price_format($order->payment_fee) }}</dd></div>
            @endif
            <div class="flex justify-between pt-1 text-base font-bold"><dt>Total</dt><dd>{{ price_format($order->total) }}</dd></div>
        </dl>
    </div>

    <div class="mt-8 flex justify-center gap-3">
        <a href="{{ route('shop') }}" class="rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500">Continue shopping</a>
        @auth
            <a href="{{ route('account.orders') }}" class="rounded-md border border-gray-300 px-6 py-3 text-sm font-semibold hover:bg-gray-50">View my orders</a>
        @endauth
    </div>
</div>
@endsection
