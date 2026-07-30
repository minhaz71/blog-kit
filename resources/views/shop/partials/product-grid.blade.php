<div class="flex items-center justify-between gap-4">
    <p class="text-sm text-gray-500">{{ $products->total() }} {{ str('product')->plural($products->total()) }}</p>
    <form method="GET" class="text-sm">
        @foreach(request()->except('sort', 'page') as $key => $value)
            @if(is_array($value))
                @foreach($value as $subKey => $subValue)
                    <input type="hidden" name="{{ $key }}[{{ $subKey }}]" value="{{ $subValue }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <label class="sr-only" for="sort">Sort</label>
        <select id="sort" name="sort" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
            @foreach(['latest' => 'Latest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'best_selling' => 'Best selling', 'rating' => 'Top rated', 'name' => 'Name'] as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'latest') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

@if($products->isEmpty())
    <p class="mt-12 text-center text-gray-500">No products found. Try adjusting your filters.</p>
@else
    {{-- Bridges the page H1 → product-card H3 so the heading outline never
         skips a level (product cards are H3, reused across the site). --}}
    <h2 class="sr-only">Products</h2>
    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
        @foreach($products as $product)
            {{-- These listing pages have no hero, so the first row of cards
                 holds the LCP image — load it eagerly on page 1. --}}
            <x-product-card :product="$product" :eager="$products->onFirstPage() && $loop->index < 4" />
        @endforeach
    </div>
    <div class="mt-8">{{ $products->links() }}</div>
@endif
