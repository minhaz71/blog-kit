{{--
    Horizontal list row: side thumbnail (left ~1/3) + text on the right.
    Shared by the list layout.
    Params: $post
--}}
<a href="{{ $post->url() }}" class="group flex flex-col gap-5 overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg sm:flex-row">
    <div class="relative shrink-0 overflow-hidden rounded-xl sm:w-1/3">
        <div class="aspect-[16/9] sm:aspect-[4/3]">
            @if($post->featuredImageUrl())
                <img width="1536" height="864" src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            @else
                <div class="grad-brand flex h-full w-full items-center justify-center p-5">
                    <span class="text-center font-bold leading-snug text-white/95 line-clamp-3">{{ $post->title }}</span>
                </div>
            @endif
        </div>
    </div>
    <div class="flex flex-1 flex-col justify-center">
        @if($post->category)
            <span class="text-xs font-semibold uppercase tracking-wide text-brand">{{ $post->category->name }}</span>
        @endif
        <h2 class="mt-1 text-lg font-bold leading-snug text-gray-900 transition group-hover:text-brand sm:text-xl">{{ $post->title }}</h2>
        <p class="mt-2 text-sm leading-relaxed text-gray-600 line-clamp-2">{{ $post->excerpt }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
            <span class="font-medium text-gray-700">{{ optional($post->author)->publicName() }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ $post->reading_time }} min read</span>
        </div>
    </div>
</a>
