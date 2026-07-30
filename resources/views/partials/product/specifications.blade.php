@php
    // Taxonomy attributes first (canonical, matches the additionalProperty
    // schema), then legacy free-form specifications — deduped by label so
    // an old "Flavor: Menthol" spec never doubles a taxonomy row.
    $specs = [];
    foreach ($product->attributeFacts() as $label => $value) {
        $specs[] = [$label, $value];
    }
    if (is_array($product->specifications)) {
        foreach ($product->specifications as $key => $spec) {
            if (is_array($spec)) {
                $specs[] = [$spec['name'] ?? $spec['label'] ?? '', $spec['value'] ?? ''];
            } elseif (! is_int($key)) {
                $specs[] = [$key, $spec];
            }
        }
    }
    $specs = array_filter($specs, fn ($s) => trim((string) $s[0]) !== '');
    $specs = collect($specs)->unique(fn ($s) => mb_strtolower(trim((string) $s[0])))->values()->all();
@endphp
@if($specs !== [])
    <x-pb-block :data="$block" class="mt-12">
        <section aria-labelledby="specs-heading">
            <h2 id="specs-heading" class="text-xl font-bold" style="color: var(--pb-heading, inherit)">{{ $block['heading'] ?? 'Specifications' }}</h2>
            <dl class="mt-3 divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 text-sm">
                @foreach($specs as [$label, $value])
                    <div class="flex justify-between gap-4 p-3 odd:bg-gray-50">
                        <dt class="font-medium text-gray-500">{{ $label }}</dt>
                        <dd class="text-right text-gray-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </x-pb-block>
@endif
