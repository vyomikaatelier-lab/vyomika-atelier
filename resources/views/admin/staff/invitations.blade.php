@extends('layouts.admin')

@section('title', 'Staff Invitations')

@php
    $invitationRevealCssVer = @filemtime(public_path('css/admin-invitation-reveal.css')) ?: time();
    $invitationRevealJsVer = @filemtime(public_path('js/admin-invitation-reveal.js')) ?: time();
    $showingManualLink = !empty($manualInvitation);
@endphp

@section('content')
<div class="invitations-page max-w-6xl mx-auto space-y-8">
    <header class="invitations-heading flex flex-wrap items-end justify-between gap-4">
        <div class="invitations-heading-copy max-w-2xl">
            <p class="invitations-kicker text-xs uppercase tracking-widest text-amber-700 mb-2">Administration</p>
            <h1 class="invitations-title text-3xl font-semibold">Staff Invitations</h1>
            <p class="invitations-lead text-sm text-gray-600 mt-2">Send a secure invitation email. Pending and previous invitations stay on this page. If delivery fails, a one-time link is shown once so you can share it manually.</p>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="invitations-back border border-gray-300 rounded-lg px-4 py-3 font-medium min-h-[44px] inline-flex items-center">Back to Staff &amp; Roles</a>
    </header>

    <div @class(['secure-layout' => $showingManualLink, 'space-y-8' => ! $showingManualLink])>
        @if($showingManualLink)
        <aside class="fallback-panel" data-invitation-fallback-panel aria-labelledby="fallback-heading">
            <p class="fallback-status">{{ $regenerated ? 'Replacement link ready' : 'Email could not be delivered' }}</p>
            <h2 id="fallback-heading">Manual invitation link</h2>
            <p class="fallback-explain">The invitation email could not be delivered. Send this link to the intended staff member manually.</p>
            <div class="reveal-warning" role="status">This link is shown only once. Share it only with the intended staff member.</div>
            <dl class="reveal-details">
                <div>
                    <dt>Name</dt>
                    <dd>{{ $manualInvitation->name }}</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd>{{ $manualInvitation->email }}</dd>
                </div>
                <div>
                    <dt>Assigned role</dt>
                    <dd>{{ $roles[$manualInvitation->admin_role] ?? $manualInvitation->admin_role }}</dd>
                </div>
                <div>
                    <dt>Expires</dt>
                    <dd>{{ $manualInvitation->expires_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</dd>
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
                @if($manualInvitation->isPending())
                <form method="POST" action="{{ route('admin.staff.invite.legacy') }}">
                    @csrf
                    <input type="hidden" name="staff_invitation_action" value="regenerate">
                    <input type="hidden" name="invitation_id" value="{{ $manualInvitation->getKey() }}">
                    <button type="submit" class="reveal-secondary">Regenerate Link</button>
                </form>
                @endif
                <a class="reveal-close" href="{{ route('admin.staff.invitations.index') }}">Close</a>
            </div>
        </aside>
        @endif

        <div class="{{ $showingManualLink ? 'secure-main' : 'space-y-8' }}">
            <section class="invite-card bg-white border border-gray-200 rounded-xl p-6 shadow-sm max-w-3xl">
                <h2 class="invite-card-title text-xl font-semibold mb-2">Invite staff member</h2>
                <p class="invite-card-lead text-sm text-gray-600 mb-4">The invitation is emailed when delivery succeeds.</p>
                <form method="POST" action="{{ route('admin.staff.invite.legacy') }}" class="invite-form grid sm:grid-cols-2 gap-4">
                    @csrf
                    <div class="invite-field invite-field-wide sm:col-span-2">
                        <label class="invite-label block text-sm font-medium mb-1" for="staff-name">Name</label>
                        <input id="staff-name" name="name" value="{{ old('name') }}" required autocomplete="name" class="invite-control w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
                    </div>
                    <div class="invite-field">
                        <label class="invite-label block text-sm font-medium mb-1" for="staff-email">Email</label>
                        <input id="staff-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off" class="invite-control w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
                    </div>
                    <div class="invite-field">
                        <label class="invite-label block text-sm font-medium mb-1" for="staff-role">Role</label>
                        <select id="staff-role" name="admin_role" required class="invite-control w-full border border-gray-300 rounded-lg px-3 py-2 min-h-[44px]">
                            @foreach($roles as $value => $label)
                                @if($value !== \App\Support\AdminRole::OWNER)
                                    <option value="{{ $value }}" @selected(old('admin_role') === $value)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="invite-field invite-field-wide sm:col-span-2">
                        <button class="invite-submit bg-gray-900 text-white rounded-lg px-4 py-3 font-medium min-h-[44px]">Send invitation</button>
                    </div>
                </form>
            </section>

            <section class="invite-card history-card bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" aria-labelledby="invitation-history-heading">
                <div class="history-card-header p-6 border-b">
                    <h2 id="invitation-history-heading" class="invite-card-title text-xl font-semibold">Invitation history</h2>
                    <p class="invite-card-lead text-sm text-gray-600 mt-1">Pending and previous invitations.</p>
                </div>
                <div class="invitation-history-head hidden md:grid md:grid-cols-[minmax(0,1.6fr)_minmax(8rem,0.8fr)_minmax(7rem,0.7fr)_minmax(9rem,0.9fr)_auto] gap-3 px-4 py-3 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <span>Staff</span>
                    <span>Role</span>
                    <span>Status</span>
                    <span>Expires</span>
                    <span>Actions</span>
                </div>
                <div class="invitation-history-list divide-y">
                @forelse($invitations as $invitation)
                    <article class="invitation-row p-4 grid gap-3 md:grid-cols-[minmax(0,1.6fr)_minmax(8rem,0.8fr)_minmax(7rem,0.7fr)_minmax(9rem,0.9fr)_auto] md:items-center">
                        <div class="invitation-person min-w-0">
                            <strong class="invitation-name block">{{ $invitation->name }}</strong>
                            <span class="invitation-email block text-sm text-gray-500 break-all">{{ $invitation->email }}</span>
                        </div>
                        <div class="invitation-cell">
                            <span class="invitation-field-label md:hidden text-xs uppercase tracking-wide text-gray-500">Role</span>
                            <span class="invitation-cell-value block text-sm">{{ $roles[$invitation->admin_role] ?? $invitation->admin_role }}</span>
                        </div>
                        <div class="invitation-cell">
                            <span class="invitation-field-label md:hidden text-xs uppercase tracking-wide text-gray-500">Status</span>
                            <span class="invitation-cell-value block text-sm">{{ $invitation->statusLabel() }}</span>
                        </div>
                        <div class="invitation-cell">
                            <span class="invitation-field-label md:hidden text-xs uppercase tracking-wide text-gray-500">Expires</span>
                            <span class="invitation-cell-value block text-sm">{{ $invitation->expires_at?->timezone(config('app.timezone'))->format('d M Y, H:i') ?? 'n/a' }}</span>
                        </div>
                        @if($invitation->isPending())
                            <div class="invitation-actions flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.staff.invite.legacy') }}">
                                    @csrf
                                    <input type="hidden" name="staff_invitation_action" value="regenerate">
                                    <input type="hidden" name="invitation_id" value="{{ $invitation->getKey() }}">
                                    <button class="invitation-action border border-gray-900 rounded-lg px-3 py-2 text-sm min-h-[44px]">Regenerate Link</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff-invitations.revoke', $invitation) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="invitation-action invitation-action-danger border border-red-700 text-red-700 rounded-lg px-3 py-2 text-sm min-h-[44px]">Revoke</button>
                                </form>
                            </div>
                        @elseif($invitation->revoked_at !== null && $invitation->accepted_at === null)
                            <form class="invitation-actions" method="POST" action="{{ route('admin.staff-invitations.destroy', $invitation) }}" data-confirm="Permanently delete this revoked invitation from history?">
                                @csrf
                                @method('DELETE')
                                <button class="invitation-action invitation-action-danger border border-red-700 text-red-700 rounded-lg px-3 py-2 text-sm min-h-[44px]">Delete</button>
                            </form>
                        @else
                            <span class="invitation-idle text-sm text-gray-400">No actions</span>
                        @endif
                    </article>
                @empty
                    <p class="invitation-empty p-6 text-sm text-gray-500">No staff invitations yet.</p>
                @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('styles')
@if($showingManualLink)
<link rel="stylesheet" href="{{ asset('css/admin-invitation-reveal.css') }}?v={{ $invitationRevealCssVer }}">
@endif
@endpush

@push('scripts')
<script src="{{ asset('js/admin-invitation-reveal.js') }}?v={{ $invitationRevealJsVer }}" defer></script>
@endpush
