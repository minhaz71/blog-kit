@php
    $gallery = $product->images->isNotEmpty() ? $product->images : collect();
    $w = (int) ($block['image_width'] ?? $template->galleryWidth());
    $rounded = ($block['rounded'] ?? true) ? 'rounded-xl' : '';
@endphp
<x-pb-block :data="$block" x-data="{ active: 0 }">
    <div class="relative aspect-square overflow-hidden border border-gray-200 bg-gray-50 {{ $rounded }}">
        @if($product->isOnSale())
            <span class="absolute left-3 top-3 z-10 rounded-full bg-green-600 px-2.5 py-1 text-xs font-semibold text-white">Sale!</span>
        @endif
        <template x-if="variation?.image">
            <img :src="variation.image" alt="{{ $product->name }}" title="{{ $product->name }}" class="h-full w-full object-contain" width="{{ $w }}" height="{{ $w }}">
        </template>
        <template x-if="!variation?.image">
            <div class="h-full w-full">
                @if($gallery->isNotEmpty())
                    @foreach($gallery as $index => $image)
                        <img x-show="active === {{ $index }}" src="{{ $image->webpUrl() ?? $image->url() }}" alt="{{ $image->altText() }}" title="{{ $image->titleText() }}"
                             class="h-full w-full object-contain" width="{{ $w }}" height="{{ $w }}"
                             @if($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @endforeach
                @elseif($product->featuredImageWebpUrl())
                    <img src="{{ $product->featuredImageWebpUrl() }}" alt="{{ $product->name }}" title="{{ $product->name }}" class="h-full w-full object-contain" width="{{ $w }}" height="{{ $w }}" fetchpriority="high">
                @else
                    <div class="flex h-full w-full items-center justify-center text-gray-300">No image</div>
                @endif
            </div>
        </template>
    </div>
    {{-- Buyer-facing caption for the active image (when one is set). --}}
    @foreach($gallery as $index => $image)
        @if($image->caption)
            <p x-show="active === {{ $index }}" class="mt-2 text-center text-xs text-gray-500">{{ $image->caption }}</p>
        @endif
    @endforeach
    @if(($block['show_thumbnails'] ?? true) && $gallery->count() > 1)
        <div class="mt-3 flex gap-2.5 overflow-x-auto pb-1">
            @foreach($gallery as $index => $image)
                <button type="button" @click="active = {{ $index }}"
                        :class="active === {{ $index }} ? 'ring-2 ring-indigo-600 ring-offset-1 opacity-100' : 'opacity-70 hover:opacity-100'"
                        class="aspect-square h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 transition"
                        aria-label="Show image {{ $index + 1 }}">
                    <img src="{{ $image->webpUrl() ?? $image->url() }}" alt="{{ $image->altText() }}" title="{{ $image->titleText() }}" loading="lazy" width="80" height="80" class="h-full w-full object-cover">
                </button>
            @endforeach
        </div>
    @endif
</x-pb-block>
