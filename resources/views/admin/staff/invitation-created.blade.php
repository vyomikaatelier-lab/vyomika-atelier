@extends('layouts.admin-invitation-reveal')

@section('title', $regenerated ? 'Replacement invitation link' : 'Staff Invitations')

@section('content')
<header class="secure-header">
    <p class="reveal-kicker">Administration</p>
    <h1 class="reveal-title">Staff Invitations</h1>
    <p class="reveal-lead">Send a secure invitation email. Pending and previous invitations stay on this page. If delivery fails, a one-time link is shown once so you can share it manually.</p>
    <a class="reveal-return" href="{{ route('admin.staff.index') }}">Back to Staff &amp; Roles</a>
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
                <form method="POST" action="{{ route('admin.staff.invite.legacy') }}">
                    @csrf
                    <input type="hidden" name="staff_invitation_action" value="regenerate">
                    <input type="hidden" name="invitation_id" value="{{ $invitation->getKey() }}">
                    <button type="submit" class="reveal-secondary">Regenerate Link</button>
                </form>
            @endif
            <a class="reveal-close" href="{{ route('admin.staff.invitations.index') }}">Close</a>
        </div>
    </aside>

    <div class="secure-main">
        <section class="reveal-card">
            <h2>Invite staff member</h2>
            <p class="secure-muted">A secure invitation is emailed when delivery succeeds. If email fails, a one-time link is shown in the panel.</p>
            <form method="POST" action="{{ route('admin.staff.invite.legacy') }}" class="secure-invite-form">
                @csrf
                <div>
                    <label class="reveal-label" for="staff-name">Name</label>
                    <input id="staff-name" name="name" required class="reveal-url" value="{{ old('name') }}" autocomplete="name">
                </div>
                <div>
                    <label class="reveal-label" for="staff-email">Email</label>
                    <input id="staff-email" name="email" type="email" required class="reveal-url" value="{{ old('email') }}" autocomplete="off">
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

        <section class="reveal-card">
            <h2>Invitation history</h2>
            <p class="secure-muted">Pending and previous invitations.</p>
            <ul class="secure-list">
                @forelse($invitations as $historyInvitation)
                    <li>
                        <strong>{{ $historyInvitation->name }}</strong>
                        <span class="secure-muted">
                            {{ $historyInvitation->email }}
                            · {{ $roles[$historyInvitation->admin_role] ?? $historyInvitation->admin_role }}
                            · {{ $historyInvitation->statusLabel() }}
                            · Expires {{ $historyInvitation->expires_at?->timezone(config('app.timezone'))->format('d M Y, H:i') ?? 'n/a' }}
                        </span>
                        @if($historyInvitation->isPending())
                            <div class="reveal-copy-row">
                                <form method="POST" action="{{ route('admin.staff.invite.legacy') }}">
                                    @csrf
                                    <input type="hidden" name="staff_invitation_action" value="regenerate">
                                    <input type="hidden" name="invitation_id" value="{{ $historyInvitation->getKey() }}">
                                    <button type="submit" class="reveal-secondary">Regenerate Link</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff-invitations.revoke', $historyInvitation) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="reveal-secondary">Revoke</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="secure-muted">No staff invitations yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
