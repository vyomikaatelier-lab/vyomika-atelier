@extends('layouts.admin')

@section('title', 'Staff & Roles')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <div>
        <p class="text-xs uppercase tracking-widest text-amber-700 mb-2">Administration</p>
        <h1 class="text-3xl font-semibold">Staff & Roles</h1>
        <p class="text-sm text-gray-600 mt-2">Invite staff, assign fixed least-privilege roles and revoke access immediately.</p>
    </div>

    @if(auth()->user()->hasAdminPermission(\App\Support\AdminRole::STAFF_MANAGE))
    <section class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold mb-4">Invite staff member</h2>
        <form method="POST" action="{{ route('admin.staff.invite') }}" class="grid md:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1" for="staff-name">Name</label>
                <input id="staff-name" name="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="staff-email">Email</label>
                <input id="staff-email" name="email" type="email" value="{{ old('email') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
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
            <button class="bg-gray-900 text-white rounded-lg px-4 py-3 font-medium">Send invitation</button>
        </form>
    </section>
    @endif

    <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b"><h2 class="text-xl font-semibold">Current staff</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left"><tr><th class="p-4">Staff</th><th class="p-4">Role</th><th class="p-4">Status</th><th class="p-4">Last login</th><th class="p-4">Actions</th></tr></thead>
                <tbody class="divide-y">
                @foreach($staff as $member)
                    <tr>
                        <td class="p-4"><strong>{{ $member->name }}</strong><br><span class="text-gray-500">{{ $member->email }} · {{ $member->staff_id ?: 'ID assigned on update' }}</span></td>
                        <td class="p-4">{{ $member->adminRoleLabel() }}</td>
                        <td class="p-4">{{ $member->is_active ? 'Active' : 'Disabled' }}</td>
                        <td class="p-4">{{ $member->admin_last_login_at?->format('d M Y H:i') ?? 'Not recorded' }}</td>
                        <td class="p-4 min-w-[320px]">
                            @if(auth()->user()->hasAdminPermission(\App\Support\AdminRole::STAFF_MANAGE))
                                <form method="POST" action="{{ route('admin.staff.update', $member) }}" class="flex flex-wrap gap-2 items-center mb-2">
                                    @csrf @method('PATCH')
                                    <select name="admin_role" class="border rounded px-2 py-2">
                                        @foreach($roles as $value => $label)<option value="{{ $value }}" @selected($member->admin_role === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                    <select name="is_active" class="border rounded px-2 py-2">
                                        <option value="1" @selected($member->is_active)>Active</option>
                                        <option value="0" @selected(! $member->is_active)>Disabled</option>
                                    </select>
                                    <button class="border border-gray-900 rounded px-3 py-2">Save access</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff.revoke-sessions', $member) }}">
                                    @csrf
                                    <button class="text-red-700 text-xs">Revoke all sessions</button>
                                </form>
                            @else
                                <span class="text-gray-500">View only</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b"><h2 class="text-xl font-semibold">Invitation history</h2></div>
        <div class="divide-y">
        @forelse($invitations as $invitation)
            <div class="p-4 flex flex-wrap justify-between gap-4">
                <div><strong>{{ $invitation->name }}</strong> · {{ $invitation->email }}<br><span class="text-xs text-gray-500">{{ $roles[$invitation->admin_role] ?? $invitation->admin_role }} · {{ $invitation->isPending() ? 'Pending' : ($invitation->accepted_at ? 'Accepted' : 'Expired or revoked') }}</span></div>
                @if($invitation->isPending() && auth()->user()->hasAdminPermission(\App\Support\AdminRole::STAFF_MANAGE))
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('admin.staff-invitations.resend', $invitation) }}">@csrf<button class="text-sm underline">Resend</button></form>
                        <form method="POST" action="{{ route('admin.staff-invitations.revoke', $invitation) }}">@csrf @method('DELETE')<button class="text-sm text-red-700 underline">Revoke</button></form>
                    </div>
                @endif
            </div>
        @empty
            <p class="p-6 text-sm text-gray-500">No staff invitations yet.</p>
        @endforelse
        </div>
    </section>
</div>
@endsection
