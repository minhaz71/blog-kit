@php
    // Cart count is NEVER rendered server-side: guest pages are served from
    // the full-page cache, so the badge starts at 0 and hydrates via the
    // no-cache /cart/count endpoint (see shopkit.hydrateCartCount in app.js).
    $logo = setting('navigation.logo');
    $logoUrl = $logo ? Storage::disk('public')->url($logo) : null;
    $storeName = setting('general.site_name', setting('general.store_name', config('app.name')));

    // Admin-defined menu wins; otherwise fall back to top categories.
    $menu = collect((array) setting('navigation.header_menu', []))
        ->filter(fn ($i) => ! empty($i['label']) && ! empty($i['url']))
        ->values()
        ->all();

    if (empty($menu)) {
        $commerce = ecommerce_enabled();
        $autoBlogCats = (bool) setting('navigation.auto_blog_categories', true);

        // Blog category tree (mother → subs) for the auto menu. Only mothers
        // with posts or sub-categories are shown, so empty shells never appear.
        // Cache raw arrays (not Eloquent Collections) for cross-request safety.
        $blogNav = $autoBlogCats ? cache()->remember('nav.blog_categories', 3600, fn () =>
            \App\Models\PostCategory::query()->where('is_active', true)->whereNull('parent_id')
                ->where('show_in_menu', true)->orderBy('sort_order')->orderBy('name')->take(6)
                ->withCount('posts')
                ->with(['children' => fn ($q) => $q->where('is_active', true)->where('show_in_menu', true)->orderBy('sort_order')->limit(8)])
                ->get(['id', 'name', 'slug'])
                ->map(fn ($c) => [
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'posts_count' => $c->posts_count,
                    'children' => $c->children->map(fn ($ch) => ['name' => $ch->name, 'slug' => $ch->slug])->all(),
                ])
                ->filter(fn ($c) => $c['posts_count'] > 0 || count($c['children']) > 0)
                ->values()->all()
        ) : [];

        if ($commerce) {
            // Ecommerce: product categories lead; blog categories fold into one
            // "Blog" dropdown so the bar doesn't become a giant mixed list.
            $navCategories = cache()->remember('nav.categories', 3600, fn () =>
                \App\Models\Category::active()->root()->orderBy('sort_order')->take(6)
                    ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')->limit(6)])
                    ->get(['id', 'name', 'slug'])
                    ->map(fn ($c) => [
                        'name' => $c->name,
                        'slug' => $c->slug,
                        'children' => $c->children->map(fn ($ch) => ['name' => $ch->name, 'slug' => $ch->slug])->all(),
                    ])
                    ->all()
            );

            $menu = collect($navCategories)->map(fn ($c) => [
                'label' => $c['name'],
                'url' => \App\Support\Permalinks::category($c['slug']),
                'children' => collect($c['children'])->map(fn ($ch) => [
                    'label' => $ch['name'],
                    'url' => \App\Support\Permalinks::category($ch['slug']),
                ])->all(),
            ])->values()->prepend(['label' => 'Shop', 'url' => route('shop'), 'children' => []]);

            $blogChildren = collect($blogNav)->map(fn ($c) => ['label' => $c['name'], 'url' => route('blog.category', $c['slug'])])->all();
            $menu = $menu->push(['label' => 'Blog', 'url' => route('blog.index'), 'children' => $blogChildren])->all();
        } else {
            // Pure blog: mother categories are the top-level items, their
            // sub-categories the dropdown children.
            $menu = collect($blogNav)->map(fn ($c) => [
                'label' => $c['name'],
                'url' => route('blog.category', $c['slug']),
                'children' => collect($c['children'])->map(fn ($ch) => [
                    'label' => $ch['name'],
                    'url' => route('blog.category', $ch['slug']),
                ])->all(),
            ])
                ->prepend(['label' => 'Home', 'url' => route('home'), 'children' => []])
                ->push(['label' => 'Blog', 'url' => route('blog.index'), 'children' => []])
                ->all();
        }
    }

    // Header design tokens (Admin → Appearance → Header design).
    $h = \App\Support\HeaderStyle::tokens();
    $bar = $h['bar'];
    $isDark = $bar === 'dark';
    $navMode = $h['nav'];            // row2 | inline | drawer
    $logoCenter = $h['logo'] === 'center';
    $accent = $h['accent'];          // underline | pill | plain

    // Outer bar chrome.
    $headerChrome = match ($bar) {
        'shadow' => 'bg-white shadow-sm',
        'bordered' => 'bg-white border-y-2 border-gray-200',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white border-b border-gray-200', // plain + brandstrip
    };

    // For nav=drawer the hamburger + left drawer are the only nav even on
    // desktop, so they must show at every breakpoint.
    $hamburgerHidden = $navMode === 'drawer' ? '' : 'lg:hidden';

    // Desktop nav item spacing (pills sit tighter than underline/plain links).
    $navGap = $accent === 'pill' ? 'gap-x-1' : 'gap-x-6';

    // Per-item desktop nav link classes, keyed by accent + active + dark bar.
    $navLinkClass = function (bool $active) use ($accent, $isDark) {
        return match ($accent) {
            'pill' => 'flex items-center gap-1 whitespace-nowrap rounded-full px-3 py-1.5 transition-colors '
                . ($active
                    ? 'bg-brand-tint text-brand'
                    : ($isDark ? 'text-white/80 hover:bg-white/10 hover:text-white' : 'text-gray-700 hover:bg-brand-tint hover:text-brand')),
            'plain' => 'flex items-center gap-1 whitespace-nowrap py-2 transition-colors '
                . ($active
                    ? 'text-brand'
                    : ($isDark ? 'text-white/80 hover:text-white' : 'text-gray-700 hover:text-brand')),
            default => 'relative flex items-center gap-1 whitespace-nowrap py-2 transition-colors after:absolute after:inset-x-0 after:bottom-0.5 after:h-0.5 after:origin-left after:rounded-full after:bg-teal-600 after:transition-transform after:duration-200 '
                . ($active
                    ? ($isDark ? 'text-white after:scale-x-100' : 'text-teal-700 after:scale-x-100')
                    : ($isDark ? 'text-white/80 hover:text-white after:scale-x-0 group-hover:after:scale-x-100' : 'text-gray-700 hover:text-teal-600 after:scale-x-0 group-hover:after:scale-x-100')),
        };
    };
@endphp
@if($announcement = setting('appearance.announcement_text'))
    {{-- Slim announcement bar — text + optional link set under Appearance settings. --}}
    <div class="bg-gray-900 px-4 py-2 text-center text-xs font-medium text-white sm:text-sm">
        @if($announcementUrl = setting('appearance.announcement_url'))
            <a href="{{ $announcementUrl }}" class="hover:underline">{{ $announcement }}</a>
        @else
            {{ $announcement }}
        @endif
    </div>
@endif
<header class="sticky top-0 z-40 {{ $headerChrome }}"
        x-data="{
            menuOpen: false,
            cartOpen: false,
            cartCount: 0,
            cartHtml: null,
            async openCart() {
                this.cartOpen = true;
                this.cartHtml = await (await fetch('{{ route('cart.drawer') }}', { headers: { 'Accept': 'text/html' } })).text();
            },
            async refreshCart() {
                try {
                    this.cartHtml = await (await fetch('{{ route('cart.drawer') }}', { headers: { 'Accept': 'text/html' } })).text();
                } catch (e) {}
            },
        }"
        @cart:updated.document="cartCount = $event.detail.count; openCart()"
        @cart:count.document="cartCount = $event.detail.count"
        @cart:refresh.document="if (cartHtml !== null) refreshCart()"
        @keydown.escape.window="menuOpen = false; cartOpen = false">
    @if($bar === 'brandstrip')
        {{-- Thin theme-aware brand strip sitting above the white bar. --}}
        <div class="grad-brand h-1" aria-hidden="true"></div>
    @endif
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="relative flex h-16 items-center gap-4">
            {{-- Left cluster: menu button + (logo when left-aligned) --}}
            <div class="flex shrink-0 items-center gap-2">
                <button class="-ml-2 p-2 {{ $hamburgerHidden }}" @click="menuOpen = true" aria-label="Open menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                @unless($logoCenter)
                    <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2">
                        @if($logoUrl)
                            @php($logoDims = image_dimensions($logo))
                            <img src="{{ $logoUrl }}" alt="{{ $storeName }}"
                                 @if($logoDims) width="{{ $logoDims[0] }}" height="{{ $logoDims[1] }}" @endif
                                 class="h-9 w-auto max-w-[160px] object-contain">
                        @else
                            <span class="text-xl font-bold tracking-tight">{{ $storeName }}</span>
                        @endif
                    </a>
                @endunless
            </div>

            @if($navMode === 'inline' && ! empty($menu))
                {{-- Nav rendered inline within the top bar (lg and up). --}}
                <nav class="hidden flex-1 items-center {{ $navGap }} gap-y-1 text-sm font-medium lg:flex {{ $logoCenter ? 'justify-start' : 'justify-end' }}" aria-label="Main">
                    @foreach($menu as $item)
                        @php($navActive = ($np = trim((string) parse_url($item['url'], PHP_URL_PATH), '/')) !== '' && (request()->is($np) || request()->is($np.'/*')))
                        <div class="group relative">
                            <a href="{{ $item['url'] }}"
                               @if($navActive) aria-current="page" @endif
                               class="{{ $navLinkClass($navActive) }}">
                                {{ $item['label'] }}
                                @if(! empty($item['children']))
                                    <svg class="h-3 w-3 transition group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                @endif
                            </a>
                            @if(! empty($item['children']))
                                <div class="absolute left-1/2 top-full z-20 hidden min-w-[220px] -translate-x-1/2 rounded-lg border border-gray-200 bg-white py-2 shadow-lg group-hover:block">
                                    @foreach($item['children'] as $child)
                                        <a href="{{ $child['url'] }}" class="block whitespace-nowrap px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-teal-600">{{ $child['label'] }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>
            @else
                {{-- Spacer keeps the actions pushed right (and lets a centered logo sit between). --}}
                <div class="flex-1"></div>
            @endif

            @if($logoCenter)
                {{-- Absolutely centered logo overlay. --}}
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <a href="{{ route('home') }}" class="pointer-events-auto flex shrink-0 items-center gap-2">
                        @if($logoUrl)
                            @php($logoDims = image_dimensions($logo))
                            <img src="{{ $logoUrl }}" alt="{{ $storeName }}"
                                 @if($logoDims) width="{{ $logoDims[0] }}" height="{{ $logoDims[1] }}" @endif
                                 class="h-9 w-auto max-w-[160px] object-contain">
                        @else
                            <span class="text-xl font-bold tracking-tight">{{ $storeName }}</span>
                        @endif
                    </a>
                </div>
            @endif

            <div class="flex shrink-0 items-center gap-1 sm:gap-3">
                @php($ajaxSearch = (bool) setting('search.ajax_enabled', true))
                @if(ecommerce_enabled())
                <div class="hsearch hidden md:block"
                     @if($ajaxSearch) data-search data-endpoint="{{ route('search.suggest') }}" data-min-chars="{{ max(1, (int) setting('search.min_chars', 2)) }}" @endif>
                    <form action="{{ route('search') }}" method="GET" role="search">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products…"
                               autocomplete="off"
                               class="w-48 rounded-full border border-gray-300 px-4 py-1.5 text-sm focus:border-indigo-500 focus:outline-none"
                               aria-label="Search products">
                    </form>
                    @if($ajaxSearch)<div class="hsearch-panel" data-search-panel hidden role="listbox"></div>@endif
                </div>
                @endif

                {{-- Blog CTA — brand-filled, so the header carries the active theme. --}}
                @if($h['cta'])
                <a href="{{ route('home') }}#newsletter" class="bg-brand text-brand-fg hover:bg-brand-dark hidden rounded-full px-4 py-2 text-sm font-semibold transition sm:inline-block">
                    Subscribe
                </a>
                @endif
                <a href="{{ auth()->check() ? url('/admin') : route('login') }}" class="hover:text-brand p-2" aria-label="{{ auth()->check() ? 'Admin dashboard' : 'Sign in' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </a>

                @if(ecommerce_enabled())
                <button type="button" class="relative p-2 hover:text-indigo-600" aria-label="Open cart" @click="openCart()">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 4.6a1 1 0 00.9 1.4H19M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
                    <span class="absolute -right-0.5 -top-0.5 rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white"
                          x-text="cartCount" x-show="cartCount > 0" x-cloak></span>
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Desktop primary nav — its own full-width row so many items wrap
         cleanly onto a second line (each label stays on one line). Rendered
         only when the design places the nav on its own row. --}}
    @if($navMode === 'row2' && ! empty($menu))
        <div class="hidden border-t {{ $isDark ? 'border-white/10' : 'border-gray-100' }} lg:block">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <nav class="flex flex-wrap items-center justify-center {{ $navGap }} gap-y-1 py-1.5 text-sm font-medium" aria-label="Main">
                    @foreach($menu as $item)
                        @php($navActive = ($np = trim((string) parse_url($item['url'], PHP_URL_PATH), '/')) !== '' && (request()->is($np) || request()->is($np.'/*')))
                        <div class="group relative">
                            <a href="{{ $item['url'] }}"
                               @if($navActive) aria-current="page" @endif
                               class="{{ $navLinkClass($navActive) }}">
                                {{ $item['label'] }}
                                @if(! empty($item['children']))
                                    <svg class="h-3 w-3 transition group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                @endif
                            </a>
                            @if(! empty($item['children']))
                                <div class="absolute left-1/2 top-full z-20 hidden min-w-[220px] -translate-x-1/2 rounded-lg border border-gray-200 bg-white py-2 shadow-lg group-hover:block">
                                    @foreach($item['children'] as $child)
                                        <a href="{{ $child['url'] }}" class="block whitespace-nowrap px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-teal-600">{{ $child['label'] }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>
            </div>
        </div>
    @endif

    {{-- Mobile search bar: below the header bar, above page content --}}
    @if(ecommerce_enabled())
    <div class="border-t border-gray-100 px-4 py-2 md:hidden">
        <div class="hsearch"
             @if($ajaxSearch ?? (bool) setting('search.ajax_enabled', true)) data-search data-endpoint="{{ route('search.suggest') }}" data-min-chars="{{ max(1, (int) setting('search.min_chars', 2)) }}" @endif>
            <form action="{{ route('search') }}" method="GET" role="search">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products…"
                       autocomplete="off"
                       class="w-full rounded-full border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                       aria-label="Search products">
            </form>
            @if($ajaxSearch ?? (bool) setting('search.ajax_enabled', true))<div class="hsearch-panel" data-search-panel hidden role="listbox"></div>@endif
        </div>
    </div>
    @endif

    {{-- Overlay (shared by both drawers) --}}
    <div class="fixed inset-0 z-40 bg-black/40" x-show="menuOpen || cartOpen" x-cloak
         x-transition.opacity @click="menuOpen = false; cartOpen = false"></div>

    {{-- Left drawer: mobile menu --}}
    <aside class="fixed inset-y-0 left-0 z-50 flex w-80 max-w-[85vw] flex-col bg-white shadow-xl {{ $hamburgerHidden }}"
           x-show="menuOpen" x-cloak
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
           role="dialog" aria-label="Menu">
        <div class="flex items-center justify-between border-b border-gray-200 p-4">
            <span class="text-lg font-bold">{{ $storeName }}</span>
            <button class="p-2" @click="menuOpen = false" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto p-3" aria-label="Mobile">
            <div class="flex flex-col gap-0.5 text-sm font-medium">
                @foreach($menu as $item)
                    @php($mActive = ($mp = trim((string) parse_url($item['url'], PHP_URL_PATH), '/')) !== '' && (request()->is($mp) || request()->is($mp.'/*')))
                    @if(empty($item['children']))
                        <a href="{{ $item['url'] }}" @if($mActive) aria-current="page" @endif
                           class="rounded px-3 py-2.5 transition-colors {{ $mActive ? 'bg-teal-50 font-semibold text-teal-700' : 'hover:bg-gray-50 active:bg-gray-100' }}">{{ $item['label'] }}</a>
                    @else
                        <details class="group rounded" @if($mActive) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between rounded px-3 py-2.5 transition-colors {{ $mActive ? 'bg-teal-50 font-semibold text-teal-700' : 'hover:bg-gray-50 active:bg-gray-100' }}">
                                <span>{{ $item['label'] }}</span>
                                <svg class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="ml-3 flex flex-col gap-0.5 border-l border-gray-200 py-1 pl-3">
                                <a href="{{ $item['url'] }}" class="rounded px-3 py-2 text-gray-600 transition-colors hover:bg-gray-50 hover:text-teal-600">All {{ $item['label'] }}</a>
                                @foreach($item['children'] as $child)
                                    <a href="{{ $child['url'] }}" class="rounded px-3 py-2 text-gray-600 transition-colors hover:bg-gray-50 hover:text-teal-600">{{ $child['label'] }}</a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach
            </div>
        </nav>
        <div class="border-t border-gray-200 p-4 text-sm">
            <a href="{{ auth()->check() ? url('/admin') : route('login') }}" class="text-brand font-medium">
                {{ auth()->check() ? 'Admin dashboard' : 'Sign in' }}
            </a>
        </div>
    </aside>

    {{-- Right drawer: mini cart --}}
    @if(ecommerce_enabled())
    <aside class="fixed inset-y-0 right-0 z-50 flex w-96 max-w-[92vw] flex-col bg-white shadow-xl"
           x-show="cartOpen" x-cloak
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           role="dialog" aria-label="Shopping cart">
        <div class="flex items-center justify-between border-b border-gray-200 p-4">
            <span class="text-lg font-bold">Your cart</span>
            <button class="p-2" @click="cartOpen = false" aria-label="Close cart">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <template x-if="cartHtml === null">
            <div class="flex flex-1 items-center justify-center p-8 text-sm text-gray-500">Loading…</div>
        </template>
        <div class="flex flex-1 flex-col overflow-hidden" x-show="cartHtml !== null" x-html="cartHtml"></div>
    </aside>
    @endif
</header>
