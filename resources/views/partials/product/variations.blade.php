@if($variationAttributes->isNotEmpty())
    <x-pb-block :data="$block">
        @foreach($variationAttributes as $attribute)
            <div class="mt-5">
                <p class="text-sm font-semibold" style="color: var(--pb-heading, inherit)">{{ $attribute->name }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($product->attributeValues->where('attribute_id', $attribute->id) as $value)
                        <button type="button"
                                @click="select('{{ $attribute->slug }}', '{{ $value->slug }}')"
                                :class="selections['{{ $attribute->slug }}'] === '{{ $value->slug }}' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300'"
                                class="rounded-md border px-4 py-2 text-sm">
                            @if($attribute->type === 'color' && $value->color_code)
                                <span class="mr-1 inline-block h-3 w-3 rounded-full border align-middle" style="background: {{ $value->color_code }}"></span>
                            @endif
                            {{ $value->value }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </x-pb-block>
@endif
