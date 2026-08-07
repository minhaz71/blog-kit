@php
    $limit = (int) $section->setting('limit', 8);
    $categories = \App\Models\PostCategory::query()
        ->withCount(['posts' => fn ($q) => $q->published()])
        ->having('posts_count', '>', 0)
        ->orderByDesc('posts_count')
        ->take(max(1, $limit))
        ->get();
@endphp
@if($categories->isNotEmpty())
    <section class="mt-12" aria-label="{{ $section->title ?? 'Browse by topic' }}">
        <div class="flex items-end justify-between gap-3">
            <div>
                <span class="text-xs font-semibold uppercase tracking-widest text-brand">Topics</span>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                    {{ $section->title ?? 'Browse by topic' }}
                </h2>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($categories as $category)
                <a href="{{ route('blog.category', $category->slug) }}"
                   class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg">
                    {{-- Soft brand wash that fills in on hover. --}}
                    <div class="bg-brand-tint absolute inset-0 opacity-60 transition group-hover:opacity-100"></div>
                    <div class="relative">
                        <span class="bg-brand text-brand-fg flex h-10 w-10 items-center justify-center rounded-xl text-base font-bold shadow-sm">
                            {{ strtoupper(mb_substr($category->name, 0, 1)) }}
                        </span>
                        <h3 class="mt-3 text-base font-bold text-gray-900 transition group-hover:text-brand">{{ $category->name }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">{{ $category->posts_count }} {{ \Illuminate\Support\Str::plural('article', $category->posts_count) }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
