@extends('layouts.admin')

@section('title', 'Passkeys')

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Passkeys</h1>
        <p class="text-sm text-gray-600 mt-2">
            Passkeys use the WebAuthn standard. Your browser may show a temporary QR code on desktop so you can approve with your phone.
            Only public-key data is stored here — never biometrics.
        </p>
    </div>

    @unless($mfaEnabled)
    <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
        Enroll <a href="{{ route('admin.mfa.manage') }}" class="underline">two-factor authentication</a> before adding or removing passkeys.
    </div>
    @endunless

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Added</th>
                    <th class="px-4 py-3">Last used</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($passkeys as $passkey)
                <tr class="border-t" data-passkey-id="{{ $passkey->id }}">
                    <td class="px-4 py-3 font-medium">{{ $passkey->name }}</td>
                    <td class="px-4 py-3">{{ optional($passkey->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                    <td class="px-4 py-3">{{ $passkey->last_used_at ? $passkey->last_used_at->timezone(config('app.timezone'))->format('d M Y H:i') : '—' }}</td>
                    <td class="px-4 py-3 space-x-2">
                        <button type="button" class="text-gray-700 underline passkey-rename-btn" data-name="{{ $passkey->name }}">Rename</button>
                        <button type="button" class="text-red-700 underline passkey-remove-btn">Remove</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-gray-500">No passkeys registered yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form id="passkey-register-form" class="bg-white rounded shadow p-4 space-y-3">
        @csrf
        <h2 class="font-medium">Add a passkey</h2>
        <p class="text-sm text-gray-600">Confirm your password and current authenticator code to register a new passkey.</p>
        <div>
            <label class="block text-sm mb-1" for="passkey_name">Passkey name</label>
            <input id="passkey_name" name="name" type="text" required maxlength="255" placeholder="MacBook, iPhone, YubiKey"
                   class="w-full border px-3 py-2 rounded">
        </div>
        <div>
            <label class="block text-sm mb-1" for="register_password">Current password</label>
            <input id="register_password" name="current_password" type="password" required autocomplete="current-password"
                   class="w-full border px-3 py-2 rounded">
        </div>
        <div>
            <label class="block text-sm mb-1" for="register_totp">Authenticator code</label>
            <input id="register_totp" name="totp_code" type="text" inputmode="numeric" required maxlength="64"
                   class="w-full border px-3 py-2 rounded">
        </div>
        <p id="passkey-register-error" class="hidden text-sm text-red-600"></p>
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm" @disabled(! $mfaEnabled)>Register passkey</button>
    </form>
</div>
@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/admin-passkeys.js') }}"></script>
<script>
const optionsUrl = @json(route('admin.passkeys.register.options'));
const storeUrl = @json(route('admin.passkeys.register'));
const updateUrlTemplate = @json(route('admin.passkeys.update', ['passkey' => '__PASSKEY__']));
const deleteUrlTemplate = @json(route('admin.passkeys.destroy', ['passkey' => '__PASSKEY__']));

function managementPayload(extra = {}) {
    return {
        current_password: document.getElementById('register_password')?.value ?? prompt('Current password'),
        totp_code: document.getElementById('register_totp')?.value ?? prompt('Authenticator code'),
        ...extra,
    };
}

document.getElementById('passkey-register-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const errorBox = document.getElementById('passkey-register-error');
    errorBox.classList.add('hidden');
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
        const payload = {
            name: form.name.value,
            current_password: form.current_password.value,
            totp_code: form.totp_code.value,
        };
        await AdminPasskeys.register(optionsUrl, storeUrl, payload);
        window.location.reload();
    } catch (error) {
        errorBox.textContent = error.message || 'Unable to register passkey.';
        errorBox.classList.remove('hidden');
    } finally {
        button.disabled = false;
    }
});

document.querySelectorAll('.passkey-rename-btn').forEach((button) => {
    button.addEventListener('click', async () => {
        const row = button.closest('tr');
        const passkeyId = row.dataset.passkeyId;
        const newName = prompt('New passkey name', button.dataset.name);
        if (!newName) return;
        try {
            await AdminPasskeys.postJson(updateUrlTemplate.replace('__PASSKEY__', passkeyId), {
                ...managementPayload(),
                name: newName,
            });
            window.location.reload();
        } catch (error) {
            alert(error.message || 'Unable to rename passkey.');
        }
    });
});

document.querySelectorAll('.passkey-remove-btn').forEach((button) => {
    button.addEventListener('click', async () => {
        if (!confirm('Remove this passkey?')) return;
        const row = button.closest('tr');
        const passkeyId = row.dataset.passkeyId;
        try {
            await fetch(deleteUrlTemplate.replace('__PASSKEY__', passkeyId), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(managementPayload()),
            }).then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message ?? data.errors?.passkey?.[0] ?? data.errors?.totp_code?.[0] ?? 'Unable to remove passkey.');
                }
            });
            window.location.reload();
        } catch (error) {
            alert(error.message || 'Unable to remove passkey.');
        }
    });
});
</script>
@endpush
