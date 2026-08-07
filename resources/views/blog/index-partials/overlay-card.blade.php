{{--
    Overlay card: title sits over the cover image with a dark scrim.
    Params: $post, $tall (bool) — taller aspect for hero-ish cards.
--}}
@php($tall ??= false)
<a href="{{ $post->url() }}" class="group relative flex overflow-hidden rounded-2xl border border-gray-200 shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg {{ $tall ? 'aspect-[4/5] sm:aspect-[3/4]' : 'aspect-[4/3]' }}">
    @if($post->featuredImageUrl())
        <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
    @else
        <div class="grad-brand absolute inset-0 flex items-center justify-center p-5">
            <span class="text-center font-bold leading-snug text-white/95 line-clamp-3">{{ $post->title }}</span>
        </div>
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>
    <div class="relative mt-auto w-full p-5">
        @if($post->category)
            <span class="inline-block rounded-full bg-white/90 px-2.5 py-0.5 text-[11px] font-semibold text-brand shadow-sm backdrop-blur">{{ $post->category->name }}</span>
        @endif
        <h2 class="mt-2 text-lg font-bold leading-snug text-white">{{ $post->title }}</h2>
        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-white/80">
            <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ $post->reading_time }} min read</span>
        </div>
    </div>
</a>
