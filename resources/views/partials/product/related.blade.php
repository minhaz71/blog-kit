@if($related->isNotEmpty())
    <x-pb-block :data="$block" class="mt-12">
        <section aria-labelledby="related-heading">
            <h2 id="related-heading" class="text-xl font-bold" style="color: var(--pb-heading, inherit)">{{ $block['heading'] ?? 'Related products' }}</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach($related->take((int) ($block['limit'] ?? 4)) as $relatedProduct)
                    <x-product-card :product="$relatedProduct" />
                @endforeach
            </div>
        </section>
    </x-pb-block>
@endif
