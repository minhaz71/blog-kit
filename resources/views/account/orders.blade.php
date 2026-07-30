@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">My orders</h1>
    <div class="mt-6 flex flex-col gap-8 lg:flex-row">
        @include('account.partials.nav')
        <div class="flex-1">
            @if($orders->isEmpty())
                <p class="text-sm text-gray-500">No orders yet.</p>
            @else
                @include('account.partials.orders-table', ['orders' => $orders])
                <div class="mt-4">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
