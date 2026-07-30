@php
    $limit = (int) $section->setting('limit', 8);
    $categoryId = $section->setting('category_id');
    $products = \App\Models\Product::visible()->when($categoryId, fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $categoryId)))->onSale()->with(['images', 'brand'])->take($limit)->get();
@endphp
@if($products->isNotEmpty())
    <section class="mt-4 overflow-hidden rounded-lg border border-red-200 bg-white shadow-sm" aria-label="{{ $section->title ?? 'Deals & offers' }}">
        <div class="flex items-center justify-between gap-3 border-b border-red-100 bg-red-50 px-4 py-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 truncate text-base font-bold text-red-700 sm:text-lg">
                    <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248z"/></svg>
                    {{ $section->title ?? 'Deals & offers' }}
                </h2>
                @if($section->subtitle)<p class="mt-0.5 truncate text-xs text-red-600/80">{{ $section->subtitle }}</p>@endif
            </div>
            <a href="{{ route('shop', ['on_sale' => 1]) }}" class="shrink-0 text-sm font-semibold text-red-700 hover:underline">All deals →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
            @foreach($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
@endif
