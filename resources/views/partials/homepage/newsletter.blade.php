<section id="newsletter" class="relative mt-12 overflow-hidden rounded-3xl grad-brand scroll-mt-24 shadow-sm">
    <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
    <div class="relative flex flex-col items-start gap-6 px-6 py-10 sm:px-10 lg:flex-row lg:items-center lg:justify-between lg:py-12">
        <div class="max-w-xl text-white">
            <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $section->title ?? 'Get new posts by email' }}</h2>
            @if($section->subtitle || $section->setting('description'))
                <p class="mt-2 text-base text-white/85">{{ $section->subtitle ?? $section->setting('description') }}</p>
            @endif
        </div>
        <form action="{{ route('newsletter.subscribe') }}" method="POST" class="w-full max-w-md">
            @csrf
            <div class="flex gap-2">
                <input type="email" name="email" required placeholder="you@example.com"
                       class="min-w-0 flex-1 rounded-full border-0 px-5 py-3 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:ring-2 focus:ring-white">
                <button class="shrink-0 rounded-full bg-gray-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-gray-800">
                    {{ $section->setting('button_text') ?? 'Subscribe' }}
                </button>
            </div>
            <p class="mt-2 pl-1 text-xs text-white/70">No spam. Unsubscribe anytime.</p>
        </form>
    </div>
</section>
