@if($product->categories->isNotEmpty())
    <x-pb-block :data="$block" class="mt-4 text-sm text-gray-500">
        <span class="font-medium text-gray-700">{{ $block['label'] ?? 'Categories' }}:</span>
        @foreach($product->categories as $category)
            <a href="{{ $category->url() }}" class="text-indigo-600 hover:underline">{{ $category->name }}</a>{{ ! $loop->last ? ',' : '' }}
        @endforeach
    </x-pb-block>
@endif
