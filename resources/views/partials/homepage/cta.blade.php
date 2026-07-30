<section class="mt-4 overflow-hidden rounded-lg bg-gray-900 shadow-sm">
    <div class="flex flex-col items-start gap-4 px-5 py-8 text-white sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div class="min-w-0">
            @if($section->title)<h2 class="text-xl font-extrabold text-balance sm:text-2xl">{{ $section->title }}</h2>@endif
            @if($section->setting('description'))<p class="mt-2 max-w-2xl text-sm text-gray-300">{{ $section->setting('description') }}</p>@endif
        </div>
        @if($section->setting('button_text') && $section->setting('button_url'))
            <a href="{{ $section->setting('button_url') }}"
               class="shrink-0 rounded-md bg-teal-500 px-6 py-3 text-sm font-bold text-white hover:bg-teal-400">
                {{ $section->setting('button_text') }} →
            </a>
        @endif
    </div>
</section>
