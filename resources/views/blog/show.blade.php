@extends('layouts.app')

@section('content')
@if (!empty($isPreview))
    <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
        Draft preview — not visible to visitors.
    </div>
@endif
<article class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <x-breadcrumbs :crumbs="$seo->breadcrumbs" />

    <header class="mt-6">
        <h1 class="text-3xl font-bold sm:text-4xl">{{ $post->title }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <a href="{{ $post->author->authorUrl() }}" class="font-medium text-gray-700 hover:text-indigo-600">{{ $post->author->publicName() }}</a>
            <span aria-hidden="true">·</span>
            @if($post->published_at)
                <time datetime="{{ $post->published_at->toDateString() }}">Published {{ $post->published_at->format('M j, Y') }}</time>
                @if($post->updated_at->gt($post->published_at->addDay()))
                    <span aria-hidden="true">·</span>
                    <time datetime="{{ $post->updated_at->toDateString() }}">Updated {{ $post->updated_at->format('M j, Y') }}</time>
                @endif
            @else
                <span>Not published yet</span>
            @endif
            <span aria-hidden="true">·</span>
            <span>{{ $post->reading_time }} min read</span>
        </div>
    </header>

    @if($post->featuredImageUrl())
        <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="768" height="432" class="mt-6 w-full rounded-xl object-cover">
    @endif

    @if(count($toc) > 1)
        {{-- .bd-toc styled in blog.css (cached bundle), no inline CSS. --}}
        <nav class="bd-toc" aria-label="Table of contents">
            <p class="bd-toc__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5M3.75 12h16.5m-16.5 6.75h16.5"/>
                </svg>
                In this article
            </p>
            <ol class="bd-toc__list">
                @foreach($toc as $item)
                    <li class="bd-toc__item--{{ $item['level'] }}">
                        <a href="#{{ $item['anchor'] }}">{{ $item['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    {{-- Comparison articles: the two products under review, visible up
         front (backs the ItemList schema with real on-page content and
         gives buyers a direct path to both product pages). --}}
    @if(($compared = $post->comparedProducts())->isNotEmpty())
        <section class="mt-8" aria-labelledby="compared-products-heading">
            <h2 id="compared-products-heading" class="text-lg font-bold">Products compared in this article</h2>
            <div class="mt-3 grid grid-cols-2 gap-4">
                @foreach($compared as $comparedProduct)
                    <x-product-card :product="$comparedProduct" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- bd-article: the tag-based blog design layer (blog.css) — every
         semantic tag is styled, no classes needed in the content itself. --}}
    <div class="prose bd-article mt-8 max-w-none">
        {!! preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/i', fn ($m) => sprintf('<h%s%s id="%s">%s</h%s>', $m[1], $m[2], str(strip_tags($m[3]))->slug(), $m[3], $m[1]), $post->content ?? '') !!}
    </div>

    @if($post->tags->isNotEmpty())
        <div class="mt-8 flex flex-wrap gap-2">
            @foreach($post->tags as $tag)
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-600">#{{ $tag->name }}</span>
            @endforeach
        </div>
    @endif

    {{-- E-E-A-T author box: who wrote this and why they're credible. --}}
    @if($post->author && ($post->author->bio || $post->author->job_title))
        <aside class="mt-10 flex gap-4 rounded-xl border border-gray-200 bg-gray-50 p-5" aria-label="About the author">
            @if($post->author->avatarUrl())
                <img src="{{ $post->author->avatarUrl() }}" alt="{{ $post->author->publicName() }}" width="64" height="64"
                     class="h-16 w-16 shrink-0 rounded-full object-cover">
            @else
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xl font-bold text-indigo-600">
                    {{ mb_substr($post->author->publicName(), 0, 1) }}
                </div>
            @endif
            <div>
                <p class="text-sm font-semibold">
                    <a href="{{ $post->author->authorUrl() }}" class="hover:text-indigo-600">{{ $post->author->publicName() }}</a>
                    @if($post->author->job_title)
                        <span class="ml-1 font-normal text-gray-500">· {{ $post->author->job_title }}</span>
                    @endif
                </p>
                @if($post->author->bio)
                    <p class="mt-1 text-sm text-gray-600">{{ $post->author->bio }}</p>
                @endif
                @if(array_filter((array) $post->author->social_links))
                    <p class="mt-1.5 flex gap-3 text-xs">
                        @foreach(array_filter((array) $post->author->social_links) as $link)
                            <a href="{{ $link }}" rel="me noopener" target="_blank" class="text-indigo-600 hover:underline">{{ parse_url($link, PHP_URL_HOST) }}</a>
                        @endforeach
                    </p>
                @endif
            </div>
        </aside>
    @endif

    <x-faq-section :faqs="$post->faqs" />

    @if($related->isNotEmpty())
        <section class="mt-12" aria-labelledby="related-posts-heading">
            <h2 id="related-posts-heading" class="text-xl font-bold">Related posts</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach($related as $relatedPost)
                    <a href="{{ $relatedPost->url() }}" class="rounded-lg border border-gray-200 p-4 text-sm hover:shadow-md">
                        <p class="text-xs text-gray-500">{{ $relatedPost->published_at->format('M j, Y') }}</p>
                        <p class="mt-1 font-semibold hover:text-indigo-600">{{ $relatedPost->title }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</article>
@include('partials.custom-code', ['model' => $post])
@endsection
