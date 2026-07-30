@php $btn = $block['button_text'] ?? 'Add to cart'; @endphp
<x-pb-block :data="$block" class="mt-6">
    @if($product->type === 'external')
        <a href="{{ $product->external_url }}" rel="nofollow noopener" target="_blank"
           class="inline-block rounded-md bg-indigo-600 px-8 py-3 font-semibold text-white hover:bg-indigo-500">
            {{ $product->external_button_text ?: 'Buy now' }}
        </a>
    @elseif($product->type === 'grouped')
        <div class="space-y-3 rounded-lg border border-gray-200 p-4">
            @foreach($product->groupedChildren as $child)
                <div class="flex items-center justify-between gap-4 text-sm">
                    <a href="{{ $child->url() }}" class="font-medium hover:text-indigo-600">{{ $child->name }}</a>
                    <x-price :product="$child" />
                </div>
            @endforeach
        </div>
    @else
        <div class="flex items-center gap-3">
            @if($block['show_quantity'] ?? true)
                <div class="flex items-center overflow-hidden rounded-md border border-gray-300">
                    <button type="button"
                            class="px-3.5 py-2 text-lg leading-none text-gray-500 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                            @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity">−</button>
                    <input type="number" x-model.number="qty" min="1" class="w-14 border-0 text-center text-sm focus:ring-0" aria-label="Quantity">
                    <button type="button"
                            class="px-3.5 py-2 text-lg leading-none text-gray-500 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                            @click="qty++" aria-label="Increase quantity">+</button>
                </div>
            @endif
            <button type="button" @click="add()"
                    data-primary-add-to-cart
                    :disabled="requiresVariation && !variation"
                    class="flex-1 rounded-md bg-indigo-600 px-8 py-3 font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none">
                {{ $btn }}
            </button>
            @auth
                @if($block['show_wishlist'] ?? true)
                    <form action="{{ route('wishlist.toggle', $product) }}" method="POST">
                        @csrf
                        {{-- Wishlist = a "love" action: soft red hover, distinct from purchase buttons. --}}
                        <button class="rounded-md border border-gray-300 p-3 hover:border-red-300 hover:bg-red-50 hover:text-red-500" aria-label="Add to wishlist">♥</button>
                    </form>
                @endif
            @endauth
        </div>
        <p class="mt-2 text-sm text-indigo-600" x-text="message" x-show="message" style="display:none"></p>
    @endif
</x-pb-block>
