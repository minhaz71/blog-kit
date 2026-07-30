@php
    $items = (array) $section->setting('items', []);
@endphp
@if(count($items) > 0)
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="{{ $section->title ?? 'Frequently asked questions' }}">
        <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="flex items-center gap-2 text-base font-bold text-gray-900 sm:text-lg">
                <span class="h-5 w-1.5 shrink-0 rounded-full bg-teal-600"></span>{{ $section->title ?? 'Frequently asked questions' }}
            </h2>
        </div>
        <dl class="grid gap-x-6 p-2 sm:grid-cols-2 sm:p-4">
            @foreach($items as $item)
                <details class="group rounded-lg px-3 py-2.5 open:bg-gray-50">
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4 text-sm font-semibold text-gray-900">
                        <span>{{ $item['question'] ?? '' }}</span>
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <dd class="mt-2 text-sm text-gray-600">{{ $item['answer'] ?? '' }}</dd>
                </details>
            @endforeach
        </dl>
    </section>
@endif
