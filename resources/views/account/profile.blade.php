@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">Profile</h1>
    <div class="mt-6 flex flex-col gap-8 lg:flex-row">
        @include('account.partials.nav')
        <div class="max-w-xl flex-1">
            <form action="{{ route('account.profile.update') }}" method="POST" class="space-y-4">
                @csrf @method('PATCH')
                <div>
                    <label for="name" class="text-sm font-medium">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label class="text-sm font-medium">Email</label>
                    <input type="email" value="{{ $user->email }}" disabled class="mt-1 w-full rounded-md border-gray-200 bg-gray-50 text-gray-500">
                </div>
                <div>
                    <label for="phone" class="text-sm font-medium">Phone</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="accepts_marketing" value="1" @checked($user->accepts_marketing) class="rounded border-gray-300">
                    Send me offers and news by email
                </label>

                <fieldset class="rounded-lg border border-gray-200 p-4">
                    <legend class="px-1 text-sm font-semibold">Change password</legend>
                    <div class="space-y-3">
                        <input type="password" name="current_password" placeholder="Current password" class="w-full rounded-md border-gray-300 text-sm" autocomplete="current-password">
                        <input type="password" name="password" placeholder="New password" class="w-full rounded-md border-gray-300 text-sm" autocomplete="new-password">
                        <input type="password" name="password_confirmation" placeholder="Confirm new password" class="w-full rounded-md border-gray-300 text-sm" autocomplete="new-password">
                    </div>
                </fieldset>

                <button class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Save changes</button>
            </form>

            <div class="mt-8 rounded-lg border border-gray-200 p-4">
                <p class="text-sm font-semibold">Two-factor authentication</p>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $user->two_factor_confirmed_at ? 'Enabled — you\'ll be asked for a code when you sign in.' : 'Add an extra layer of security with an authenticator app.' }}
                </p>
                <a href="{{ route('two-factor.show') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">
                    {{ $user->two_factor_confirmed_at ? 'Manage' : 'Set up' }} two-factor →
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
