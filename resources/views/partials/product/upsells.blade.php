@if($product->upsells->isNotEmpty())
    <x-pb-block :data="$block" class="mt-8">
        <div class="rounded-lg bg-gray-50 p-4">
            <p class="text-sm font-semibold" style="color: var(--pb-heading, inherit)">{{ $block['heading'] ?? 'You may prefer' }}</p>
            <div class="mt-2 space-y-2">
                @foreach($product->upsells as $upsell)
                    <div class="flex items-center justify-between text-sm">
                        <a href="{{ $upsell->url() }}" class="hover:text-indigo-600">{{ $upsell->name }}</a>
                        <x-price :product="$upsell" />
                    </div>
                @endforeach
            </div>
        </div>
    </x-pb-block>
@endif
