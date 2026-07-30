<x-pb-block :data="$block">
    @if(($block['show_brand'] ?? true) && $product->brand)
        <p class="text-sm uppercase tracking-wide text-gray-400">{{ $product->brand->name }}</p>
    @endif
    <h1 class="mt-1 text-3xl font-bold" style="color: var(--pb-heading, inherit)">{{ $product->name }}</h1>
</x-pb-block>
