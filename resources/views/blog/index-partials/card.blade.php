{{--
    Vertical cover card (cover image on top). Shared by grid / cards / masonry /
    featured-rest / magazine layouts.
    Params:
      $post  — App\Models\Post
      $size  — 'compact' | 'default' | 'large'  (controls type + padding)
      $natural — bool: when true the image keeps its natural height (masonry)
--}}
@php
    $size ??= 'default';
    $natural ??= false;
    $titleClass = match ($size) {
        'large' => 'text-xl font-bold leading-snug sm:text-2xl',
        'compact' => 'text-sm font-bold leading-snug',
        default => 'text-base font-bold leading-snug',
    };
    $pad = $size === 'compact' ? 'p-4' : 'p-5';
@endphp
<a href="{{ $post->url() }}" class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg">
    <div class="relative overflow-hidden {{ $natural ? '' : 'aspect-[16/9]' }}">
        @if($post->featuredImageUrl())
            <img width="1536" height="864" src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" class="w-full object-cover transition duration-500 group-hover:scale-105 {{ $natural ? 'h-auto' : 'h-full' }}">
        @else
            <div class="grad-brand flex {{ $natural ? 'aspect-[4/3]' : 'h-full' }} w-full items-center justify-center p-5">
                <span class="text-center font-bold leading-snug text-white/95 line-clamp-3">{{ $post->title }}</span>
            </div>
        @endif
        @if($post->category)
            <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-0.5 text-[11px] font-semibold text-brand shadow-sm backdrop-blur">{{ $post->category->name }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col {{ $pad }}">
        <h2 class="{{ $titleClass }} text-gray-900 transition group-hover:text-brand">{{ $post->title }}</h2>
        @if($size !== 'compact')
            <p class="mt-2 text-sm leading-relaxed text-gray-600 line-clamp-2">{{ $post->excerpt }}</p>
        @endif
        <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
            <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ $post->reading_time }} min read</span>
        </div>
    </div>
</a>
