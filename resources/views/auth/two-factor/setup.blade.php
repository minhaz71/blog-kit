@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-2xl font-bold">Two-factor authentication</h1>

    @if(session('success'))
        <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if(session('recovery_codes'))
        <div class="mt-4 rounded-lg border-2 border-amber-400 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">Save these one-time recovery codes.</p>
            <p class="mt-1 text-xs text-amber-800">If you lose your authenticator you can use these to sign in — one per code. Once you leave this page, you won't see them again.</p>
            <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                @foreach(session('recovery_codes') as $code)
                    <span class="rounded bg-white px-2 py-1">{{ $code }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if(! $isConfirmed)
        <p class="mt-4 text-gray-600">
            Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password), then type the 6-digit code it shows to confirm.
        </p>
        <div class="mt-4 flex justify-center">
            <div class="w-48 h-48">{!! $qrSvg !!}</div>
        </div>
        <p class="mt-2 text-center text-xs text-gray-500">
            Can't scan? Enter this secret manually:
            <code class="ml-1 select-all rounded bg-gray-100 px-2 py-0.5 font-mono">{{ $secret }}</code>
        </p>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6 space-y-3">
            @csrf
            <label for="code" class="block text-sm font-medium">Verification code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                   class="w-full rounded-md border-gray-300 text-center tracking-widest text-lg focus:border-indigo-500"
                   maxlength="6" placeholder="123456">
            @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button class="w-full rounded-full bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Confirm & enable
            </button>
        </form>
    @else
        <div class="mt-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">
            Two-factor authentication is <strong>enabled</strong> on your account.
        </div>

        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-6 space-y-3">
            @csrf
            @method('DELETE')
            <label for="code" class="block text-sm font-medium">Enter a current code to disable</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                   class="w-full rounded-md border-gray-300 text-center tracking-widest text-lg"
                   maxlength="6" placeholder="123456">
            @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button class="w-full rounded-full bg-red-600 py-2 text-sm font-semibold text-white hover:bg-red-700">
                Disable two-factor
            </button>
        </form>
    @endif
</div>
@endsection
