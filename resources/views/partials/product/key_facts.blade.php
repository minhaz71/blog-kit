@php
    // Normalise specifications (supports both {"Flavor":"Menthol"} maps and
    // [{"name":..,"value":..}] lists) into label/value pairs.
    $facts = [];
    if (($block['use_specifications'] ?? true)) {
        // Taxonomy attributes lead (canonical facets, mirrors the
        // additionalProperty schema), legacy free-form specs follow.
        foreach ($product->attributeFacts() as $label => $value) {
            $facts[] = [$label, $value];
        }
        if (is_array($product->specifications)) {
            foreach ($product->specifications as $key => $spec) {
                if (is_array($spec)) {
                    $facts[] = [$spec['name'] ?? $spec['label'] ?? '', $spec['value'] ?? ''];
                } elseif (! is_int($key)) {
                    $facts[] = [$key, $spec];
                }
            }
        }
    }
    foreach ((array) ($block['items'] ?? []) as $item) {
        if (! empty($item['label'])) {
            $facts[] = [$item['label'], $item['value'] ?? ''];
        }
    }
    $facts = array_filter($facts, fn ($f) => trim((string) $f[0]) !== '');
    $facts = collect($facts)->unique(fn ($f) => mb_strtolower(trim((string) $f[0])))->values()->all();
@endphp
@if($facts !== [])
    <x-pb-block :data="$block" class="mt-4">
        @if(! empty($block['heading']))
            <p class="mb-2 font-semibold" style="color: var(--pb-heading, inherit)">{{ $block['heading'] }}</p>
        @endif
        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
            @foreach($facts as [$label, $value])
                <li><span class="font-medium text-gray-800">{{ $label }}:</span> {{ $value }}</li>
            @endforeach
        </ul>
    </x-pb-block>
@endif
