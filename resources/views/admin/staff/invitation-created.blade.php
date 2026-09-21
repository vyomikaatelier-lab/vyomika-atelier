@extends('layouts.admin-invitation-reveal')

@section('title', $regenerated ? 'Replacement invitation link' : 'Staff & Roles')

@section('content')
<header class="secure-header">
    <p class="reveal-kicker">Administration</p>
    <h1 class="reveal-title">Staff &amp; Roles</h1>
    <p class="reveal-lead">Invite staff with a one-time secure link, assign fixed least-privilege roles and revoke access immediately.</p>
</header>

<div class="secure-layout">
    <aside class="fallback-panel" data-invitation-fallback-panel aria-labelledby="fallback-heading">
        <p class="fallback-status">{{ $regenerated ? 'Replacement link ready' : 'Email could not be delivered' }}</p>
        <h2 id="fallback-heading">Manual invitation link</h2>
        <p class="fallback-explain">
            The invitation email could not be delivered. Send this link to the intended staff member manually.
        </p>

        <div class="reveal-warning" role="status">
            This link is shown only once. Share it only with the intended staff member.
        </div>

        <dl class="reveal-details">
            <div>
                <dt>Name</dt>
                <dd>{{ $invitation->name }}</dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd>{{ $invitation->email }}</dd>
            </div>
            <div>
                <dt>Assigned role</dt>
                <dd>{{ $roles[$invitation->admin_role] ?? $invitation->admin_role }}</dd>
            </div>
            <div>
                <dt>Expires</dt>
                <dd>{{ $invitation->expires_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</dd>
            </div>
        </dl>

        <label class="reveal-label" for="invitation-url">Invitation URL</label>
        <input id="invitation-url" class="reveal-url" type="text" readonly value="{{ $acceptUrl }}"
            autocomplete="off" spellcheck="false" aria-describedby="invitation-copy-status">

        <div class="reveal-copy-row">
            <button type="button" id="copy-invitation-link" class="reveal-copy">Copy Link</button>
            <p id="invitation-copy-status" class="reveal-copy-status" aria-live="polite"></p>
        </div>

        <div class="fallback-actions">
            @if($invitation->isPending())
                <form method="POST" action="{{ route('admin.staff-invitations.resend', $invitation) }}">
                    @csrf
                    <button type="submit" class="reveal-secondary">Regenerate Link</button>
                </form>
            @endif
            <a class="reveal-close" href="{{ route('admin.staff.index') }}">Close</a>
        </div>
    </aside>

    <div class="secure-main">
        <section class="reveal-card">
            <h2>Invite staff member</h2>
            <p class="secure-muted">A secure invitation is emailed when delivery succeeds. If email fails, a one-time link is shown in the panel.</p>
            <form method="POST" action="{{ route('admin.staff.invite') }}" class="secure-invite-form">
                @csrf
                <div>
                    <label class="reveal-label" for="staff-name">Name</label>
                    <input id="staff-name" name="name" required class="reveal-url" value="{{ old('name') }}">
                </div>
                <div>
                    <label class="reveal-label" for="staff-email">Email</label>
                    <input id="staff-email" name="email" type="email" required class="reveal-url" value="{{ old('email') }}">
                </div>
                <div>
                    <label class="reveal-label" for="staff-role">Role</label>
                    <select id="staff-role" name="admin_role" required class="reveal-url">
                        @foreach($roles as $value => $label)
                            @if($value !== \App\Support\AdminRole::OWNER)
                                <option value="{{ $value }}" @selected(old('admin_role') === $value)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="reveal-copy">Send invitation</button>
            </form>
        </section>

        <section class="reveal-card" aria-labelledby="secure-roles-heading">
            <h2 id="secure-roles-heading">Roles and permissions</h2>
            <p class="secure-muted">Choose a role on Staff &amp; Roles to review its permissions. This page shows the same roles without the live editor.</p>
            <ul class="secure-role-list">
                @foreach($roleMatrix as $role => $definition)
                    <li class="secure-role-item{{ $role === $selectedRole ? ' is-selected' : '' }}">
                        <span>
                            <strong>{{ $definition['label'] }}</strong>
                            <span class="secure-role-badge">{{ $definition['group_label'] }}</span>
                        </span>
                        @if($role === $selectedRole)
                            <span class="secure-muted">{{ $definition['description'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="reveal-card">
            <h2>Current staff</h2>
            <ul class="secure-list">
                @forelse($staff as $member)
                    <li>
                        <strong>{{ $member->name }}</strong>
                        <span class="secure-muted">{{ $member->email }} · {{ $member->adminRoleLabel() }} · {{ $member->is_active ? 'Active' : 'Disabled' }}</span>
                    </li>
                @empty
                    <li class="secure-muted">No staff accounts yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="reveal-card">
            <h2>Invitation history</h2>
            <ul class="secure-list">
                @forelse($invitations as $historyInvitation)
                    <li>
                        <strong>{{ $historyInvitation->name }}</strong>
                        <span class="secure-muted">
                            {{ $historyInvitation->email }}
                            · {{ $roles[$historyInvitation->admin_role] ?? $historyInvitation->admin_role }}
                            · {{ $historyInvitation->statusLabel() }}
                        </span>
                    </li>
                @empty
                    <li class="secure-muted">No staff invitations yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
