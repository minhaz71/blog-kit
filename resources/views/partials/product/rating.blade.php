<x-pb-block :data="$block" class="mt-2">
    <div class="flex items-center gap-3">
        @if($product->reviews_count > 0)
            <x-rating-stars :rating="$product->avg_rating" :count="$product->reviews_count" />
        @else
            <span class="text-xs text-gray-400">No reviews yet</span>
        @endif
        @if($product->sku)
            <span class="text-xs text-gray-400" x-text="variation?.sku ? 'SKU: ' + variation.sku : 'SKU: {{ $product->sku }}'">SKU: {{ $product->sku }}</span>
        @endif
    </div>
</x-pb-block>
