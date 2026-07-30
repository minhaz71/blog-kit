@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6">
    @if($sections->isEmpty())
        {{-- Default fallback shown until the admin defines any sections. --}}
        <section class="mt-6 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-16 text-white sm:px-12 sm:py-24">
            <h1 class="max-w-2xl text-3xl font-extrabold tracking-tight sm:text-5xl text-balance">
                {{ setting('seo.homepage_title', setting('general.site_name', config('app.name'))) }}
            </h1>
            <p class="mt-4 max-w-xl text-lg text-indigo-100">
                {{ setting('seo.default_description', ecommerce_enabled() ? 'Quality products, fast shipping, secure checkout.' : 'Fresh articles, guides and stories.') }}
            </p>
            @if(ecommerce_enabled())
                <a href="{{ route('shop') }}" class="mt-8 inline-block rounded-full bg-white px-6 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                    Shop now
                </a>
            @else
                <a href="{{ route('blog.index') }}" class="mt-8 inline-block rounded-full bg-white px-6 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                    Read the blog
                </a>
            @endif
        </section>
        <p class="mt-8 text-center text-sm text-gray-500">
            Build this page from
            <a href="/admin/homepage-sections" class="text-indigo-600 underline">Admin → Content → Homepage sections</a>.
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
@endsection
