@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">{{ $heading }}</h1>

    @if($categories->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('blog.index') }}" class="rounded-full border px-4 py-1.5 text-sm {{ request()->routeIs('blog.index') ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300' }}">All</a>
            @foreach($categories as $category)
                <a href="{{ route('blog.category', $category->slug) }}" class="rounded-full border px-4 py-1.5 text-sm {{ request()->route('postCategory')?->id === $category->id ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300' }}">{{ $category->name }}</a>
            @endforeach
        </div>
    @endif

    @if($posts->isEmpty())
        <p class="mt-12 text-gray-500">No posts yet — check back soon.</p>
    @else
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <article class="group overflow-hidden rounded-lg border border-gray-200 transition hover:shadow-md">
                    <a href="{{ $post->url() }}">
                        <div class="aspect-[16/9] bg-gray-100">
                            @if($post->featuredImageUrl())
                                <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" width="400" height="225" class="h-full w-full object-cover transition group-hover:scale-105">
                            @endif
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-gray-500">
                                {{ $post->published_at->format('M j, Y') }} · {{ $post->reading_time }} min read
                                @if($post->category) · {{ $post->category->name }} @endif
                            </p>
                            <h2 class="mt-2 font-semibold group-hover:text-indigo-600">{{ $post->title }}</h2>
                            <p class="mt-2 text-sm text-gray-600">{{ str($post->excerpt)->limit(120) }}</p>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
        <div class="mt-8">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
