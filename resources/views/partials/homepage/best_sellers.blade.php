@php
    $limit = (int) $section->setting('limit', 8);
    $categoryId = $section->setting('category_id');
    $products = \App\Models\Product::visible()->when($categoryId, fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $categoryId)))->orderByDesc('sales_count')->with(['images', 'brand'])->take($limit)->get();
@endphp
@if($products->isNotEmpty())
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="{{ $section->title ?? 'Best sellers' }}">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 truncate text-base font-bold text-gray-900 sm:text-lg">
                    <span class="h-5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>{{ $section->title ?? 'Best sellers' }}
                </h2>
                @if($section->subtitle)<p class="mt-0.5 truncate text-xs text-gray-500">{{ $section->subtitle }}</p>@endif
            </div>
            <a href="{{ route('shop') }}" class="shrink-0 text-sm font-semibold text-teal-700 hover:underline">View all →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
            @foreach($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
@endif
