@php
    $items = (array) $section->setting('items', []);
@endphp
@if(count($items) > 0)
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="{{ $section->title ?? 'What our customers say' }}">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 truncate text-base font-bold text-gray-900 sm:text-lg">
                    <span class="h-5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>{{ $section->title ?? 'What our customers say' }}
                </h2>
                @if($section->subtitle)<p class="mt-0.5 truncate text-xs text-gray-500">{{ $section->subtitle }}</p>@endif
            </div>
        </div>
        <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($items as $item)
                <figure class="rounded-lg border border-gray-100 bg-gray-50/60 p-4">
                    <div class="flex text-amber-400" aria-hidden="true">
                        @for($i = 0; $i < 5; $i++)
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.363 1.118l1.286 3.958c.3.921-.755 1.688-1.538 1.118l-3.367-2.446a1 1 0 00-1.175 0l-3.367 2.446c-.783.57-1.838-.197-1.538-1.118l1.286-3.958a1 1 0 00-.363-1.118L2.063 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.958z"/></svg>
                        @endfor
                    </div>
                    <blockquote class="mt-2 text-sm text-gray-700">
                        <p>"{{ $item['quote'] ?? '' }}"</p>
                    </blockquote>
                    <figcaption class="mt-3 flex items-center gap-2 text-xs">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-teal-100 font-bold text-teal-700">{{ mb_substr($item['author'] ?? 'C', 0, 1) }}</span>
                        <span>
                            <span class="block font-semibold text-gray-900">{{ $item['author'] ?? 'Customer' }}</span>
                            @if(! empty($item['location']))<span class="block text-gray-500">{{ $item['location'] }} · Verified buyer</span>@endif
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif
