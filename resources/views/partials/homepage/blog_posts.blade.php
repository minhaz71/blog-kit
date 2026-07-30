@php
    $limit = (int) $section->setting('limit', 3);
    $posts = \App\Models\Post::published()->with(['author', 'category'])->latest('published_at')->take($limit)->get();
@endphp
@if($posts->isNotEmpty())
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="{{ $section->title ?? 'From the blog' }}">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <h2 class="flex items-center gap-2 truncate text-base font-bold text-gray-900 sm:text-lg">
                <span class="h-5 w-1.5 shrink-0 rounded-full bg-teal-600"></span>{{ $section->title ?? 'From the blog' }}
            </h2>
            <a href="{{ route('blog.index') }}" class="shrink-0 text-sm font-semibold text-teal-700 hover:underline">All posts →</a>
        </div>
        <div class="grid gap-3 p-4 sm:grid-cols-{{ min($limit, 3) }}">
            @foreach($posts as $post)
                <a href="{{ $post->url() }}" class="group rounded-lg border border-gray-100 bg-gray-50/60 p-4 transition hover:border-teal-200 hover:bg-white hover:shadow-sm">
                    <p class="text-xs text-gray-500">{{ optional($post->published_at)->format('M j, Y') }} · {{ $post->reading_time }} min read</p>
                    <h3 class="mt-1.5 text-sm font-bold text-gray-900 group-hover:text-teal-700">{{ $post->title }}</h3>
                    <p class="mt-1.5 text-xs text-gray-600">{{ str($post->excerpt)->limit(120) }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endif
