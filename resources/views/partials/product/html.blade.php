@if(! empty($block['content']))
    <x-pb-block :data="$block" class="mt-6">
        <div class="pb-custom-html prose prose-sm max-w-none">{!! parse_shortcodes($block['content']) !!}</div>
    </x-pb-block>
@endif
