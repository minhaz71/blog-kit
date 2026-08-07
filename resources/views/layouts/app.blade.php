<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Analytics / tag manager first, so GTM/gtag load as early as possible. --}}
    @include('partials.head-scripts')

    @include('partials.seo-head', ['seo' => $seo ?? null])

    {{-- Preconnect to the analytics origin only when tracking is configured —
         it saves a round-trip on the gtm/gtag request. (Preconnecting to our
         own origin is pointless: that connection is already open.) --}}
    @if(trim((string) setting('seo.google_tag_manager_id')) !== '' || trim((string) setting('seo.google_tag_id')) !== '')
        <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    @endif
    {!! vite_fonts_links() !!}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased flex flex-col" data-ecommerce="{{ ecommerce_enabled() ? '1' : '0' }}">
    @include('partials.body-scripts')

    {{-- Staff-only admin toolbar (never rendered for customers/guests). --}}
    @if(auth()->check() && auth()->user()->is_active && auth()->user()->isStaff())
        @include('partials.admin-bar')
    @endif

    @include('partials.header')

    @include('partials.flash')

    <main class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')
    {{-- Store-only surfaces: rendered only when the ecommerce module is on, so
         a blog install never ships cart-recovery or product-search JS. The code
         is retained (behind the flag) for when the store is re-enabled. --}}
    @if(ecommerce_enabled())
        @include('partials.cart-recovery-banner')
    @endif
    @include('partials.whatsapp-button')

    {{-- Live product search: deferred module, only loaded when the store is on
         AND ajax search is enabled. Self-initializes on window load. --}}
    @if(ecommerce_enabled() && (bool) setting('search.ajax_enabled', true))
        @vite('resources/js/search.js')
    @endif
</body>
</html>
