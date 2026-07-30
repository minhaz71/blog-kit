@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <h1 class="text-3xl font-bold">Create account</h1>
    <form action="{{ route('register') }}" method="POST" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="name" class="text-sm font-medium">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <div>
            <label for="email" class="text-sm font-medium">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', request('email')) }}" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <div>
            <label for="password" class="text-sm font-medium">Password</label>
            <input id="password" type="password" name="password" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <div>
            <label for="password_confirmation" class="text-sm font-medium">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="accepts_marketing" value="1" class="rounded border-gray-300">
            Send me offers and news by email
        </label>
        <button class="w-full rounded-md bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-500">Create account</button>
    </form>
    <p class="mt-4 text-center text-sm text-gray-600">Already registered? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Sign in</a></p>
</div>
@endsection
