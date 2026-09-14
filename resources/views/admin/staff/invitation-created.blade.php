@extends('layouts.admin')

@section('title', $regenerated ? 'Invitation link regenerated' : 'Invitation link created')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <p class="text-xs uppercase tracking-widest text-amber-700 mb-2">Administration</p>
        <h1 class="text-3xl font-semibold">{{ $regenerated ? 'Replacement invitation link' : 'Invitation link ready' }}</h1>
        <p class="text-sm text-gray-600 mt-2">
            Share this link through WhatsApp or another trusted channel. Email delivery is not used for invitations.
        </p>
    </div>

    <div class="bg-amber-50 border border-amber-200 text-amber-950 rounded-xl p-4 text-sm" role="status">
        This link is shown only once. Share it only with the intended staff member.
    </div>

    <section class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm space-y-4">
        <h2 class="text-xl font-semibold">Invitation details</h2>
        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Name</dt>
                <dd class="font-medium">{{ $invitation->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Email</dt>
                <dd class="font-medium">{{ $invitation->email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Assigned role</dt>
                <dd class="font-medium">{{ $roles[$invitation->admin_role] ?? $invitation->admin_role }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Expires</dt>
                <dd class="font-medium">{{ $invitation->expires_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</dd>
            </div>
        </dl>

        <div>
            <label class="block text-sm font-medium mb-1" for="invitation-url">One-time invitation URL</label>
            <input id="invitation-url" type="text" readonly value="{{ $acceptUrl }}"
                autocomplete="off" spellcheck="false" aria-describedby="invitation-copy-status"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px] font-mono text-xs sm:text-sm bg-gray-50">
        </div>

        <div class="flex flex-wrap gap-3 items-center">
            <button type="button" id="copy-invitation-link"
                class="bg-gray-900 text-white rounded-lg px-4 py-3 font-medium min-h-[44px]">
                Copy invitation link
            </button>
            <p id="invitation-copy-status" class="text-sm text-gray-600" aria-live="polite"></p>
        </div>
    </section>

    <p>
        <a href="{{ route('admin.staff.index') }}" class="text-sm underline">Return to Staff &amp; Roles</a>
    </p>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('invitation-url');
    var button = document.getElementById('copy-invitation-link');
    var status = document.getElementById('invitation-copy-status');
    if (!input || !button || !status) return;

    button.addEventListener('click', function () {
        var text = input.value;
        function copied() {
            status.textContent = 'Invitation link copied.';
        }
        function fallback() {
            input.focus();
            input.select();
            status.textContent = 'Select the link and copy it with your keyboard.';
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(copied).catch(fallback);
            return;
        }
        fallback();
    });
})();
</script>
@endpush
