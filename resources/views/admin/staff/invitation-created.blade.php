@extends('layouts.admin-invitation-reveal')

@section('title', $regenerated ? 'Invitation link regenerated' : 'Invitation link created')

@section('content')
<p class="reveal-kicker">Administration</p>
<h1 class="reveal-title">{{ $regenerated ? 'Replacement invitation link' : 'Invitation link ready' }}</h1>
<p class="reveal-lead">
    Share this link through WhatsApp or another trusted channel. Email delivery is not used for invitations.
</p>

<div class="reveal-warning" role="status">
    This link is shown only once. Share it only with the intended staff member.
</div>

<section class="reveal-card">
    <h2>Invitation details</h2>
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

    <label class="reveal-label" for="invitation-url">One-time invitation URL</label>
    <input id="invitation-url" class="reveal-url" type="text" readonly value="{{ $acceptUrl }}"
        autocomplete="off" spellcheck="false" aria-describedby="invitation-copy-status">

    <div class="reveal-copy-row">
        <button type="button" id="copy-invitation-link" class="reveal-copy">
            Copy invitation link
        </button>
        <p id="invitation-copy-status" class="reveal-copy-status" aria-live="polite"></p>
    </div>
</section>

<a class="reveal-return" href="{{ route('admin.staff.index') }}">Return to Staff &amp; Roles</a>
@endsection
