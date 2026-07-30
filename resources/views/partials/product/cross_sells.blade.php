@if($product->crossSells->isNotEmpty())
    <x-pb-block :data="$block" class="mt-12">
        <section aria-labelledby="crosssell-heading">
            <h2 id="crosssell-heading" class="text-xl font-bold" style="color: var(--pb-heading, inherit)">{{ $block['heading'] ?? 'Frequently bought together' }}</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach($product->crossSells->take((int) ($block['limit'] ?? 4)) as $crossSell)
                    <x-product-card :product="$crossSell" />
                @endforeach
            </div>
        </section>
    </x-pb-block>
@endif
