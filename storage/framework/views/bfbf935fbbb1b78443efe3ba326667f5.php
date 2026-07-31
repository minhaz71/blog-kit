<?php
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
        // Cache raw arrays (not Eloquent Collections) — some cache stores can't
        // rehydrate Collection instances cleanly across requests.
        $navCategories = cache()->remember('nav.categories', 3600, fn () =>
            \App\Models\Category::active()->root()->orderBy('sort_order')->take(6)
                ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')->limit(6)])
                ->get(['id', 'name', 'slug'])
                ->map(fn ($c) => [
                    'id' => $c->id,
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
        ])->values();

        // The Shop link only makes sense with the ecommerce module on. A pure
        // blog site leads with Home + Blog instead (no product categories exist
        // to populate the fallback, so the list would otherwise be Shop+Blog).
        if (ecommerce_enabled()) {
            $menu = $menu->prepend(['label' => 'Shop', 'url' => route('shop'), 'children' => []]);
        } else {
            $menu = $menu->prepend(['label' => 'Home', 'url' => route('home'), 'children' => []]);
        }

        $menu = $menu->push(['label' => 'Blog', 'url' => route('blog.index'), 'children' => []])->all();
    }
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($announcement = setting('appearance.announcement_text')): ?>
    
    <div class="bg-gray-900 px-4 py-2 text-center text-xs font-medium text-white sm:text-sm">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($announcementUrl = setting('appearance.announcement_url')): ?>
            <a href="<?php echo e($announcementUrl); ?>" class="hover:underline"><?php echo e($announcement); ?></a>
        <?php else: ?>
            <?php echo e($announcement); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<header class="sticky top-0 z-40 border-b border-gray-200 bg-white"
        x-data="{
            menuOpen: false,
            cartOpen: false,
            cartCount: 0,
            cartHtml: null,
            async openCart() {
                this.cartOpen = true;
                this.cartHtml = await (await fetch('<?php echo e(route('cart.drawer')); ?>', { headers: { 'Accept': 'text/html' } })).text();
            },
            async refreshCart() {
                try {
                    this.cartHtml = await (await fetch('<?php echo e(route('cart.drawer')); ?>', { headers: { 'Accept': 'text/html' } })).text();
                } catch (e) {}
            },
        }"
        @cart:updated.document="cartCount = $event.detail.count; openCart()"
        @cart:count.document="cartCount = $event.detail.count"
        @cart:refresh.document="if (cartHtml !== null) refreshCart()"
        @keydown.escape.window="menuOpen = false; cartOpen = false">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex h-16 items-center justify-between gap-4">
            
            <button class="-ml-2 p-2 lg:hidden" @click="menuOpen = true" aria-label="Open menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <a href="<?php echo e(route('home')); ?>" class="flex shrink-0 items-center gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?>
                    <?php $logoDims = image_dimensions($logo); ?>
                    <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($storeName); ?>"
                         <?php if($logoDims): ?> width="<?php echo e($logoDims[0]); ?>" height="<?php echo e($logoDims[1]); ?>" <?php endif; ?>
                         class="h-9 w-auto max-w-[160px] object-contain">
                <?php else: ?>
                    <span class="text-xl font-bold tracking-tight"><?php echo e($storeName); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </a>

            <div class="flex items-center gap-1 sm:gap-3">
                <?php ($ajaxSearch = (bool) setting('search.ajax_enabled', true)); ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ecommerce_enabled()): ?>
                <div class="hsearch hidden md:block"
                     <?php if($ajaxSearch): ?> data-search data-endpoint="<?php echo e(route('search.suggest')); ?>" data-min-chars="<?php echo e(max(1, (int) setting('search.min_chars', 2))); ?>" <?php endif; ?>>
                    <form action="<?php echo e(route('search')); ?>" method="GET" role="search">
                        <input type="search" name="q" value="<?php echo e(request('q')); ?>" placeholder="Search products…"
                               autocomplete="off"
                               class="w-48 rounded-full border border-gray-300 px-4 py-1.5 text-sm focus:border-indigo-500 focus:outline-none"
                               aria-label="Search products">
                    </form>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ajaxSearch): ?><div class="hsearch-panel" data-search-panel hidden role="listbox"></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <a href="<?php echo e(auth()->check() ? route('account.dashboard') : route('login')); ?>" class="p-2 hover:text-indigo-600" aria-label="Account">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </a>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ecommerce_enabled()): ?>
                <button type="button" class="relative p-2 hover:text-indigo-600" aria-label="Open cart" @click="openCart()">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 4.6a1 1 0 00.9 1.4H19M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
                    <span class="absolute -right-0.5 -top-0.5 rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white"
                          x-text="cartCount" x-show="cartCount > 0" x-cloak></span>
                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($menu)): ?>
        <div class="hidden border-t border-gray-100 lg:block">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 py-1.5 text-sm font-medium" aria-label="Main">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php ($navActive = ($np = trim((string) parse_url($item['url'], PHP_URL_PATH), '/')) !== '' && (request()->is($np) || request()->is($np.'/*'))); ?>
                        <div class="group relative">
                            <a href="<?php echo e($item['url']); ?>"
                               <?php if($navActive): ?> aria-current="page" <?php endif; ?>
                               class="relative flex items-center gap-1 whitespace-nowrap py-2 transition-colors after:absolute after:inset-x-0 after:bottom-0.5 after:h-0.5 after:origin-left after:rounded-full after:bg-teal-600 after:transition-transform after:duration-200 <?php echo e($navActive ? 'text-teal-700 after:scale-x-100' : 'text-gray-700 hover:text-teal-600 after:scale-x-0 group-hover:after:scale-x-100'); ?>">
                                <?php echo e($item['label']); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($item['children'])): ?>
                                    <svg class="h-3 w-3 transition group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($item['children'])): ?>
                                <div class="absolute left-1/2 top-full z-20 hidden min-w-[220px] -translate-x-1/2 rounded-lg border border-gray-200 bg-white py-2 shadow-lg group-hover:block">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $item['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <a href="<?php echo e($child['url']); ?>" class="block whitespace-nowrap px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-teal-600"><?php echo e($child['label']); ?></a>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </nav>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ecommerce_enabled()): ?>
    <div class="border-t border-gray-100 px-4 py-2 md:hidden">
        <div class="hsearch"
             <?php if($ajaxSearch ?? (bool) setting('search.ajax_enabled', true)): ?> data-search data-endpoint="<?php echo e(route('search.suggest')); ?>" data-min-chars="<?php echo e(max(1, (int) setting('search.min_chars', 2))); ?>" <?php endif; ?>>
            <form action="<?php echo e(route('search')); ?>" method="GET" role="search">
                <input type="search" name="q" value="<?php echo e(request('q')); ?>" placeholder="Search products…"
                       autocomplete="off"
                       class="w-full rounded-full border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                       aria-label="Search products">
            </form>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ajaxSearch ?? (bool) setting('search.ajax_enabled', true)): ?><div class="hsearch-panel" data-search-panel hidden role="listbox"></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="fixed inset-0 z-40 bg-black/40" x-show="menuOpen || cartOpen" x-cloak
         x-transition.opacity @click="menuOpen = false; cartOpen = false"></div>

    
    <aside class="fixed inset-y-0 left-0 z-50 flex w-80 max-w-[85vw] flex-col bg-white shadow-xl lg:hidden"
           x-show="menuOpen" x-cloak
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
           role="dialog" aria-label="Menu">
        <div class="flex items-center justify-between border-b border-gray-200 p-4">
            <span class="text-lg font-bold"><?php echo e($storeName); ?></span>
            <button class="p-2" @click="menuOpen = false" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto p-3" aria-label="Mobile">
            <div class="flex flex-col gap-0.5 text-sm font-medium">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php ($mActive = ($mp = trim((string) parse_url($item['url'], PHP_URL_PATH), '/')) !== '' && (request()->is($mp) || request()->is($mp.'/*'))); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($item['children'])): ?>
                        <a href="<?php echo e($item['url']); ?>" <?php if($mActive): ?> aria-current="page" <?php endif; ?>
                           class="rounded px-3 py-2.5 transition-colors <?php echo e($mActive ? 'bg-teal-50 font-semibold text-teal-700' : 'hover:bg-gray-50 active:bg-gray-100'); ?>"><?php echo e($item['label']); ?></a>
                    <?php else: ?>
                        <details class="group rounded" <?php if($mActive): ?> open <?php endif; ?>>
                            <summary class="flex cursor-pointer list-none items-center justify-between rounded px-3 py-2.5 transition-colors <?php echo e($mActive ? 'bg-teal-50 font-semibold text-teal-700' : 'hover:bg-gray-50 active:bg-gray-100'); ?>">
                                <span><?php echo e($item['label']); ?></span>
                                <svg class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="ml-3 flex flex-col gap-0.5 border-l border-gray-200 py-1 pl-3">
                                <a href="<?php echo e($item['url']); ?>" class="rounded px-3 py-2 text-gray-600 transition-colors hover:bg-gray-50 hover:text-teal-600">All <?php echo e($item['label']); ?></a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $item['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <a href="<?php echo e($child['url']); ?>" class="rounded px-3 py-2 text-gray-600 transition-colors hover:bg-gray-50 hover:text-teal-600"><?php echo e($child['label']); ?></a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </details>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </nav>
        <div class="border-t border-gray-200 p-4 text-sm">
            <a href="<?php echo e(auth()->check() ? route('account.dashboard') : route('login')); ?>" class="font-medium text-indigo-600">
                <?php echo e(auth()->check() ? 'My account' : 'Sign in / Register'); ?>

            </a>
        </div>
    </aside>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ecommerce_enabled()): ?>
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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</header>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/header.blade.php ENDPATH**/ ?>