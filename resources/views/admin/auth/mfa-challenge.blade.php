@extends('layouts.admin-guest')

@section('title', 'Admin MFA')

@section('content')
<div class="max-w-sm mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-xl font-semibold mb-2">Two-factor authentication</h1>
    <p class="text-sm text-gray-600 mb-4">Enter the 6-digit code from your authenticator app, or a recovery code.</p>
    <form method="POST" action="{{ route('admin.mfa.challenge.submit') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1" for="code">Authentication code</label>
            <input id="code" type="text" name="code" inputmode="text" autocomplete="one-time-code" required
                   class="w-full border px-3 py-2 rounded @error('code') border-red-500 @enderror"
                   placeholder="123456 or XXXX-XXXX">
            @error('code')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="w-full bg-gray-900 text-white px-4 py-2 rounded text-sm">Verify</button>
    </form>
    <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm text-gray-500 underline">Sign out</button>
    </form>
</div>
@endsection
