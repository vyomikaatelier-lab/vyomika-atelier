@extends('layouts.admin')

@section('title', 'Set up staff account')

@section('content')
<div class="max-w-lg mx-auto bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <p class="text-xs uppercase tracking-widest text-amber-700 mb-2">Secure staff invitation</p>
    <h1 class="text-2xl font-semibold mb-2">Set up your account</h1>
    <p class="text-sm text-gray-600 mb-6">
        {{ $invitation->name }} · {{ $invitation->email }} ·
        {{ \App\Support\AdminRole::labels()[$invitation->admin_role] ?? 'Staff' }}
    </p>

    <form method="POST" action="{{ route('admin.staff-invitations.store', $invitation) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="password" class="block text-sm font-medium mb-1">Create password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
            <p class="text-xs text-gray-500 mt-1">At least 12 characters with uppercase, lowercase, number and symbol.</p>
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
        </div>

        <button class="w-full bg-gray-900 text-white rounded-lg px-4 py-3 font-medium">Create staff account</button>
    </form>
</div>
@endsection
