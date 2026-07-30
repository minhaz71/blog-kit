@if($product->description)
    {{-- .pd-content styles live in resources/css/app.css (external, cached). --}}
    <x-pb-block :data="$block" class="mt-12">
        @if(($block['layout'] ?? 'tabs') === 'tabs')
            <div x-data="{ tab: 'description' }">
                <div class="flex gap-1 border-b border-gray-200">
                    <button type="button" @click="tab = 'description'"
                            :class="tab === 'description' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'"
                            class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold">
                        {{ $block['heading'] ?? 'Description' }}
                    </button>
                    @if($block['show_reviews_tab'] ?? true)
                        <a href="#product-reviews"
                           class="-mb-px border-b-2 border-transparent px-4 py-2 text-sm font-semibold text-gray-500 hover:text-indigo-600">
                            Reviews ({{ $product->reviews_count }})
                        </a>
                    @endif
                </div>
                <div x-show="tab === 'description'" class="pd-content mt-4">{!! parse_shortcodes($product->description) !!}</div>
            </div>
        @else
            <section aria-labelledby="description-heading">
                <h2 id="description-heading" class="text-xl font-bold" style="color: var(--pb-heading, inherit)">{{ $block['heading'] ?? 'Description' }}</h2>
                <div class="pd-content mt-3">{!! parse_shortcodes($product->description) !!}</div>
            </section>
        @endif
    </x-pb-block>
@endif
