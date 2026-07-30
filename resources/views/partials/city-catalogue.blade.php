@php
    /**
     * Standalone category catalogue for content pages (city delivery pages).
     * Same .catalogue-* styling as the homepage section, but self-contained
     * (no HomepageSection needed) so any page can route visitors to the
     * category pages. Cached list, active categories only.
     */
    $cityCategories = \Illuminate\Support\Facades\Cache::remember(
        'city-catalogue.v'.((int) \Illuminate\Support\Facades\Cache::get('pagecache.version', 1)),
        now()->addDay(),
        fn () => \App\Models\Category::active()
            ->withCount(['products' => fn ($q) => $q->where('status', 'published')->where('visibility', 'visible')])
            ->orderByDesc('products_count')
            ->take(8)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'url' => $c->url(),
                'image' => $c->imageUrl(),
                'alt' => $c->image_alt ?: $c->name,
                'count' => $c->products_count,
                'initial' => mb_substr($c->name, 0, 1),
            ])
            ->all()
    );
@endphp
@if(! empty($cityCategories))
    <section class="catalogue{{ ($flush ?? false) ? ' catalogue--flush' : '' }}" aria-label="Shop TEREA by category">
        @unless($hideHead ?? false)
            <div class="catalogue-head">
                <h2 class="catalogue-title">Shop TEREA by Category</h2>
                <p class="catalogue-subtitle">Browse every genuine IQOS TEREA range we deliver, then order in a couple of taps.</p>
            </div>
        @endunless
        <div class="catalogue-grid cols-4">
            @foreach($cityCategories as $category)
                <a href="{{ $category['url'] }}" class="catalogue-card">
                    <div class="catalogue-media">
                        @if($category['image'])
                            <img src="{{ $category['image'] }}" alt="{{ $category['alt'] }}" title="{{ $category['alt'] }}"
                                 loading="lazy" width="400" height="400">
                        @else
                            <span class="catalogue-media-empty">{{ $category['initial'] }}</span>
                        @endif
                    </div>
                    <div class="catalogue-meta">
                        <span class="catalogue-name">{{ $category['name'] }}</span>
                        <span class="catalogue-count">{{ $category['count'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
