@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <h1 class="text-3xl font-bold">Choose a new password</h1>
    <form action="{{ route('password.update') }}" method="POST" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="text-sm font-medium">Email</label>
            <input id="email" type="email" name="email" value="{{ $email }}" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <div>
            <label for="password" class="text-sm font-medium">New password</label>
            <input id="password" type="password" name="password" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <div>
            <label for="password_confirmation" class="text-sm font-medium">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <button class="w-full rounded-md bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-500">Update password</button>
    </form>
</div>
@endsection
