@php
    $labels = [
        'cash' => 'Cash on Delivery', 'card' => 'Card on Delivery', 'cod' => 'Cash on Delivery',
        'paypal' => 'PayPal', 'visa' => 'VISA', 'mastercard' => 'Mastercard', 'amex' => 'Amex',
        'applepay' => 'Apple Pay', 'gpay' => 'G Pay', 'tabby' => 'tabby', 'tamara' => 'tamara',
    ];
    // Pay-on-delivery methods get the brand-filled chip; card networks stay neutral.
    $highlight = ['cash', 'card', 'cod'];
    $methods = (array) ($block['methods'] ?? ['cash', 'card', 'visa', 'mastercard', 'applepay', 'gpay']);
@endphp
<x-pb-block :data="$block" class="mt-5">
    @if(! empty($block['heading']))
        <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-gray-500" style="color: var(--pb-heading, )">
            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
            {{ $block['heading'] }}
        </p>
    @endif
    <div class="mt-2.5 flex flex-wrap items-center gap-2">
        @foreach($methods as $method)
            @if(in_array($method, $highlight, true))
                <span class="inline-flex h-8 items-center gap-1 rounded-lg bg-indigo-600 px-2.5 text-xs font-bold text-white shadow-sm">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/></svg>
                    {{ $labels[$method] ?? ucfirst($method) }}
                </span>
            @else
                <span class="inline-flex h-8 items-center rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-semibold text-gray-600 shadow-sm">
                    {{ $labels[$method] ?? ucfirst($method) }}
                </span>
            @endif
        @endforeach
    </div>
    @if(! empty($block['note']))
        <p class="mt-2 text-sm text-gray-500">{{ $block['note'] }}</p>
    @endif
</x-pb-block>
