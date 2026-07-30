{{-- SEO text: marketplace-style collapsed read-more box (no JS needed). --}}
<section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
    @if($section->title)
        <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="flex items-center gap-2 text-base font-bold text-gray-900 sm:text-lg">
                <span class="h-5 w-1.5 shrink-0 rounded-full bg-teal-600"></span>{{ $section->title }}
            </h2>
        </div>
    @endif
    <details class="group p-4">
        <summary class="block cursor-pointer list-none">
            <div class="prose prose-sm prose-neutral max-w-none max-h-40 overflow-hidden group-open:max-h-none [&_a]:text-teal-700">
                {!! $section->setting('body') !!}
            </div>
            <span class="mt-2 inline-block text-sm font-semibold text-teal-700 group-open:hidden">Read more ↓</span>
            <span class="mt-2 hidden text-sm font-semibold text-teal-700 group-open:inline-block">Show less ↑</span>
        </summary>
    </details>
</section>
