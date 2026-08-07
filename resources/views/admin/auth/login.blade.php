@extends('layouts.admin-guest')

@section('title', 'Login')

@section('content')
<div class="max-w-sm mx-auto mt-20">
    <h1 class="text-2xl font-semibold mb-6">Admin Login</h1>
    @if(session('info'))
        <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded mb-4 text-sm">{{ session('info') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ $errors->first() }}</div>
    @endif
    <div id="passkey-login-error" class="hidden bg-red-100 text-red-700 px-4 py-2 rounded mb-4 text-sm"></div>
    <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="w-full border px-3 py-2 rounded">
        <input type="password" name="password" placeholder="Password" required class="w-full border px-3 py-2 rounded">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <button type="submit" class="w-full bg-gray-900 text-white py-2 rounded hover:bg-gray-800">Login</button>
    </form>
    <div class="mt-6 bg-white p-6 rounded-lg shadow space-y-3">
        <button type="button" id="admin-passkey-login" class="w-full border border-gray-900 text-gray-900 py-2 rounded hover:bg-gray-50">
            Sign in with a passkey
        </button>
        <p class="text-xs text-gray-600 leading-relaxed">
            On desktop, your browser may show a temporary QR code so you can approve sign-in on your phone with Face ID, fingerprint, or device PIN.
            Biometric data never leaves your device and is not sent to this website.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/admin-passkeys.js') }}"></script>
<script>
document.getElementById('admin-passkey-login')?.addEventListener('click', async () => {
    const errorBox = document.getElementById('passkey-login-error');
    errorBox.classList.add('hidden');
    errorBox.textContent = '';
    const button = document.getElementById('admin-passkey-login');
    button.disabled = true;
    try {
        await AdminPasskeys.login(
            @json(route('admin.passkeys.login.options')),
            @json(route('admin.passkeys.login'))
        );
    } catch (error) {
        errorBox.textContent = error.message || 'Unable to sign in with a passkey.';
        errorBox.classList.remove('hidden');
    } finally {
        button.disabled = false;
    }
});
</script>
@endpush
