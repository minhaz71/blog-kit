<x-pb-block :data="$block" class="mt-4">
    <div class="font-bold" style="font-size: inherit">
        <template x-if="variation">
            <span>
                <span x-text="variation.price"></span>
                <s class="ml-2 text-base font-normal text-gray-400" x-show="variation.regular" x-text="variation.regular"></s>
            </span>
        </template>
        <template x-if="!variation">
            <span>
                @if($product->isOnSale())
                    <span class="text-red-600">{{ price_format($product->currentPrice()) }}</span>
                    <s class="ml-2 text-base font-normal text-gray-400">{{ price_format($product->price) }}</s>
                @elseif($product->type === 'variable')
                    @php [$min, $max] = $product->priceRange(); @endphp
                    {{ $min === $max ? price_format($min) : price_format($min).' – '.price_format($max) }}
                @else
                    {{ price_format($product->currentPrice()) }}
                @endif
            </span>
        </template>
    </div>
    <p class="mt-2 text-sm font-medium"
       :class="(variation ? variation.in_stock : {{ $product->isInStock() ? 'true' : 'false' }}) ? 'text-green-600' : 'text-red-600'"
       x-text="(variation ? variation.in_stock : {{ $product->isInStock() ? 'true' : 'false' }}) ? 'In stock' : 'Out of stock'"></p>
</x-pb-block>
