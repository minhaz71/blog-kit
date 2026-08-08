@extends('layouts.app')

@php($t = \App\Support\BlogIndexStyle::tokens())
@php($layout = $t['layout'])
@php($wide = in_array($layout, ['grid', 'cards', 'masonry', 'magazine', 'overlay'], true))
@php($isBlogIndex = request()->routeIs('blog.index'))
@php($eyebrow = $isBlogIndex ? 'The blog' : 'Category')
@php($gridCols = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-2 lg:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'])

@section('content')
<div class="mx-auto {{ $wide ? 'max-w-7xl' : 'max-w-6xl' }} px-4 py-10 sm:px-6">

    {{-- Page header --}}
    <header class="max-w-2xl">
        <span class="text-xs font-semibold uppercase tracking-widest text-brand">{{ $eyebrow }}</span>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ $heading }}</h1>
    </header>

    {{-- Category filter --}}
    @if($t['filter'] !== 'none' && $categories->isNotEmpty())
        @if($t['filter'] === 'bar')
            <nav class="mt-6 -mb-px flex gap-6 overflow-x-auto border-b border-gray-200" aria-label="Categories">
                <a href="{{ route('blog.index') }}" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium {{ $isBlogIndex ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-brand' }}">All</a>
                @foreach($categories as $category)
                    @php($active = request()->route('postCategory')?->id === $category->id)
                    <a href="{{ route('blog.category', $category->slug) }}" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium {{ $active ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-brand' }}">{{ $category->name }}</a>
                @endforeach
            </nav>
        @else
            <div class="mt-6 flex flex-wrap gap-2">
                <a href="{{ route('blog.index') }}" class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $isBlogIndex ? 'bg-brand text-brand-fg' : 'border border-gray-300 text-gray-700 hover:border-brand hover:text-brand' }}">All</a>
                @foreach($categories as $category)
                    @php($active = request()->route('postCategory')?->id === $category->id)
                    <a href="{{ route('blog.category', $category->slug) }}" class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $active ? 'bg-brand text-brand-fg' : 'border border-gray-300 text-gray-700 hover:border-brand hover:text-brand' }}">{{ $category->name }}</a>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Sub-category chips (shown on a category archive that has sub-categories) --}}
    @if(!empty($subcategories) && $subcategories->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach($subcategories as $sub)
                @php($subActive = ($activeCategory ?? null)?->id === $sub->id)
                <a href="{{ route('blog.category', $sub->slug) }}" class="rounded-full px-3 py-1 text-xs font-medium transition {{ $subActive ? 'bg-brand text-brand-fg' : 'border border-gray-200 text-gray-600 hover:border-brand hover:text-brand' }}">{{ $sub->name }}</a>
            @endforeach
        </div>
    @endif

    {{-- Empty state --}}
    @if($posts->isEmpty())
        <div class="py-24 text-center">
            <p class="text-lg font-semibold text-gray-700">No posts yet</p>
            <p class="mt-1 text-sm text-gray-500">Check back soon — new stories are on the way.</p>
        </div>
    @else
        <div class="mt-10">
            @switch($layout)

                {{-- 1. GRID — even card grid, cover on top --}}
                @case('grid')
                    <div class="grid gap-6 {{ $gridCols[(string) $t['columns']] ?? $gridCols['3'] }}">
                        @foreach($posts as $post)
                            @include('blog.index-partials.card', ['post' => $post])
                        @endforeach
                    </div>
                    @break

                {{-- 2. FEATURED — first post as a 2-col spotlight, rest in a grid --}}
                @case('featured')
                    @php($feature = $posts->first())
                    @php($rest = $posts->slice(1))
                    <a href="{{ $feature->url() }}" class="group relative mb-6 flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg lg:flex-row">
                        <div class="relative aspect-[16/9] overflow-hidden lg:aspect-auto lg:w-3/5">
                            @if($feature->featuredImageUrl())
                                <img src="{{ $feature->featuredImageUrl() }}" alt="{{ $feature->featured_image_alt ?: $feature->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            @else
                                <div class="grad-brand flex h-full w-full items-center justify-center p-6">
                                    <span class="text-center text-lg font-bold leading-snug text-white/95 line-clamp-4">{{ $feature->title }}</span>
                                </div>
                            @endif
                            <span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-brand shadow-sm backdrop-blur">Featured</span>
                        </div>
                        <div class="flex flex-1 flex-col justify-center p-6 sm:p-8">
                            @if($feature->category)
                                <span class="text-xs font-semibold uppercase tracking-wide text-brand">{{ $feature->category->name }}</span>
                            @endif
                            <h2 class="mt-2 text-2xl font-bold leading-snug text-gray-900 transition group-hover:text-brand sm:text-3xl">{{ $feature->title }}</h2>
                            <p class="mt-3 text-base leading-relaxed text-gray-600 line-clamp-3">{{ $feature->excerpt }}</p>
                            <div class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500">
                                <span class="font-medium text-gray-700">{{ optional($feature->author)->publicName() }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ optional($feature->published_at)->format('M j, Y') }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $feature->reading_time }} min read</span>
                            </div>
                        </div>
                    </a>
                    @if($rest->isNotEmpty())
                        <div class="grid gap-6 {{ $gridCols[(string) $t['columns']] ?? $gridCols['3'] }}">
                            @foreach($rest as $post)
                                @include('blog.index-partials.card', ['post' => $post])
                            @endforeach
                        </div>
                    @endif
                    @break

                {{-- 3. LIST — single-column rows with a side thumbnail --}}
                @case('list')
                    <div class="space-y-5">
                        @foreach($posts as $post)
                            @include('blog.index-partials.list-row', ['post' => $post])
                        @endforeach
                    </div>
                    @break

                {{-- 4. MAGAZINE — asymmetric editorial grid --}}
                @case('magazine')
                    @php($feature = $posts->first())
                    @php($rest = $posts->slice(1))
                    <div class="grid gap-6 lg:grid-cols-3">
                        {{-- Big lead spanning two columns --}}
                        <a href="{{ $feature->url() }}" class="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg lg:col-span-2 lg:row-span-2">
                            <div class="relative aspect-[16/10] overflow-hidden lg:flex-1">
                                @if($feature->featuredImageUrl())
                                    <img src="{{ $feature->featuredImageUrl() }}" alt="{{ $feature->featured_image_alt ?: $feature->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                @else
                                    <div class="grad-brand flex h-full min-h-64 w-full items-center justify-center p-6">
                                        <span class="text-center text-lg font-bold leading-snug text-white/95 line-clamp-4">{{ $feature->title }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="p-6 sm:p-8">
                                @if($feature->category)
                                    <span class="text-xs font-semibold uppercase tracking-wide text-brand">{{ $feature->category->name }}</span>
                                @endif
                                <h2 class="mt-2 text-2xl font-bold leading-snug text-gray-900 transition group-hover:text-brand sm:text-3xl">{{ $feature->title }}</h2>
                                <p class="mt-3 text-base leading-relaxed text-gray-600 line-clamp-2">{{ $feature->excerpt }}</p>
                                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500">
                                    <span>{{ optional($feature->published_at)->format('M j, Y') }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $feature->reading_time }} min read</span>
                                </div>
                            </div>
                        </a>
                        {{-- Side stack: compact horizontal rows --}}
                        @foreach($rest->take(2) as $post)
                            <a href="{{ $post->url() }}" class="group flex gap-4 overflow-hidden rounded-2xl border border-gray-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg">
                                <div class="relative aspect-square w-24 shrink-0 overflow-hidden rounded-xl sm:w-28">
                                    @if($post->featuredImageUrl())
                                        <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                    @else
                                        <div class="grad-brand flex h-full w-full items-center justify-center p-2">
                                            <span class="text-center text-xs font-bold leading-tight text-white/95 line-clamp-3">{{ $post->title }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-1 flex-col justify-center">
                                    @if($post->category)
                                        <span class="text-[11px] font-semibold uppercase tracking-wide text-brand">{{ $post->category->name }}</span>
                                    @endif
                                    <h3 class="mt-0.5 text-sm font-bold leading-snug text-gray-900 transition group-hover:text-brand line-clamp-3">{{ $post->title }}</h3>
                                    <span class="mt-1 text-xs text-gray-500">{{ $post->reading_time }} min read</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    {{-- Remaining posts in a 3-col grid --}}
                    @if($rest->count() > 2)
                        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($rest->slice(2) as $post)
                                @include('blog.index-partials.card', ['post' => $post])
                            @endforeach
                        </div>
                    @endif
                    @break

                {{-- 5. CARDS — dense 4-col compact cards --}}
                @case('cards')
                    <div class="grid gap-5 {{ $gridCols[(string) $t['columns']] ?? $gridCols['4'] }}">
                        @foreach($posts as $post)
                            @include('blog.index-partials.card', ['post' => $post, 'size' => 'compact'])
                        @endforeach
                    </div>
                    @break

                {{-- 6. MINIMAL — text-only single column, no images --}}
                @case('minimal')
                    <div class="mx-auto max-w-2xl divide-y divide-gray-200">
                        @foreach($posts as $post)
                            <a href="{{ $post->url() }}" class="group block py-8">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
                                    @if($post->category)
                                        <span aria-hidden="true">·</span>
                                        <span class="font-semibold text-brand">{{ $post->category->name }}</span>
                                    @endif
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $post->reading_time }} min read</span>
                                </div>
                                <h2 class="mt-2 text-2xl font-bold leading-snug text-gray-900 transition group-hover:text-brand">{{ $post->title }}</h2>
                                <p class="mt-2 text-base leading-relaxed text-gray-600 line-clamp-1">{{ $post->excerpt }}</p>
                            </a>
                        @endforeach
                    </div>
                    @break

                {{-- 7. COMPACT — dense 2-col rows, no images, small type --}}
                @case('compact')
                    <div class="grid gap-x-8 gap-y-0 sm:grid-cols-2">
                        @foreach($posts as $post)
                            <a href="{{ $post->url() }}" class="group flex items-baseline gap-3 border-b border-gray-200 py-3">
                                <span class="w-20 shrink-0 text-xs text-gray-400">{{ optional($post->published_at)->format('M j, Y') }}</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block truncate text-sm font-semibold text-gray-900 transition group-hover:text-brand">{{ $post->title }}</span>
                                </span>
                                @if($post->category)
                                    <span class="hidden shrink-0 text-[11px] font-semibold uppercase tracking-wide text-brand sm:inline">{{ $post->category->name }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    @break

                {{-- 8. OVERLAY — title overlaid on the cover image --}}
                @case('overlay')
                    <div class="grid gap-6 {{ $gridCols[(string) $t['columns']] ?? $gridCols['3'] }}">
                        @foreach($posts as $post)
                            @include('blog.index-partials.overlay-card', ['post' => $post])
                        @endforeach
                    </div>
                    @break

                {{-- 9. TIMELINE — single column with a vertical brand rail --}}
                @case('timeline')
                    <div class="relative mx-auto max-w-3xl">
                        <span class="absolute left-2 top-2 bottom-2 w-px bg-brand/40 sm:left-3" aria-hidden="true"></span>
                        <div class="space-y-8">
                            @foreach($posts as $post)
                                <div class="relative pl-10 sm:pl-14">
                                    <span class="absolute left-0 top-1.5 h-4 w-4 rounded-full bg-brand ring-4 ring-white sm:left-1" aria-hidden="true"></span>
                                    <a href="{{ $post->url() }}" class="group block">
                                        <time class="text-xs font-semibold uppercase tracking-wide text-brand">{{ optional($post->published_at)->format('F j, Y') }}</time>
                                        <div class="mt-2 flex flex-col gap-4 sm:flex-row">
                                            <div class="relative aspect-[16/9] shrink-0 overflow-hidden rounded-xl sm:aspect-[4/3] sm:w-40">
                                                @if($post->featuredImageUrl())
                                                    <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                                @else
                                                    <div class="grad-brand flex h-full w-full items-center justify-center p-3">
                                                        <span class="text-center text-xs font-bold leading-tight text-white/95 line-clamp-3">{{ $post->title }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex-1">
                                                <h2 class="text-lg font-bold leading-snug text-gray-900 transition group-hover:text-brand sm:text-xl">{{ $post->title }}</h2>
                                                <p class="mt-1 text-sm leading-relaxed text-gray-600 line-clamp-2">{{ $post->excerpt }}</p>
                                                <span class="mt-2 inline-block text-xs text-gray-500">{{ $post->reading_time }} min read</span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @break

                {{-- 10. MASONRY — staggered CSS columns, natural-height cards --}}
                @case('masonry')
                    <div class="columns-1 gap-6 sm:columns-2 lg:columns-3 [&>*]:mb-6 [&>*]:break-inside-avoid">
                        @foreach($posts as $post)
                            @include('blog.index-partials.card', ['post' => $post, 'natural' => true])
                        @endforeach
                    </div>
                    @break

                {{-- Fallback → grid --}}
                @default
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($posts as $post)
                            @include('blog.index-partials.card', ['post' => $post])
                        @endforeach
                    </div>
            @endswitch
        </div>

        <div class="mt-10">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
