@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">Shop</h1>

    <div class="mt-6 flex flex-col gap-8 lg:flex-row">
        @include('shop.partials.filters')
        <div class="flex-1">
            @include('shop.partials.product-grid')
        </div>
    </div>
</div>
@endsection
