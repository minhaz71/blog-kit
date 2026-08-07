@php($light = $light ?? false)
<div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm {{ $light ? 'text-white/80' : 'text-gray-500' }}">
    <a href="{{ $post->author->authorUrl() }}" class="font-medium {{ $light ? 'text-white hover:underline' : 'text-gray-700 hover:text-brand' }}">{{ $post->author->publicName() }}</a>
    <span aria-hidden="true">·</span>
    @if($post->published_at)
        <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('M j, Y') }}</time>
        @if($post->updated_at->gt($post->published_at->addDay()))
            <span aria-hidden="true">·</span>
            <span>Updated {{ $post->updated_at->format('M j, Y') }}</span>
        @endif
    @else
        <span>Not published yet</span>
    @endif
    <span aria-hidden="true">·</span>
    <span>{{ $post->reading_time }} min read</span>
    @if($post->category)
        <span aria-hidden="true">·</span>
        <a href="{{ route('blog.category', $post->category->slug) }}" class="font-medium {{ $light ? 'text-white hover:underline' : 'text-brand' }}">{{ $post->category->name }}</a>
    @endif
</div>
