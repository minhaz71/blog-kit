@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">
        @if($term !== '') Search results for “{{ $term }}” @else Search @endif
    </h1>

    <form action="{{ route('search') }}" method="GET" class="mt-4 max-w-xl" role="search">
        <div class="flex gap-2">
            <input type="search" name="q" value="{{ $term }}" placeholder="Search products…" class="w-full rounded-md border-gray-300" aria-label="Search products">
            <button class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Search</button>
        </div>
    </form>

    <div class="mt-8">
        @if($term === '')
            <p class="text-gray-500">Type something to search the catalog.</p>
        @elseif($products->isEmpty())
            <p class="text-gray-500">No products matched “{{ $term }}”. Try a different keyword.</p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                @foreach($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
</div>
@endsection
