@extends('layouts.app')

@php
    // The overall home page design (hero variant is chosen inside the hero
    // partial; here we apply the page-level treatment: background, width, gaps).
    $hs = \App\Support\HomeStyle::tokens();
    $pageBg = match ($hs['bg'] ?? 'white') {
        'tint' => 'bg-gray-50',
        'soft' => 'bg-brand-tint',
        'dark' => 'home--dark bg-gray-950 text-gray-100',
        default => '',
    };
    $wrapW = match ($hs['width'] ?? 'contained') {
        'wide' => 'max-w-screen-2xl',
        'narrow' => 'max-w-5xl',
        default => 'max-w-7xl',
    };
    // Full-bleed hero variants manage their own width; keep page padding light.
@endphp

@section('content')
<div class="home {{ $pageBg }} pb-16">
    <div class="mx-auto {{ $wrapW }} px-4 sm:px-6">
        @if($sections->isEmpty())
            {{-- Default fallback shown until the admin defines any sections. --}}
            <section class="grad-brand mt-6 rounded-3xl px-6 py-16 text-white sm:px-12 sm:py-24">
                <h1 class="max-w-2xl text-3xl font-extrabold tracking-tight text-balance sm:text-5xl">
                    {{ setting('seo.homepage_title', setting('general.site_name', config('app.name'))) }}
                </h1>
                <p class="mt-4 max-w-xl text-lg text-white/85">
                    {{ setting('seo.default_description', ecommerce_enabled() ? 'Quality products, fast shipping, secure checkout.' : 'Fresh articles, guides and stories.') }}
                </p>
                <a href="{{ ecommerce_enabled() ? route('shop') : route('blog.index') }}" class="text-brand hover:bg-brand-tint mt-8 inline-block rounded-full bg-white px-6 py-3 text-sm font-semibold">
                    {{ ecommerce_enabled() ? 'Shop now' : 'Read the blog' }}
                </a>
            </section>
            <p class="mt-8 text-center text-sm text-gray-500">
                Build this page from
                <a href="/admin/homepage-sections" class="text-brand underline">Admin → Content → Homepage sections</a>.
            </p>
        @else
            @foreach($sections as $section)
                @includeIf('partials.homepage.'.$section->type, ['section' => $section])
                @if($loop->first)
                    {{-- Everything below the first section is below the fold for critical CSS. --}}
                    <!--critical-fold-->
                @endif
            @endforeach
        @endif
    </div>
</div>
@endsection
