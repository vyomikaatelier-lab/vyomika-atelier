@extends('layouts.admin')

@section('title', 'Staff & Roles')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-staff-roles.css') }}">
@endpush

@section('content')
@php
    $selectedDefinition = $roleMatrix[$selectedRole];
@endphp
<div class="max-w-6xl mx-auto space-y-8">
    <div>
        <p class="text-xs uppercase tracking-widest text-amber-700 mb-2">Administration</p>
        <h1 class="text-3xl font-semibold">Staff & Roles</h1>
        <p class="text-sm text-gray-600 mt-2">Invite staff with a one-time secure link, assign fixed least-privilege roles and revoke access immediately.</p>
    </div>

    @if(auth()->user()->hasAdminPermission(\App\Support\AdminRole::STAFF_MANAGE))
    <section class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold mb-2">Invite staff member</h2>
        <p class="text-sm text-gray-600 mb-4">Send a secure invitation email. If delivery fails, a one-time link is shown once so you can share it manually.</p>
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
            <button class="bg-gray-900 text-white rounded-lg px-4 py-3 font-medium min-h-[44px]">Send invitation</button>
        </form>
    </section>
    @endif

    <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" aria-labelledby="role-editor-heading">
        <div class="p-6 border-b space-y-2">
            <h2 id="role-editor-heading" class="text-xl font-semibold">Roles and permissions</h2>
            <p class="text-sm text-gray-600">Choose a role to review or change its permissions. Owner-only and Owner-essential permissions stay locked.</p>
            @if($canEditRolePermissions)
                <p class="text-sm text-gray-600">Saving a change immediately updates access and signs out other active staff in that role.</p>
            @endif
        </div>
        <div class="staff-role-editor p-6 grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]" data-staff-role-editor data-selected-role="{{ $selectedRole }}">
            <form method="GET" action="{{ route('admin.staff.index') }}" class="lg:hidden">
                <label class="block text-sm font-medium mb-1" for="staff-role-picker">Role</label>
                <select id="staff-role-picker" name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]" data-role-picker>
                    @foreach($roleMatrix as $role => $definition)
                        <option value="{{ $role }}" @selected($role === $selectedRole)>{{ $definition['label'] }}</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="mt-2 border border-gray-900 rounded-lg px-4 py-2 min-h-[44px]">Show role</button>
                </noscript>
            </form>

            <nav class="hidden lg:block" aria-label="Roles">
                <ul class="staff-role-list">
                    @foreach($roleMatrix as $role => $definition)
                        <li>
                            <a
                                href="{{ route('admin.staff.index', ['role' => $role]) }}"
                                class="staff-role-item{{ $role === $selectedRole ? ' is-selected' : '' }}"
                                data-role-link="{{ $role }}"
                                @if($role === $selectedRole) aria-current="page" @endif
                            >
                                <span class="staff-role-item-label">{{ $definition['label'] }}</span>
                                <span class="staff-role-badge">{{ $definition['group_label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div
                class="staff-role-panel min-w-0"
                data-permission-panel="{{ $selectedRole }}"
                data-selected="true"
                aria-labelledby="selected-role-heading"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
                    <div>
                        <h3 id="selected-role-heading" class="text-lg font-semibold">{{ $selectedDefinition['label'] }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ $selectedDefinition['description'] }}</p>
                    </div>
                    <span class="staff-role-badge">{{ $selectedDefinition['group_label'] }}</span>
                </div>
                <p class="text-sm text-gray-500 mb-4" data-unsaved-status hidden>You have unsaved permission changes for this role.</p>

                @if($canEditRolePermissions)
                <form method="POST" action="{{ route('admin.staff.role-permissions.update') }}" data-role-permission-form>
                    @csrf
                    @method('PUT')
                @endif

                @foreach($permissionGroups as $groupLabel => $groupPermissions)
                    <section class="staff-permission-group" aria-labelledby="permission-group-{{ \Illuminate\Support\Str::slug($groupLabel) }}">
                        <h4 id="permission-group-{{ \Illuminate\Support\Str::slug($groupLabel) }}" class="staff-permission-group-title">{{ $groupLabel }}</h4>
                        <ul class="staff-permission-list">
                            @foreach($groupPermissions as $permission)
                                @php
                                    $granted = $selectedDefinition['permissions'][$permission] ?? false;
                                    $lockReason = $selectedDefinition['locks'][$permission] ?? null;
                                    $editable = $canEditRolePermissions && $lockReason === null;
                                    $state = $granted ? 'on' : 'off';
                                    $label = $permissionLabels[$permission] ?? $permission;
                                    $explanation = $permissionExplanations[$permission] ?? null;
                                    $accessibleName = $selectedDefinition['label'].': '.$label.', '.$state.($lockReason ? ', locked. '.$lockReason : '');
                                @endphp
                                <li class="staff-permission-row">
                                    <div class="staff-permission-copy">
                                        <div class="staff-permission-heading">
                                            <span class="staff-permission-label">{{ $label }}</span>
                                            @if($lockReason)
                                                <span class="staff-permission-lock" title="{{ $lockReason }}">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                                                        <path fill="currentColor" fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm-2 6V5a2 2 0 114 0v2H8zm2 3a1 1 0 00-.894.553l-1 2A1 1 0 009 14h2a1 1 0 00.894-1.447l-1-2A1 1 0 0010 10z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span class="sr-only">Locked</span>
                                                </span>
                                            @endif
                                        </div>
                                        @if($explanation)
                                            <p class="staff-permission-help">{{ $explanation }}</p>
                                        @endif
                                        @if($lockReason)
                                            <p class="staff-permission-help">{{ $lockReason }}</p>
                                        @endif
                                    </div>
                                    <label class="staff-switch">
                                        @if($editable)
                                            <input type="hidden" name="permissions[{{ $selectedRole }}][{{ $permission }}]" value="0">
                                            <input
                                                type="checkbox"
                                                role="switch"
                                                class="staff-switch-input"
                                                name="permissions[{{ $selectedRole }}][{{ $permission }}]"
                                                value="1"
                                                @checked($granted)
                                                aria-checked="{{ $granted ? 'true' : 'false' }}"
                                                aria-label="{{ $accessibleName }}"
                                            >
                                        @else
                                            <input
                                                type="checkbox"
                                                role="switch"
                                                class="staff-switch-input"
                                                @checked($granted)
                                                disabled
                                                aria-disabled="true"
                                                aria-checked="{{ $granted ? 'true' : 'false' }}"
                                                aria-label="{{ $accessibleName }}"
                                                @if($lockReason) title="{{ $lockReason }}" @endif
                                            >
                                        @endif
                                        <span class="staff-switch-track" aria-hidden="true"><span class="staff-switch-thumb"></span></span>
                                        <span class="staff-switch-state">{{ $granted ? 'On' : 'Off' }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach

                @if($canEditRolePermissions)
                    <div class="staff-permission-actions">
                        <button type="submit" class="bg-gray-900 text-white rounded-lg px-4 py-3 font-medium min-h-[44px]">Save changes</button>
                        <button type="reset" class="border border-gray-900 rounded-lg px-4 py-3 font-medium min-h-[44px]" data-reset-permissions>Reset</button>
                        <a href="{{ route('admin.staff.index', ['role' => $selectedRole]) }}" class="border border-gray-300 rounded-lg px-4 py-3 font-medium min-h-[44px] inline-flex items-center" data-cancel-permissions>Cancel</a>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </section>

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
                <div>
                    <strong>{{ $invitation->name }}</strong> · {{ $invitation->email }}<br>
                    <span class="text-xs text-gray-500">
                        {{ $roles[$invitation->admin_role] ?? $invitation->admin_role }}
                        · {{ $invitation->statusLabel() }}
                        · Expires {{ $invitation->expires_at?->timezone(config('app.timezone'))->format('d M Y, H:i') ?? 'n/a' }}
                    </span>
                </div>
                @if($invitation->isPending() && auth()->user()->hasAdminPermission(\App\Support\AdminRole::STAFF_MANAGE))
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('admin.staff-invitations.resend', $invitation) }}">@csrf<button class="text-sm underline">Regenerate Link</button></form>
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

@push('scripts')
<script src="{{ asset('js/admin-staff-roles.js') }}" defer></script>
@endpush
