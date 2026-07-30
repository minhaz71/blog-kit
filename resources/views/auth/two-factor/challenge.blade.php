@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-2xl font-bold">Two-factor verification</h1>
    <p class="mt-2 text-gray-600">Enter the 6-digit code from your authenticator app, or a one-time recovery code.</p>

    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-3">
        @csrf
        <label for="code" class="block text-sm font-medium">Code</label>
        <input id="code" name="code" autocomplete="one-time-code" autofocus required
               class="w-full rounded-md border-gray-300 text-center tracking-widest text-lg focus:border-indigo-500"
               placeholder="123456 or RECOV-CODE1">
        @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="w-full rounded-full bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Verify
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center text-sm">
        @csrf
        <button class="text-gray-500 underline">Log out instead</button>
    </form>
</div>
@endsection
