@if($post->tags->isNotEmpty())
    <div class="mt-8 flex flex-wrap gap-2">
        @foreach($post->tags as $tag)
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-600">#{{ $tag->name }}</span>
        @endforeach
    </div>
@endif

{{-- E-E-A-T author box: who wrote this and why they're credible. --}}
@if($post->author && ($post->author->bio || $post->author->job_title))
    <aside class="mt-10 flex gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5" aria-label="About the author">
        @if($post->author->avatarUrl())
            <img src="{{ $post->author->avatarUrl() }}" alt="{{ $post->author->publicName() }}" width="64" height="64"
                 class="h-16 w-16 shrink-0 rounded-full object-cover">
        @else
            <div class="bg-brand text-brand-fg flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-xl font-bold">
                {{ mb_substr($post->author->publicName(), 0, 1) }}
            </div>
        @endif
        <div>
            <p class="text-sm font-semibold">
                <a href="{{ $post->author->authorUrl() }}" class="hover:text-brand">{{ $post->author->publicName() }}</a>
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
                        <a href="{{ $link }}" rel="me noopener" target="_blank" class="text-brand hover:underline">{{ parse_url($link, PHP_URL_HOST) }}</a>
                    @endforeach
                </p>
            @endif
        </div>
    </aside>
@endif

<x-faq-section :faqs="$post->faqs" />

@if($related->isNotEmpty())
    <section class="mt-12" aria-labelledby="related-posts-heading">
        <h2 id="related-posts-heading" class="text-xl font-bold text-gray-900">Keep reading</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach($related as $relatedPost)
                <a href="{{ $relatedPost->url() }}" class="group rounded-2xl border border-gray-200 bg-white p-4 text-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-md">
                    <p class="text-xs text-gray-500">{{ optional($relatedPost->published_at)->format('M j, Y') }}</p>
                    <p class="mt-1 font-semibold text-gray-900 transition group-hover:text-brand">{{ $relatedPost->title }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endif
