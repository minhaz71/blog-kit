@php
    /**
     * Category catalogue: admin-picked categories as compact marketplace
     * tiles with product-count pills; horizontally scrollable on mobile.
     */
    $columns = (int) ($section->setting('columns', 4) ?: 4);
    $columns = in_array($columns, [2, 3, 4], true) ? $columns : 4;
    $rows = max(1, min(3, (int) ($section->setting('rows', 2) ?: 2)));
    $limit = $columns * $rows;
    $showCount = (bool) $section->setting('show_count', true);

    $slugs = collect((array) $section->setting('categories', []))->filter()->values();

    $query = \App\Models\Category::active()
        ->withCount(['products' => fn ($q) => $q->where('status', 'published')->where('visibility', 'visible')]);

    if ($slugs->isNotEmpty()) {
        $categories = $query->whereIn('slug', $slugs)->get()
            ->sortBy(fn ($c) => $slugs->search($c->slug))
            ->values()
            ->take($limit);
    } else {
        // Auto-fill fallback: fullest categories first.
        $categories = $query->orderByDesc('products_count')->take($limit)->get();
    }
@endphp
@if($categories->isNotEmpty())
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="{{ $section->title ?? 'Browse popular categories' }}">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 truncate text-base font-bold text-gray-900 sm:text-lg">
                    <span class="h-5 w-1.5 shrink-0 rounded-full bg-teal-600"></span>{{ $section->title ?? 'Browse Popular Categories' }}
                </h2>
                @if($section->subtitle)<p class="mt-0.5 truncate text-xs text-gray-500">{{ $section->subtitle }}</p>@endif
            </div>
            <a href="{{ route('shop') }}" class="shrink-0 text-sm font-semibold text-teal-700 hover:underline">All products →</a>
        </div>
        <div class="flex snap-x gap-3 overflow-x-auto p-4 sm:grid sm:snap-none sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-{{ max(4, $columns + 2) }}">
            @foreach($categories as $category)
                <a href="{{ $category->url() }}" class="group w-32 shrink-0 snap-start sm:w-auto">
                    <div class="relative aspect-square overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                        @if($category->imageUrl())
                            <img src="{{ $category->imageUrl() }}"
                                 alt="{{ $category->image_alt ?: $category->name }}"
                                 title="{{ $category->image_alt ?: $category->name }}"
                                 loading="lazy" width="400" height="400"
                                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        @else
                            <span class="flex h-full w-full items-center justify-center text-3xl font-bold text-gray-300">{{ mb_substr($category->name, 0, 1) }}</span>
                        @endif
                        @if($showCount)
                            <span class="absolute right-1.5 top-1.5 rounded-full bg-white/90 px-2 py-0.5 text-[0.65rem] font-bold text-gray-700 shadow-sm">{{ $category->products_count }}</span>
                        @endif
                    </div>
                    <p class="mt-1.5 truncate text-center text-sm font-semibold text-gray-800 group-hover:text-teal-700">{{ $category->name }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endif
