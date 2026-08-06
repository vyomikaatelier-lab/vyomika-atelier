@extends('layouts.admin')

@section('title', 'MFA settings')

@section('content')
<div class="max-w-lg space-y-6">
    <h1 class="text-2xl font-semibold">Two-factor authentication</h1>
    <div class="bg-white rounded shadow p-4 text-sm space-y-2">
        <p><strong>Status:</strong> {{ $enabled ? 'Enabled' : 'Not enabled' }}</p>
        @if($confirmedAt)
        <p><strong>Confirmed:</strong> {{ $confirmedAt->timezone(config('app.timezone'))->format('d M Y H:i') }}</p>
        @endif
    </div>
    @if($enabled)
    <form method="POST" action="{{ route('admin.mfa.recovery.regenerate') }}" class="bg-white rounded shadow p-4 space-y-3">
        @csrf
        <p class="text-sm text-gray-600">Regenerate recovery codes. Old codes stop working immediately.</p>
        <div>
            <label class="block text-sm mb-1" for="regen_password">Current admin password</label>
            <input id="regen_password" type="password" name="current_password" required autocomplete="current-password"
                   class="w-full border px-3 py-2 rounded @error('current_password') border-red-500 @enderror">
            @error('current_password')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Regenerate recovery codes</button>
    </form>
    <form method="POST" action="{{ route('admin.mfa.disable') }}" class="bg-white rounded shadow p-4 space-y-3 border border-red-100">
        @csrf
        <p class="text-sm text-gray-600">Disable MFA (you must re-enroll immediately to keep using the panel).</p>
        <div>
            <label class="block text-sm mb-1" for="disable_password">Current admin password</label>
            <input id="disable_password" type="password" name="current_password" required autocomplete="current-password"
                   class="w-full border px-3 py-2 rounded @error('current_password') border-red-500 @enderror">
        </div>
        <button type="submit" class="bg-red-700 text-white px-4 py-2 rounded text-sm"
                onclick="return confirm('Disable MFA and re-enroll now?')">Disable MFA</button>
    </form>
    @else
    <a href="{{ route('admin.mfa.enroll') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm inline-block">Enroll MFA</a>
    @endif
</div>
@endsection
