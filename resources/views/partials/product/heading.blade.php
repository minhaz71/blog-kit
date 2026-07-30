@if(! empty($block['text']))
    <x-pb-block :data="$block" class="mt-8">
        @php $tag = in_array($block['level'] ?? 'h2', ['h2', 'h3', 'h4']) ? $block['level'] : 'h2'; @endphp
        <{{ $tag }} class="text-xl font-bold" style="color: var(--pb-heading, inherit)">{{ $block['text'] }}</{{ $tag }}>
    </x-pb-block>
@endif
