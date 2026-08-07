@extends('layouts.admin-guest')

@section('title', 'Enroll MFA')

@section('content')
<div class="max-w-md mx-auto bg-white p-6 rounded shadow space-y-4">
    <h1 class="text-xl font-semibold">Set up two-factor authentication</h1>
    @if($mustEnroll)
    <p class="text-sm text-amber-700">Enrollment is required before you can use the admin panel.</p>
    @elseif($graceEndsAt)
    <p class="text-sm text-gray-600">Grace period ends {{ $graceEndsAt->timezone(config('app.timezone'))->format('d M Y H:i') }}.</p>
    @endif
    <p class="text-sm text-gray-600">Scan this QR code with Google Authenticator, 1Password, or any TOTP app. Or enter the secret manually.</p>
    <div class="flex justify-center">
        <img src="{{ $qrDataUri }}" alt="MFA QR code" width="220" height="220" class="border rounded">
    </div>
    <p class="text-xs font-mono break-all bg-gray-50 p-2 rounded">{{ $secret }}</p>
    <form method="POST" action="{{ route('admin.mfa.enroll.submit') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm mb-1" for="current_password">Current admin password</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
                   class="w-full border px-3 py-2 rounded @error('current_password') border-red-500 @enderror">
            @error('current_password')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm mb-1" for="code">Code from authenticator</label>
            <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required
                   class="w-full border px-3 py-2 rounded @error('code') border-red-500 @enderror" placeholder="123456">
            @error('code')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="w-full bg-gray-900 text-white px-4 py-2 rounded text-sm">Enable MFA</button>
    </form>
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="text-sm text-gray-500 underline">Sign out</button>
    </form>
</div>
@endsection
