@php
    $cart = app(\App\Services\Cart\CartService::class)->current(false);
    $itemCount = $cart?->itemCount() ?? 0;
    $onCart = request()->routeIs('cart.*', 'checkout.*');
@endphp
@if($itemCount > 0 && ! $onCart)
    <div
        x-data="{
            open: false,
            init() {
                if (localStorage.getItem('cart-banner-dismissed') === '1') return;
                setTimeout(() => this.open = true, 4000);
            },
            dismiss() {
                localStorage.setItem('cart-banner-dismissed', '1');
                this.open = false;
            }
        }"
        x-show="open"
        x-transition
        x-cloak
        class="fixed bottom-4 left-4 right-4 z-30 max-w-md mx-auto rounded-xl border border-gray-200 bg-white shadow-lg p-4 lg:right-4 lg:left-auto lg:bottom-6 lg:mx-0"
        role="dialog"
        aria-label="Cart reminder"
        style="display:none;"
    >
        <div class="flex items-start gap-3">
            <div class="flex-1">
                <p class="text-sm font-semibold">You left {{ $itemCount }} {{ Str::plural('item', $itemCount) }} in your cart</p>
                <p class="mt-1 text-xs text-gray-600">Finish checking out before something sells out.</p>
                <a href="{{ route('cart.index') }}" class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:underline">View cart →</a>
            </div>
            <button type="button" @click="dismiss()" aria-label="Dismiss" class="text-gray-400 hover:text-gray-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
@endif
