<section class="mt-4 overflow-hidden rounded-lg border border-teal-700/20 bg-teal-700 shadow-sm">
    <div class="flex flex-col items-start gap-4 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div class="min-w-0 text-white">
            <h2 class="text-lg font-bold sm:text-xl">{{ $section->title ?? 'Get updates' }}</h2>
            @if($section->setting('description'))
                <p class="mt-1 text-sm text-teal-100">{{ $section->setting('description') }}</p>
            @endif
        </div>
        <form action="{{ route('newsletter.subscribe') }}" method="POST" class="flex w-full max-w-md gap-2 sm:w-auto">
            @csrf
            <input type="email" name="email" required placeholder="Your email"
                   class="min-w-0 flex-1 rounded-md border-0 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-white">
            <button class="shrink-0 rounded-md bg-gray-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-gray-800">
                {{ $section->setting('button_text') ?? 'Subscribe' }}
            </button>
        </form>
    </div>
</section>
