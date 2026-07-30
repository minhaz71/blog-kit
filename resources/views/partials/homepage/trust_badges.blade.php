@php
    $items = (array) $section->setting('items', []);
@endphp
@if(count($items) > 0)
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if($section->title)
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900 sm:text-lg">
                    <span class="h-5 w-1.5 shrink-0 rounded-full bg-teal-600"></span>{{ $section->title }}
                </h2>
            </div>
        @endif
        <div class="grid grid-cols-2 divide-y divide-gray-100 sm:divide-y-0 sm:divide-x sm:grid-cols-{{ min(4, count($items)) }}">
            @foreach($items as $item)
                <div class="flex flex-col items-center justify-center px-4 py-4 text-center">
                    <svg class="h-6 w-6 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <p class="mt-1.5 text-sm font-bold text-gray-900">{{ $item['label'] ?? '' }}</p>
                    @if(! empty($item['sub_label']))<p class="mt-0.5 text-xs text-gray-500">{{ $item['sub_label'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </section>
@endif
