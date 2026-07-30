@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">Your cart</h1>

    @if(!$cart || $cart->items->isEmpty())
        <div class="mt-12 text-center">
            <p class="text-gray-500">Your cart is empty.</p>
            <a href="{{ route('shop') }}" class="mt-4 inline-block rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500">Continue shopping</a>
        </div>
    @else
        <div class="mt-6 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200">
                    @foreach($cart->items as $item)
                        <li class="flex gap-4 p-4">
                            <a href="{{ $item->product->url() }}" class="h-20 w-20 shrink-0 overflow-hidden rounded-md bg-gray-100">
                                @if($image = $item->variation?->imageUrl() ?? $item->product->featuredImageUrl())
                                    <img src="{{ $image }}" alt="{{ $item->product->name }}" width="80" height="80" class="h-full w-full object-cover">
                                @endif
                            </a>
                            <div class="flex flex-1 flex-col">
                                <div class="flex justify-between gap-2">
                                    <div>
                                        <a href="{{ $item->product->url() }}" class="font-medium hover:text-indigo-600">{{ $item->product->name }}</a>
                                        @if($item->variation)
                                            <p class="text-xs text-gray-500">{{ $item->variation->label() }}</p>
                                        @endif
                                    </div>
                                    <p class="font-semibold">{{ price_format($item->lineTotal()) }}</p>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-2">
                                    <div class="flex items-center gap-2">
                                        <form action="{{ route('cart.update', $item->id) }}" method="POST"
                                              class="flex items-center"
                                              x-data="{ qty: {{ $item->qty }}, busy: false,
                                                        change(next) {
                                                            next = Math.max(1, Math.min(999, next));
                                                            if (next === this.qty || this.busy) return;
                                                            this.busy = true; this.qty = next;
                                                            shopkit.setQty({{ $item->id }}, next)
                                                                .then(() => window.location.reload())
                                                                .catch(e => { alert(e.message); this.busy = false; });
                                                        } }">
                                            @csrf @method('PATCH')
                                            <label class="sr-only" for="qty-{{ $item->id }}">Quantity for {{ $item->product->name }}</label>
                                            <div class="flex items-center rounded-md border border-gray-300" :class="busy && 'opacity-50'">
                                                <button type="button"
                                                        class="flex h-9 w-9 items-center justify-center text-lg text-gray-600 hover:bg-gray-100 active:bg-gray-200 disabled:opacity-40"
                                                        aria-label="Decrease quantity"
                                                        :disabled="busy || qty <= 1"
                                                        @click="change(qty - 1)">&minus;</button>
                                                <input id="qty-{{ $item->id }}" type="number" name="qty" x-model.number="qty"
                                                       min="1" max="999" aria-label="Quantity"
                                                       class="h-9 w-12 border-0 p-0 text-center text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none focus:ring-0"
                                                       @change="change(qty)">
                                                <button type="button"
                                                        class="flex h-9 w-9 items-center justify-center text-lg text-gray-600 hover:bg-gray-100 active:bg-gray-200 disabled:opacity-40"
                                                        aria-label="Increase quantity"
                                                        :disabled="busy"
                                                        @click="change(qty + 1)">+</button>
                                            </div>
                                        </form>
                                        <span class="text-xs text-gray-400">× {{ price_format($item->unitPrice()) }}</span>
                                    </div>
                                    <form action="{{ route('cart.remove', $item->id) }}" method="POST" x-data="{ busy: false }">
                                        @csrf @method('DELETE')
                                        <button type="button" :disabled="busy"
                                                class="text-sm text-red-500 hover:underline disabled:opacity-40"
                                                @click="busy = true; shopkit.removeItem({{ $item->id }}).then(() => window.location.reload()).catch(e => { alert(e.message); busy = false })">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <h2 class="font-semibold">Summary</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Subtotal</dt><dd class="font-medium">{{ price_format($cart->subtotal()) }}</dd></div>
                        @if($discount > 0)
                            <div class="flex justify-between text-green-600">
                                <dt>Coupon ({{ $cart->coupon->code }})</dt>
                                <dd>−{{ price_format($discount) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold">
                            <dt>Total</dt><dd>{{ price_format(max(0, $cart->subtotal() - $discount)) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-1 text-xs text-gray-400">Shipping and tax calculated at checkout.</p>
                    <a href="{{ route('checkout.index') }}" class="mt-4 block rounded-md bg-indigo-600 px-6 py-3 text-center font-semibold text-white hover:bg-indigo-500">Checkout</a>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 p-4">
                    @if($cart->coupon)
                        <form action="{{ route('cart.coupon.remove') }}" method="POST" class="flex items-center justify-between text-sm">
                            @csrf @method('DELETE')
                            <span>Coupon <strong>{{ $cart->coupon->code }}</strong> applied</span>
                            <button class="text-red-500 hover:underline">Remove</button>
                        </form>
                    @else
                        <form action="{{ route('cart.coupon') }}" method="POST" class="flex gap-2">
                            @csrf
                            <input type="text" name="code" required placeholder="Coupon code" class="w-full rounded-md border-gray-300 text-sm uppercase" aria-label="Coupon code">
                            <button class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Apply</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
