@if($product->short_description)
    <x-pb-block :data="$block" class="mt-4">
        <div class="prose prose-sm max-w-none text-gray-600">{!! $product->short_description !!}</div>
    </x-pb-block>
@endif
