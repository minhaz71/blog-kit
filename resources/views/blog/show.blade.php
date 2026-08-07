@extends('layouts.app')

@php
    // Resolve the layout style: per-post override wins, else the site default
    // (Admin → Appearance → Blog post style). See App\Support\PostStyle.
    $t = \App\Support\PostStyle::tokens(\App\Support\PostStyle::resolveKey($post->post_style ?? null));
    $maxW = ($t['width'] ?? 'narrow') === 'wide' ? 'max-w-4xl' : 'max-w-3xl';
    $fontClass = ($t['font'] ?? 'sans') === 'serif' ? 'font-serif' : '';
    $titleClass = match ($t['title'] ?? 'lg') {
        'display' => 'text-4xl font-extrabold sm:text-6xl',
        'xl' => 'text-4xl font-bold sm:text-5xl',
        default => 'text-3xl font-bold sm:text-4xl',
    };
    $hasHero = empty($t['noHero']) && $post->featuredImageUrl();
    $bodyClass = ($t['dropcap'] ?? false ? 'bd-dropcap ' : '').$fontClass;
    $tocInline = ($t['toc'] ?? 'inline') === 'inline';
    $tocSidebar = ($t['toc'] ?? '') === 'sidebar';
    $eyebrow = fn ($light = false) => $post->category
        ? '<a href="'.route('blog.category', $post->category->slug).'" class="text-xs font-semibold uppercase tracking-widest '.($light ? 'text-white/90' : 'text-brand').'">'.e($post->category->name).'</a>'
        : '';
@endphp

@section('content')
@if (! empty($isPreview))
    <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
        Draft preview — not visible to visitors.
    </div>
@endif

@switch($t['layout'])

{{-- ── Sidebar (Editorial / Documentation): sticky meta + contents left ── --}}
@case('sidebar')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :crumbs="$seo->breadcrumbs" />
        <div class="mt-6 grid gap-10 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                {!! $eyebrow() !!}
                @include('blog.partials.meta')
                @if($tocSidebar)@include('blog.partials.toc', ['sidebar' => true])@endif
            </aside>
            <div class="min-w-0">
                <h1 class="{{ $titleClass }} tracking-tight text-balance text-gray-900 {{ $fontClass }}">{{ $post->title }}</h1>
                @if($hasHero)
                    <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" class="mt-6 w-full rounded-2xl object-cover">
                @endif
                @include('blog.partials.body')
                @include('blog.partials.endmatter')
            </div>
        </div>
    </div>
    @break

{{-- ── Magazine: full-bleed cover with the title overlaid ── --}}
@case('heroFull')
    @if($hasHero)
        <div class="relative h-[52vh] min-h-[22rem] w-full overflow-hidden">
            <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 text-white">
                <div class="mx-auto {{ $maxW }} px-4 pb-10 sm:px-6">
                    {!! $eyebrow(true) !!}
                    <h1 class="mt-2 {{ $titleClass }} tracking-tight text-balance {{ $fontClass }}">{{ $post->title }}</h1>
                    <div class="mt-3">@include('blog.partials.meta', ['light' => true])</div>
                </div>
            </div>
        </div>
    @else
        <div class="grad-brand text-white"><div class="mx-auto {{ $maxW }} px-4 py-16 sm:px-6">
            {!! $eyebrow(true) !!}
            <h1 class="mt-2 {{ $titleClass }} {{ $fontClass }}">{{ $post->title }}</h1>
            <div class="mt-3">@include('blog.partials.meta', ['light' => true])</div>
        </div></div>
    @endif
    <article class="mx-auto {{ $maxW }} px-4 py-10 sm:px-6">
        <x-breadcrumbs :crumbs="$seo->breadcrumbs" />
        @if($tocInline)@include('blog.partials.toc')@endif
        @include('blog.partials.body')
        @include('blog.partials.endmatter')
    </article>
    @break

{{-- ── Bold: brand gradient banner header ── --}}
@case('heroBand')
    <div class="grad-brand text-white">
        <div class="mx-auto {{ $maxW }} px-4 py-16 sm:px-6">
            {!! $eyebrow(true) !!}
            <h1 class="mt-2 {{ $titleClass }} tracking-tight text-balance {{ $fontClass }}">{{ $post->title }}</h1>
            <div class="mt-4">@include('blog.partials.meta', ['light' => true])</div>
        </div>
    </div>
    <article class="mx-auto {{ $maxW }} px-4 py-10 sm:px-6">
        <x-breadcrumbs :crumbs="$seo->breadcrumbs" />
        @if($hasHero)
            <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" class="-mt-16 w-full rounded-2xl object-cover shadow-xl ring-1 ring-black/5">
        @endif
        @if($tocInline)@include('blog.partials.toc')@endif
        @include('blog.partials.body')
        @include('blog.partials.endmatter')
    </article>
    @break

{{-- ── Split hero: title beside the cover on a brand panel ── --}}
@case('split')
    <div class="grad-brand text-white">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <div class="grid items-center gap-8 md:grid-cols-2">
                <div>
                    {!! $eyebrow(true) !!}
                    <h1 class="mt-2 {{ $titleClass }} tracking-tight text-balance {{ $fontClass }}">{{ $post->title }}</h1>
                    <div class="mt-4">@include('blog.partials.meta', ['light' => true])</div>
                </div>
                @if($hasHero)
                    <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" class="aspect-[4/3] w-full rounded-2xl object-cover shadow-xl">
                @endif
            </div>
        </div>
    </div>
    <article class="mx-auto {{ $maxW }} px-4 py-10 sm:px-6">
        <x-breadcrumbs :crumbs="$seo->breadcrumbs" />
        @if($tocInline)@include('blog.partials.toc')@endif
        @include('blog.partials.body')
        @include('blog.partials.endmatter')
    </article>
    @break

{{-- ── Centered (Classic / Minimal / Feature / Card / Newspaper) ── --}}
@default
    @php($card = ($t['frame'] ?? 'none') === 'card')
    <div class="{{ $card ? 'bg-gray-50 py-10' : '' }}">
        <article class="mx-auto {{ $maxW }} px-4 sm:px-6 {{ $card ? 'rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-10' : 'py-10' }}">
            <x-breadcrumbs :crumbs="$seo->breadcrumbs" />
            <header class="mt-6">
                {!! $eyebrow() !!}
                <h1 class="mt-2 {{ $titleClass }} tracking-tight text-balance text-gray-900 {{ $fontClass }}">{{ $post->title }}</h1>
                <div class="mt-3">@include('blog.partials.meta')</div>
                @if($t['rules'] ?? false)<hr class="mt-5 border-gray-900/10">@endif
            </header>
            @if($hasHero)
                <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="768" height="432" class="mt-6 w-full rounded-2xl object-cover">
            @endif
            @if($tocInline)@include('blog.partials.toc')@endif
            @include('blog.partials.body')
            @include('blog.partials.endmatter')
        </article>
    </div>
@endswitch

@include('partials.custom-code', ['model' => $post])
@endsection
