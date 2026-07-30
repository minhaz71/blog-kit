@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <h1 class="text-3xl font-bold">Reset password</h1>
    <p class="mt-2 text-sm text-gray-600">Enter your email and we'll send you a reset link.</p>
    <form action="{{ route('password.email') }}" method="POST" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="text-sm font-medium">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <button class="w-full rounded-md bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-500">Send reset link</button>
    </form>
    @include('partials.auth.no-account')
    <p class="mt-4 text-center text-sm text-gray-600">Remembered it? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Back to sign in</a></p>
</div>
@endsection
