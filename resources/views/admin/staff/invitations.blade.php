@extends('layouts.admin')

@section('title', 'Staff Invitations')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-2xl">
            <p class="text-xs uppercase tracking-widest text-amber-700 mb-2">Administration</p>
            <h1 class="text-3xl font-semibold">Staff Invitations</h1>
            <p class="text-sm text-gray-600 mt-2">Send a secure invitation email. Pending and previous invitations stay on this page. If delivery fails, a one-time link is shown once so you can share it manually.</p>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="border border-gray-300 rounded-lg px-4 py-3 font-medium min-h-[44px] inline-flex items-center">Back to Staff &amp; Roles</a>
    </div>

    <section class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm max-w-3xl">
        <h2 class="text-xl font-semibold mb-2">Invite staff member</h2>
        <p class="text-sm text-gray-600 mb-4">The invitation is emailed when delivery succeeds.</p>
        <form method="POST" action="{{ route('admin.staff.invite.legacy') }}" class="grid sm:grid-cols-2 gap-4">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="staff-name">Name</label>
                <input id="staff-name" name="name" value="{{ old('name') }}" required autocomplete="name" class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="staff-email">Email</label>
                <input id="staff-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off" class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="staff-role">Role</label>
                <select id="staff-role" name="admin_role" required class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
                    @foreach($roles as $value => $label)
                        @if($value !== \App\Support\AdminRole::OWNER)
                            <option value="{{ $value }}" @selected(old('admin_role') === $value)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <button class="bg-gray-900 text-white rounded-lg px-4 py-3 font-medium min-h-[44px]">Send invitation</button>
            </div>
        </form>
    </section>

    <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" aria-labelledby="invitation-history-heading">
        <div class="p-6 border-b">
            <h2 id="invitation-history-heading" class="text-xl font-semibold">Invitation history</h2>
            <p class="text-sm text-gray-600 mt-1">Pending and previous invitations.</p>
        </div>
        <div class="hidden md:grid md:grid-cols-[minmax(0,1.6fr)_minmax(8rem,0.8fr)_minmax(7rem,0.7fr)_minmax(9rem,0.9fr)_auto] gap-3 px-4 py-3 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <span>Staff</span>
            <span>Role</span>
            <span>Status</span>
            <span>Expires</span>
            <span>Actions</span>
        </div>
        <div class="divide-y">
        @forelse($invitations as $invitation)
            <article class="p-4 grid gap-3 md:grid-cols-[minmax(0,1.6fr)_minmax(8rem,0.8fr)_minmax(7rem,0.7fr)_minmax(9rem,0.9fr)_auto] md:items-center">
                <div class="min-w-0">
                    <strong class="block">{{ $invitation->name }}</strong>
                    <span class="block text-sm text-gray-500 break-all">{{ $invitation->email }}</span>
                </div>
                <div>
                    <span class="md:hidden text-xs uppercase tracking-wide text-gray-500">Role</span>
                    <span class="block text-sm">{{ $roles[$invitation->admin_role] ?? $invitation->admin_role }}</span>
                </div>
                <div>
                    <span class="md:hidden text-xs uppercase tracking-wide text-gray-500">Status</span>
                    <span class="block text-sm">{{ $invitation->statusLabel() }}</span>
                </div>
                <div>
                    <span class="md:hidden text-xs uppercase tracking-wide text-gray-500">Expires</span>
                    <span class="block text-sm">{{ $invitation->expires_at?->timezone(config('app.timezone'))->format('d M Y, H:i') ?? 'n/a' }}</span>
                </div>
                @if($invitation->isPending())
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.staff.invite.legacy') }}">
                            @csrf
                            <input type="hidden" name="staff_invitation_action" value="regenerate">
                            <input type="hidden" name="invitation_id" value="{{ $invitation->getKey() }}">
                            <button class="border border-gray-900 rounded-lg px-3 py-2 text-sm min-h-[44px]">Regenerate Link</button>
                        </form>
                        <form method="POST" action="{{ route('admin.staff-invitations.revoke', $invitation) }}">
                            @csrf
                            @method('DELETE')
                            <button class="border border-red-700 text-red-700 rounded-lg px-3 py-2 text-sm min-h-[44px]">Revoke</button>
                        </form>
                    </div>
                @else
                    <span class="text-sm text-gray-400">No actions</span>
                @endif
            </article>
        @empty
            <p class="p-6 text-sm text-gray-500">No staff invitations yet.</p>
        @endforelse
        </div>
    </section>
</div>
@endsection
