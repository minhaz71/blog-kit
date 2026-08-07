@php
    $limit = (int) $section->setting('limit', 6);
    $posts = \App\Models\Post::published()->with(['author', 'category'])->latest('published_at')->take(max(1, $limit))->get();
    $featured = $posts->first();
    $rest = $posts->slice(1);
@endphp
@if($posts->isNotEmpty())
    <section class="mt-12" aria-label="{{ $section->title ?? 'From the blog' }}">
        <div class="flex items-end justify-between gap-3">
            <div>
                <span class="text-xs font-semibold uppercase tracking-widest text-brand">The blog</span>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                    {{ $section->title ?? 'Latest from the blog' }}
                </h2>
            </div>
            <a href="{{ route('blog.index') }}" class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-brand hover:text-brand sm:inline-flex">
                All posts
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            {{-- Featured post — spans two columns on large screens. --}}
            <a href="{{ $featured->url() }}" class="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg lg:col-span-2 lg:flex-row">
                <div class="relative aspect-[16/9] overflow-hidden lg:aspect-auto lg:w-1/2">
                    @if($featured->featuredImageUrl())
                        <img src="{{ $featured->featuredImageUrl() }}" alt="{{ $featured->featured_image_alt ?: $featured->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @else
                        <div class="flex h-full w-full items-center justify-center grad-brand p-6">
                            <span class="text-center text-lg font-bold leading-snug text-white/95 line-clamp-4">{{ $featured->title }}</span>
                        </div>
                    @endif
                    <span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-brand shadow-sm backdrop-blur">Featured</span>
                </div>
                <div class="flex flex-1 flex-col justify-center p-6 sm:p-8">
                    @if($featured->category)
                        <span class="text-xs font-semibold uppercase tracking-wide text-brand">{{ $featured->category->name }}</span>
                    @endif
                    <h3 class="mt-2 text-xl font-bold leading-snug text-gray-900 transition group-hover:text-brand sm:text-2xl">{{ $featured->title }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-gray-600 line-clamp-3">{{ $featured->excerpt }}</p>
                    <div class="mt-5 flex items-center gap-2 text-xs text-gray-500">
                        <span class="font-medium text-gray-700">{{ optional($featured->author)->publicName() }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ optional($featured->published_at)->format('M j, Y') }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $featured->reading_time }} min read</span>
                    </div>
                </div>
            </a>

            {{-- Secondary posts. --}}
            @foreach($rest as $post)
                <a href="{{ $post->url() }}" class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="relative aspect-[16/9] overflow-hidden">
                        @if($post->featuredImageUrl())
                            <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?: $post->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        @else
                            <div class="flex h-full w-full items-center justify-center grad-brand p-5">
                                <span class="text-center text-base font-bold leading-snug text-white/95 line-clamp-3">{{ $post->title }}</span>
                            </div>
                        @endif
                        @if($post->category)
                            <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-0.5 text-[11px] font-semibold text-brand shadow-sm backdrop-blur">{{ $post->category->name }}</span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <h3 class="text-base font-bold leading-snug text-gray-900 transition group-hover:text-brand">{{ $post->title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 line-clamp-2">{{ $post->excerpt }}</p>
                        <div class="mt-4 flex items-center gap-2 text-xs text-gray-500">
                            <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ $post->reading_time }} min read</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6 text-center sm:hidden">
            <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand">
                All posts
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    </section>
@endif
