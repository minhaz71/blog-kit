@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">My account</h1>
    <div class="mt-6 flex flex-col gap-8 lg:flex-row">
        @include('account.partials.nav')
        <div class="flex-1">
            <p class="text-gray-600">Hi {{ $user->name }} 👋</p>

            @unless($user->hasVerifiedEmail())
                <div class="mt-4 flex items-center justify-between rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    <span>Please verify your email address.</span>
                    <form action="{{ route('verification.send') }}" method="POST">
                        @csrf
                        <button class="font-semibold underline">Resend link</button>
                    </form>
                </div>
            @endunless

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Orders</p>
                    <p class="mt-1 text-2xl font-bold">{{ $orderCount }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Wishlist</p>
                    <p class="mt-1 text-2xl font-bold">{{ $wishlistCount }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm text-gray-500">Total spent</p>
                    <p class="mt-1 text-2xl font-bold">{{ price_format($user->lifetimeValue()) }}</p>
                </div>
            </div>

            <h2 class="mt-8 text-lg font-semibold">Recent orders</h2>
            @if($recentOrders->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No orders yet. <a href="{{ route('shop') }}" class="text-indigo-600 underline">Start shopping</a></p>
            @else
                @include('account.partials.orders-table', ['orders' => $recentOrders])
            @endif

            <p class="mt-8 text-xs text-gray-400">
                <a href="{{ route('account.data-export') }}" class="underline">Download my data</a> ·
                To delete your account and personal data, contact support.
            </p>
        </div>
    </div>
</div>
@endsection
