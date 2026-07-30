@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">Wishlist</h1>
    <div class="mt-6 flex flex-col gap-8 lg:flex-row">
        @include('account.partials.nav')
        <div class="flex-1">
            @if($products->isEmpty())
                <p class="text-sm text-gray-500">Your wishlist is empty.</p>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach($products as $product)
                        <div class="relative">
                            <x-product-card :product="$product" />
                            <form action="{{ route('wishlist.toggle', $product) }}" method="POST" class="absolute right-2 top-2">
                                @csrf
                                <button class="rounded-full bg-white/90 px-2 py-1 text-xs shadow hover:text-red-500" aria-label="Remove from wishlist">✕</button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
