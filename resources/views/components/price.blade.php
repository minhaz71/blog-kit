@props(['product'])
@php [$min, $max] = $product->priceRange(); @endphp
<p {{ $attributes->merge(['class' => 'text-sm font-semibold']) }}>
    @if($product->type === 'variable' && $min !== $max)
        {{ price_format($min) }} – {{ price_format($max) }}
    @elseif($product->isOnSale())
        <span class="text-red-600">{{ price_format($product->currentPrice()) }}</span>
        <s class="ml-1 font-normal text-gray-400">{{ price_format($product->price) }}</s>
    @else
        {{ price_format($product->currentPrice()) }}
    @endif
</p>
