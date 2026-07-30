@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-2xl">✕</div>
    <h1 class="mt-4 text-3xl font-bold">Payment failed</h1>
    <p class="mt-2 text-gray-600">
        We couldn't complete the payment for order <strong>{{ $order->order_number }}</strong>.
        No money was taken. You can try again or choose another payment method.
    </p>
    <div class="mt-8 flex justify-center gap-3">
        <a href="{{ route('cart.index') }}" class="rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500">Back to cart</a>
        <a href="{{ url('/contact-us') }}" class="rounded-md border border-gray-300 px-6 py-3 text-sm font-semibold hover:bg-gray-50">Contact support</a>
    </div>
</div>
@endsection
