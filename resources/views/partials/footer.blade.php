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
@endphp
<footer class="mt-16 border-t border-gray-200 bg-gray-50">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
            <div class="col-span-2 md:col-span-1">
                <p class="text-lg font-bold">{{ $storeName }}</p>
                <p class="mt-2 text-sm text-gray-600">{{ setting('general.site_tagline', setting('seo.site_tagline', '')) }}</p>

                @if($fAddress || $fPhone || $fEmail || $fHours)
                    <address class="mt-4 space-y-2 text-sm not-italic text-gray-600">
                        @if($fAddress)
                            <p class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="whitespace-pre-line">{{ $fAddress }}</span>
                            </p>
                        @endif
                        @if($fPhone)
                            <p class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $fPhone) }}" class="hover:text-teal-600">{{ $fPhone }}</a>
                            </p>
                        @endif
                        @if($fEmail)
                            <p class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <a href="mailto:{{ $fEmail }}" class="hover:text-teal-600">{{ $fEmail }}</a>
                            </p>
                        @endif
                        @if($fHours)
                            <p class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="whitespace-pre-line">{{ $fHours }}</span>
                            </p>
                        @endif
                    </address>
                @endif
            </div>

            @forelse($columns as $column)
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $column['title'] }}</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach((array) ($column['links'] ?? []) as $link)
                            @if(! empty($link['label']) && ! empty($link['url']))
                                <li><a href="{{ $link['url'] }}" class="hover:text-indigo-600">{{ $link['label'] }}</a></li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @empty
                <div>
                    @if(ecommerce_enabled())
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Shop</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('shop') }}" class="hover:text-indigo-600">All products</a></li>
                            <li><a href="{{ route('shop', ['on_sale' => 1]) }}" class="hover:text-indigo-600">Sale</a></li>
                            <li><a href="{{ route('blog.index') }}" class="hover:text-indigo-600">Blog</a></li>
                        </ul>
                    @else
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Explore</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('home') }}" class="hover:text-indigo-600">Home</a></li>
                            <li><a href="{{ route('blog.index') }}" class="hover:text-indigo-600">Blog</a></li>
                        </ul>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Support</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li><a href="{{ url('/contact-us') }}" class="hover:text-indigo-600">Contact us</a></li>
                        <li><a href="{{ url('/shipping-policy') }}" class="hover:text-indigo-600">Shipping policy</a></li>
                        <li><a href="{{ url('/refund-policy') }}" class="hover:text-indigo-600">Refund policy</a></li>
                        <li><a href="{{ url('/faq') }}" class="hover:text-indigo-600">FAQ</a></li>
                    </ul>
                </div>
            @endforelse

            @if($showNewsletter)
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Newsletter</p>
                    <form action="{{ route('newsletter.subscribe') }}" method="POST" class="mt-3">
                        @csrf
                        <div class="flex gap-2">
                            <input type="email" name="email" required placeholder="Email address"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" aria-label="Email address">
                            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Join</button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">By subscribing you agree to our <a href="{{ url('/privacy-policy') }}" class="underline">privacy policy</a>.</p>
                    </form>
                </div>
            @endif
        </div>
        <div class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-gray-200 pt-6 text-sm text-gray-500 sm:flex-row">
            <p>{{ $footerText ?: '© '.date('Y').' '.$storeName.'. All rights reserved.' }}</p>
            <div class="flex gap-4">
                <a href="{{ url('/privacy-policy') }}" class="hover:text-indigo-600">Privacy</a>
                <a href="{{ url('/terms-and-conditions') }}" class="hover:text-indigo-600">Terms</a>
            </div>
        </div>
    </div>
</footer>
