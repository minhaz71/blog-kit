@props(['product', 'eager' => false])
{{-- $eager: set true for the first row of a grid that has no hero (shop,
     category, search listings) so the LCP product image loads immediately
     instead of lazily. Below-the-fold cards keep lazy loading. --}}
{{-- Defensive eager-load: a no-op when the caller already loaded these,
     a safety net against N+1 when a caller forgets. This card renders in
     grid loops, so an unguarded brand/images access would be 2 queries × N. --}}
@php $product->loadMissing(['brand', 'images']); @endphp
<article class="group relative flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:shadow-md">
    <a href="{{ $product->url() }}" class="aspect-square overflow-hidden bg-gray-100">
        @if($image = $product->featuredImageWebpUrl())
            <img src="{{ $image }}" alt="{{ $product->featuredImageRecord()?->altText() ?: $product->name }}"
                 title="{{ $product->featuredImageRecord()?->titleText() ?: $product->name }}"
                 @if($eager) loading="eager" fetchpriority="high" @else loading="lazy" @endif
                 width="400" height="400"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-gray-300">
                <svg class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        @endif
    </a>

    @if($product->isOnSale() && $product->discountPercent())
        <span class="absolute left-2 top-2 rounded bg-red-600 px-2 py-0.5 text-xs font-bold text-white">-{{ $product->discountPercent() }}%</span>
    @elseif($product->is_new_arrival)
        <span class="absolute left-2 top-2 rounded bg-indigo-600 px-2 py-0.5 text-xs font-bold text-white">New</span>
    @endif

    <div class="flex flex-1 flex-col p-3">
        @if($product->brand)
            <p class="text-xs uppercase tracking-wide text-gray-400">{{ $product->brand->name }}</p>
        @endif
        <h3 class="mt-1 text-sm font-medium leading-snug">
            <a href="{{ $product->url() }}" class="hover:text-indigo-600">{{ $product->name }}</a>
        </h3>

        @if($product->reviews_count > 0)
            <x-rating-stars :rating="$product->avg_rating" :count="$product->reviews_count" class="mt-1" />
        @endif

        <div class="mt-auto pt-2">
            <x-price :product="$product" />
            @unless($product->isInStock())
                <p class="mt-1 text-xs font-medium text-red-600">Out of stock</p>
            @endunless
        </div>

        @if(setting('appearance.card_add_to_cart', true))
            @if($product->isInStock() && $product->type === 'simple')
                {{-- Stacked controls: full-width stepper with the add-to-cart
                     button underneath, so it stays tappable on 2-col mobile grids. --}}
                <div class="mt-2 space-y-1.5" x-data="{ qty: 1, busy: false, added: false }">
                    <div class="flex w-full items-center justify-between rounded border border-gray-300">
                        <button type="button"
                                class="flex h-9 w-10 shrink-0 items-center justify-center text-gray-600 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                                aria-label="Decrease quantity"
                                @click="qty = Math.max(1, qty - 1)">&minus;</button>
                        <input type="number" x-model.number="qty" min="1"
                               aria-label="Quantity"
                               class="h-9 w-full min-w-0 border-0 p-0 text-center text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none focus:ring-0">
                        <button type="button"
                                class="flex h-9 w-10 shrink-0 items-center justify-center text-gray-600 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                                aria-label="Increase quantity"
                                @click="qty++">+</button>
                    </div>
                    <button type="button"
                            class="h-10 w-full rounded bg-indigo-600 px-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="busy"
                            @click="busy = true;
                                    shopkit.addToCart({{ $product->id }}, Math.max(1, qty || 1))
                                        .then(() => { added = true; setTimeout(() => added = false, 1500) })
                                        .catch((e) => alert(e.message))
                                        .finally(() => busy = false)">
                        <span x-show="!added">Add to cart</span>
                        <span x-show="added" x-cloak>Added ✓</span>
                    </button>
                </div>
            @elseif($product->isInStock())
                <a href="{{ $product->url() }}"
                   class="mt-2 flex h-9 items-center justify-center rounded border border-indigo-600 px-2 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-600 hover:text-white">
                    Select options
                </a>
            @endif
        @endif
    </div>
</article>
