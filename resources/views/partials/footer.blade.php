@php
    $storeName = setting('general.site_name', setting('general.store_name', config('app.name')));
    $columns = collect((array) setting('navigation.footer_columns', []))
        ->filter(fn ($c) => ! empty($c['title']))
        ->values();
    $footerText = setting('navigation.footer_text');
    $showNewsletter = setting('navigation.show_newsletter', true);
    $fAddress = trim((string) setting('navigation.footer_address', ''));
    $fPhone = trim((string) setting('navigation.footer_phone', setting('general.contact_phone', '')));
    $fEmail = trim((string) setting('navigation.footer_email', setting('general.contact_email', '')));
    $fHours = trim((string) setting('navigation.footer_hours', ''));
    $tagline = setting('general.site_tagline', setting('seo.site_tagline', 'Fresh writing, published fast.'));

    $f = \App\Support\FooterStyle::tokens();
    $layout = $f['layout'];
    $bg = $f['bg'];

    // Newsletter shows only when the global toggle AND the style token agree.
    // The `cta` layout centers the whole footer on the signup regardless.
    $showNews = ($showNewsletter && ! empty($f['newsletter'])) || $layout === 'cta';

    // Background-driven class tokens.
    $footerBg = match ($bg) {
        'soft'  => 'bg-brand-tint text-gray-900',
        'dark'  => 'bg-gray-900 text-gray-300',
        'brand' => 'grad-brand text-white',
        default => 'bg-gray-50 text-gray-900',
    };
    $topBorder = in_array($bg, ['light', 'soft'], true) ? 'border-t border-gray-200' : '';
    $headingCls = match ($bg) {
        'dark'  => 'text-gray-400',
        'brand' => 'text-white/80',
        default => 'text-gray-500',
    };
    $mutedCls = match ($bg) {
        'dark'  => 'text-gray-400',
        'brand' => 'text-white/85',
        default => 'text-gray-600',
    };
    $iconCls = match ($bg) {
        'dark'  => 'text-gray-500',
        'brand' => 'text-white/70',
        default => 'text-gray-400',
    };
    $linkCls = match ($bg) {
        'dark'  => 'hover:text-white',
        'brand' => 'text-white/85 hover:text-white',
        default => 'hover:text-brand',
    };
    $storeNameCls = in_array($bg, ['dark', 'brand'], true) ? 'text-white' : '';
    $inputCls = match ($bg) {
        'dark'  => 'border border-gray-700 bg-gray-800 text-white placeholder-gray-500',
        'brand' => 'border border-white/40 bg-white/10 text-white placeholder-white/60',
        default => 'border border-gray-300 bg-white text-gray-900',
    };
    $joinBtn = $bg === 'brand'
        ? 'bg-gray-900 text-white hover:bg-gray-800'
        : 'bg-brand text-brand-fg hover:bg-brand-dark';
    $dividerCls = match ($bg) {
        'dark'  => 'border-gray-800',
        'brand' => 'border-white/20',
        default => 'border-gray-200',
    };
    $bottomTextCls = match ($bg) {
        'dark'  => 'text-gray-400',
        'brand' => 'text-white/80',
        default => 'text-gray-500',
    };

    $copyright = $footerText ?: '© '.date('Y').' '.$storeName.'. All rights reserved.';
@endphp

<footer class="mt-16 {{ $footerBg }} {{ $topBorder }}">
    @if(($f['rule'] ?? 'none') === 'brand')
        {{-- Brand accent rule across the very top of the footer. --}}
        <div class="grad-brand h-1 w-full"></div>
    @endif

    @switch($layout)

    {{-- ============================ MINIMAL ============================ --}}
    @case('minimal')
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="bg-brand h-5 w-1.5 shrink-0 rounded-full"></span>
                    <span class="text-base font-bold {{ $storeNameCls }}">{{ $storeName }}</span>
                </a>
                <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm">
                    <a href="{{ route('home') }}" class="{{ $linkCls }}">Home</a>
                    <a href="{{ route('blog.index') }}" class="{{ $linkCls }}">Blog</a>
                    <a href="{{ url('/contact-us') }}" class="{{ $linkCls }}">Contact</a>
                    <a href="{{ url('/privacy-policy') }}" class="{{ $linkCls }}">Privacy</a>
                    <a href="{{ url('/terms-and-conditions') }}" class="{{ $linkCls }}">Terms</a>
                </nav>
                @if($showNews)
                    <form action="{{ route('newsletter.subscribe') }}" method="POST" class="flex gap-2">
                        @csrf
                        <input type="email" name="email" required placeholder="Email address"
                               class="w-40 rounded-md px-3 py-2 text-sm {{ $inputCls }}" aria-label="Email address">
                        <button class="{{ $joinBtn }} rounded-md px-4 py-2 text-sm font-semibold transition">Join</button>
                    </form>
                @endif
            </div>
            <p class="mt-4 text-center text-sm {{ $bottomTextCls }} sm:text-left">{{ $copyright }}</p>
        </div>
        @break

    {{-- ============================ CENTERED ============================ --}}
    @case('centered')
        <div class="mx-auto max-w-3xl px-4 py-12 text-center sm:px-6">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                <span class="bg-brand h-6 w-1.5 shrink-0 rounded-full"></span>
                <span class="text-lg font-bold {{ $storeNameCls }}">{{ $storeName }}</span>
            </a>
            <p class="mx-auto mt-3 max-w-xl text-sm {{ $mutedCls }}">{{ $tagline }}</p>

            <nav class="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm">
                <a href="{{ route('home') }}" class="{{ $linkCls }}">Home</a>
                <a href="{{ route('blog.index') }}" class="{{ $linkCls }}">Blog</a>
                <a href="{{ url('/about') }}" class="{{ $linkCls }}">About</a>
                <a href="{{ url('/contact-us') }}" class="{{ $linkCls }}">Contact</a>
                <a href="{{ url('/faq') }}" class="{{ $linkCls }}">FAQ</a>
            </nav>

            @if($showNews)
                <form action="{{ route('newsletter.subscribe') }}" method="POST" class="mx-auto mt-8 max-w-md">
                    @csrf
                    <div class="flex gap-2">
                        <input type="email" name="email" required placeholder="Email address"
                               class="w-full rounded-md px-3 py-2 text-sm {{ $inputCls }}" aria-label="Email address">
                        <button class="{{ $joinBtn }} rounded-md px-4 py-2 text-sm font-semibold transition">Join</button>
                    </div>
                    <p class="mt-2 text-xs {{ $mutedCls }}">By subscribing you agree to our <a href="{{ url('/privacy-policy') }}" class="underline">privacy policy</a>.</p>
                </form>
            @endif

            <div class="mt-10 border-t {{ $dividerCls }} pt-6">
                <div class="flex justify-center gap-4 text-sm">
                    <a href="{{ url('/privacy-policy') }}" class="{{ $linkCls }}">Privacy</a>
                    <a href="{{ url('/terms-and-conditions') }}" class="{{ $linkCls }}">Terms</a>
                </div>
                <p class="mt-3 text-sm {{ $bottomTextCls }}">{{ $copyright }}</p>
            </div>
        </div>
        @break

    {{-- ============================ BIG CTA ============================ --}}
    @case('cta')
        {{-- Large brand newsletter band. --}}
        <div class="grad-brand text-white">
            <div class="mx-auto max-w-3xl px-4 py-14 text-center sm:px-6">
                <h2 class="text-2xl font-bold sm:text-3xl">Join the {{ $storeName }} newsletter</h2>
                <p class="mx-auto mt-3 max-w-xl text-white/85">{{ $tagline }}</p>
                <form action="{{ route('newsletter.subscribe') }}" method="POST" class="mx-auto mt-8 max-w-md">
                    @csrf
                    <div class="flex gap-2">
                        <input type="email" name="email" required placeholder="Email address"
                               class="w-full rounded-md border border-white/40 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/60" aria-label="Email address">
                        <button class="bg-gray-900 text-white hover:bg-gray-800 rounded-md px-5 py-2 text-sm font-semibold transition">Join</button>
                    </div>
                    <p class="mt-2 text-xs text-white/80">By subscribing you agree to our <a href="{{ url('/privacy-policy') }}" class="underline">privacy policy</a>.</p>
                </form>
            </div>
        </div>
        {{-- Slim link + copyright row on a light background. --}}
        <div class="bg-gray-50 border-t border-gray-200 text-gray-900">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-6 text-sm text-gray-500 sm:flex-row sm:px-6">
                <p>{{ $copyright }}</p>
                <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                    <a href="{{ route('home') }}" class="hover:text-brand">Home</a>
                    <a href="{{ route('blog.index') }}" class="hover:text-brand">Blog</a>
                    <a href="{{ url('/contact-us') }}" class="hover:text-brand">Contact</a>
                    <a href="{{ url('/privacy-policy') }}" class="hover:text-brand">Privacy</a>
                    <a href="{{ url('/terms-and-conditions') }}" class="hover:text-brand">Terms</a>
                </nav>
            </div>
        </div>
        @break

    {{-- ============================ MEGA ============================ --}}
    @case('mega')
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
            <div class="grid gap-12 lg:grid-cols-12">
                {{-- Big brand wordmark + contact. --}}
                <div class="lg:col-span-4">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                        <span class="bg-brand h-10 w-2 shrink-0 rounded-full"></span>
                        <span class="text-3xl font-extrabold tracking-tight sm:text-4xl {{ $storeNameCls }}">{{ $storeName }}</span>
                    </a>
                    <p class="mt-4 max-w-sm text-sm {{ $mutedCls }}">{{ $tagline }}</p>

                    @if($fAddress || $fPhone || $fEmail || $fHours)
                        <address class="mt-6 space-y-2 text-sm not-italic {{ $mutedCls }}">
                            @if($fAddress)
                                <p class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="whitespace-pre-line">{{ $fAddress }}</span>
                                </p>
                            @endif
                            @if($fPhone)
                                <p class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $fPhone) }}" class="{{ $linkCls }}">{{ $fPhone }}</a>
                                </p>
                            @endif
                            @if($fEmail)
                                <p class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <a href="mailto:{{ $fEmail }}" class="{{ $linkCls }}">{{ $fEmail }}</a>
                                </p>
                            @endif
                            @if($fHours)
                                <p class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="whitespace-pre-line">{{ $fHours }}</span>
                                </p>
                            @endif
                        </address>
                    @endif
                </div>

                {{-- Link columns + newsletter. --}}
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-8">
                    @forelse($columns as $column)
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">{{ $column['title'] }}</p>
                            <ul class="mt-3 space-y-2 text-sm">
                                @foreach((array) ($column['links'] ?? []) as $link)
                                    @if(! empty($link['label']) && ! empty($link['url']))
                                        <li><a href="{{ $link['url'] }}" class="{{ $linkCls }}">{{ $link['label'] }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <div>
                            @if(ecommerce_enabled())
                                <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Shop</p>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <li><a href="{{ route('shop') }}" class="{{ $linkCls }}">All products</a></li>
                                    <li><a href="{{ route('shop', ['on_sale' => 1]) }}" class="{{ $linkCls }}">Sale</a></li>
                                    <li><a href="{{ route('blog.index') }}" class="{{ $linkCls }}">Blog</a></li>
                                </ul>
                            @else
                                <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Explore</p>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <li><a href="{{ route('home') }}" class="{{ $linkCls }}">Home</a></li>
                                    <li><a href="{{ route('blog.index') }}" class="{{ $linkCls }}">Latest posts</a></li>
                                    @foreach(\App\Models\PostCategory::query()->withCount(['posts' => fn ($q) => $q->published()])->having('posts_count', '>', 0)->orderByDesc('posts_count')->take(4)->get() as $cat)
                                        <li><a href="{{ route('blog.category', $cat->slug) }}" class="{{ $linkCls }}">{{ $cat->name }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Company</p>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li><a href="{{ url('/about') }}" class="{{ $linkCls }}">About</a></li>
                                <li><a href="{{ url('/contact-us') }}" class="{{ $linkCls }}">Contact</a></li>
                                <li><a href="{{ url('/faq') }}" class="{{ $linkCls }}">FAQ</a></li>
                            </ul>
                        </div>
                    @endforelse

                    @if($showNews)
                        <div class="col-span-2 sm:col-span-1">
                            <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Newsletter</p>
                            <form action="{{ route('newsletter.subscribe') }}" method="POST" class="mt-3">
                                @csrf
                                <div class="flex gap-2">
                                    <input type="email" name="email" required placeholder="Email address"
                                           class="w-full rounded-md px-3 py-2 text-sm {{ $inputCls }}" aria-label="Email address">
                                    <button class="{{ $joinBtn }} rounded-md px-4 py-2 text-sm font-semibold transition">Join</button>
                                </div>
                                <p class="mt-2 text-xs {{ $mutedCls }}">By subscribing you agree to our <a href="{{ url('/privacy-policy') }}" class="underline">privacy policy</a>.</p>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t {{ $dividerCls }} pt-6 text-sm {{ $bottomTextCls }} sm:flex-row">
                <p>{{ $copyright }}</p>
                <div class="flex gap-4">
                    <a href="{{ url('/privacy-policy') }}" class="{{ $linkCls }}">Privacy</a>
                    <a href="{{ url('/terms-and-conditions') }}" class="{{ $linkCls }}">Terms</a>
                </div>
            </div>
        </div>
        @break

    {{-- ============================ COLUMNS (default) ============================ --}}
    @default
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                <div class="col-span-2 md:col-span-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2">
                        <span class="bg-brand h-6 w-1.5 shrink-0 rounded-full"></span>
                        <span class="text-lg font-bold {{ $storeNameCls }}">{{ $storeName }}</span>
                    </a>
                    <p class="mt-2 text-sm {{ $mutedCls }}">{{ $tagline }}</p>

                    @if($fAddress || $fPhone || $fEmail || $fHours)
                        <address class="mt-4 space-y-2 text-sm not-italic {{ $mutedCls }}">
                            @if($fAddress)
                                <p class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="whitespace-pre-line">{{ $fAddress }}</span>
                                </p>
                            @endif
                            @if($fPhone)
                                <p class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $fPhone) }}" class="{{ $linkCls }}">{{ $fPhone }}</a>
                                </p>
                            @endif
                            @if($fEmail)
                                <p class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <a href="mailto:{{ $fEmail }}" class="{{ $linkCls }}">{{ $fEmail }}</a>
                                </p>
                            @endif
                            @if($fHours)
                                <p class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $iconCls }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="whitespace-pre-line">{{ $fHours }}</span>
                                </p>
                            @endif
                        </address>
                    @endif
                </div>

                @forelse($columns as $column)
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">{{ $column['title'] }}</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach((array) ($column['links'] ?? []) as $link)
                                @if(! empty($link['label']) && ! empty($link['url']))
                                    <li><a href="{{ $link['url'] }}" class="{{ $linkCls }}">{{ $link['label'] }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div>
                        @if(ecommerce_enabled())
                            <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Shop</p>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li><a href="{{ route('shop') }}" class="{{ $linkCls }}">All products</a></li>
                                <li><a href="{{ route('shop', ['on_sale' => 1]) }}" class="{{ $linkCls }}">Sale</a></li>
                                <li><a href="{{ route('blog.index') }}" class="{{ $linkCls }}">Blog</a></li>
                            </ul>
                        @else
                            <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Explore</p>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li><a href="{{ route('home') }}" class="{{ $linkCls }}">Home</a></li>
                                <li><a href="{{ route('blog.index') }}" class="{{ $linkCls }}">Latest posts</a></li>
                                @foreach(\App\Models\PostCategory::query()->withCount(['posts' => fn ($q) => $q->published()])->having('posts_count', '>', 0)->orderByDesc('posts_count')->take(4)->get() as $cat)
                                    <li><a href="{{ route('blog.category', $cat->slug) }}" class="{{ $linkCls }}">{{ $cat->name }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Company</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ url('/about') }}" class="{{ $linkCls }}">About</a></li>
                            <li><a href="{{ url('/contact-us') }}" class="{{ $linkCls }}">Contact</a></li>
                            <li><a href="{{ url('/faq') }}" class="{{ $linkCls }}">FAQ</a></li>
                        </ul>
                    </div>
                @endforelse

                @if($showNews)
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide {{ $headingCls }}">Newsletter</p>
                        <form action="{{ route('newsletter.subscribe') }}" method="POST" class="mt-3">
                            @csrf
                            <div class="flex gap-2">
                                <input type="email" name="email" required placeholder="Email address"
                                       class="w-full rounded-md px-3 py-2 text-sm {{ $inputCls }}" aria-label="Email address">
                                <button class="{{ $joinBtn }} rounded-md px-4 py-2 text-sm font-semibold transition">Join</button>
                            </div>
                            <p class="mt-2 text-xs {{ $mutedCls }}">By subscribing you agree to our <a href="{{ url('/privacy-policy') }}" class="underline">privacy policy</a>.</p>
                        </form>
                    </div>
                @endif
            </div>

            <div class="mt-10 flex flex-col items-center justify-between gap-4 border-t {{ $dividerCls }} pt-6 text-sm {{ $bottomTextCls }} sm:flex-row">
                <p>{{ $copyright }}</p>
                <div class="flex gap-4">
                    <a href="{{ url('/privacy-policy') }}" class="{{ $linkCls }}">Privacy</a>
                    <a href="{{ url('/terms-and-conditions') }}" class="{{ $linkCls }}">Terms</a>
                </div>
            </div>
        </div>
    @endswitch
</footer>
