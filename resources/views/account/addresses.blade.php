@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">Addresses</h1>
    <div class="mt-6 flex flex-col gap-8 lg:flex-row">
        @include('account.partials.nav')
        <div class="flex-1" x-data="{ adding: false }">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($addresses as $address)
                    <div class="rounded-lg border border-gray-200 p-4 text-sm">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold">{{ $address->label ?: str($address->type)->headline() }}
                                @if($address->is_default)<span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700">Default</span>@endif
                            </p>
                            <form action="{{ route('account.addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Remove this address?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">Remove</button>
                            </form>
                        </div>
                        <address class="mt-2 not-italic text-gray-600">
                            {{ $address->fullName() }}<br>
                            {{ $address->address_line_1 }}<br>
                            {{ $address->city }}@if($address->state), {{ $address->state }}@endif {{ $address->postal_code }}<br>
                            {{ $address->country }}
                        </address>
                    </div>
                @endforeach
            </div>

            <button @click="adding = !adding" class="mt-6 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">
                + Add address
            </button>

            <form action="{{ route('account.addresses.store') }}" method="POST" class="mt-4 grid max-w-xl gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-2" x-show="adding" x-cloak>
                @csrf
                <select name="type" class="rounded-md border-gray-300 text-sm">
                    <option value="shipping">Shipping</option>
                    <option value="billing">Billing</option>
                </select>
                <input type="text" name="label" placeholder="Label (Home, Office…)" class="rounded-md border-gray-300 text-sm">
                <input type="text" name="first_name" required placeholder="First name *" class="rounded-md border-gray-300 text-sm">
                <input type="text" name="last_name" required placeholder="Last name *" class="rounded-md border-gray-300 text-sm">
                <input type="text" name="address_line_1" required placeholder="Address *" class="rounded-md border-gray-300 text-sm sm:col-span-2">
                <input type="text" name="city" required placeholder="City *" class="rounded-md border-gray-300 text-sm">
                <input type="text" name="state" placeholder="State" class="rounded-md border-gray-300 text-sm">
                <input type="text" name="postal_code" placeholder="Postal code" class="rounded-md border-gray-300 text-sm">
                <input type="text" name="country" required maxlength="2" placeholder="Country code (US) *" class="rounded-md border-gray-300 text-sm uppercase">
                <input type="tel" name="phone" placeholder="Phone" class="rounded-md border-gray-300 text-sm">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="rounded border-gray-300"> Set as default</label>
                <button class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 sm:col-span-2">Save address</button>
            </form>
        </div>
    </div>
</div>
@endsection
