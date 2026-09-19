<?php

namespace App\Services;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AdminRole;
use App\Support\SqliteBusy;
use App\Support\UniqueIndex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StaffManagementService
{
    public function __construct(
        private readonly StaffIdentityService $identities,
        private readonly StaffInvitationMailer $mailer,
    ) {}

    /**
     * Create a pending invitation, store only a token hash, and attempt email.
     *
     * On mail failure the one-time accept URL is returned in memory for this
     * response only. It is never persisted.
     *
     * @return array{invitation: StaffInvitation, email_sent: bool, accept_url: ?string}
     */
    public function invite(User $actor, string $name, string $email, string $role): array
    {
        $this->authorizeOwner($actor);
        $email = strtolower(trim($email));

        if (! AdminRole::isValid($role) || $role === AdminRole::OWNER) {
            throw ValidationException::withMessages(['admin_role' => 'Select an approved non-owner staff role.']);
        }

        $plainToken = Str::random(64);

        try {
            $invitation = SqliteBusy::retry(fn () => DB::transaction(function () use ($actor, $name, $email, $role, $plainToken): StaffInvitation {
                $existingUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->lockForUpdate()
                    ->first();

                if ($existingUser) {
                    throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
                }

                $existingInvitations = StaffInvitation::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($existingInvitations as $existing) {
                    if ($existing->isPending()) {
                        throw ValidationException::withMessages([
                            'email' => 'A pending invitation already exists for this email. Regenerate that link instead of creating another.',
                        ]);
                    }

                    if ($existing->pending_email !== null) {
                        $existing->forceFill(['pending_email' => null])->save();
                    }
                }

                return StaffInvitation::query()->create([
                    'name' => trim($name),
                    'email' => $email,
                    'pending_email' => $email,
                    'admin_role' => $role,
                    'token_hash' => hash('sha256', $plainToken),
                    'invited_by' => $actor->getKey(),
                    'expires_at' => now()->addHours(48),
                ]);
            }));
        } catch (QueryException $e) {
            if (UniqueIndex::isDuplicate($e, 'staff_inv_pending_email_uq', 'staff_invitations', 'pending_email')) {
                throw ValidationException::withMessages([
                    'email' => 'A pending invitation already exists for this email. Regenerate that link instead of creating another.',
                ]);
            }

            throw $e;
        }

        return $this->deliverInvitation($actor, $invitation, $plainToken, regenerated: false);
    }

    /**
     * Rotate the token and expiry, invalidating the previous link immediately.
     *
     * @return array{invitation: StaffInvitation, email_sent: bool, accept_url: ?string}
     */
    public function regenerateLink(User $actor, StaffInvitation $invitation): array
    {
        $this->authorizeOwner($actor);
        $plainToken = Str::random(64);

        $invitation = SqliteBusy::retry(fn () => DB::transaction(function () use ($invitation, $plainToken): StaffInvitation {
            /** @var StaffInvitation $locked */
            $locked = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if ($locked->accepted_at !== null || $locked->revoked_at !== null || $locked->expires_at?->isPast()) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be regenerated.']);
            }

            $locked->forceFill([
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(48),
                'pending_email' => strtolower((string) $locked->email),
            ])->save();

            return $locked;
        }));

        return $this->deliverInvitation($actor, $invitation, $plainToken, regenerated: true);
    }

    public function revokeInvitation(User $actor, StaffInvitation $invitation): void
    {
        $this->authorizeOwner($actor);

        DB::transaction(function () use ($invitation): void {
            /** @var StaffInvitation $locked */
            $locked = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if ($locked->accepted_at === null && $locked->revoked_at === null) {
                $locked->forceFill([
                    'revoked_at' => now(),
                    'pending_email' => null,
                ])->save();
            }
        });

        Log::info('admin.staff_invitation_revoked', [
            'actor_id' => $actor->getKey(),
            'invitation_id' => $invitation->getKey(),
        ]);
    }

    public function accept(StaffInvitation $invitation, string $plainToken, string $password): User
    {
        return DB::transaction(function () use ($invitation, $plainToken, $password): User {
            /** @var StaffInvitation $locked */
            $locked = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if (! $locked->isPending()
                || ! hash_equals($locked->token_hash, hash('sha256', $plainToken))) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or has expired.']);
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [strtolower($locked->email)])->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
            }

            $user = new User;
            $user->forceFill([
                'name' => $locked->name,
                'email' => $locked->email,
                'email_verified_at' => now(),
                'password' => $password,
                'is_admin' => true,
                'is_active' => true,
                'admin_role' => $locked->admin_role,
                'admin_session_version' => 1,
                'two_factor_grace_ends_at' => now(),
            ])->save();

            $this->identities->ensure($user);
            $locked->forceFill([
                'accepted_at' => now(),
                'pending_email' => null,
            ])->save();

            Log::info('admin.staff_invitation_accepted', [
                'invitation_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'staff_id' => $user->staff_id,
            ]);

            return $user;
        });
    }

    public function update(User $actor, User $staff, string $role, bool $active): void
    {
        $this->authorizeOwner($actor);

        if (! $staff->isAdmin() || ! AdminRole::isValid($role)) {
            throw ValidationException::withMessages(['admin_role' => 'This staff account or role is invalid.']);
        }

        DB::transaction(function () use ($actor, $staff, $role, $active): void {
            $activeOwnerIds = User::query()
                ->where('is_admin', true)
                ->where('is_active', true)
                ->where('admin_role', AdminRole::OWNER)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($staff->getKey());
            $removesActiveOwner = $locked->is_active
                && $locked->admin_role === AdminRole::OWNER
                && (! $active || $role !== AdminRole::OWNER);

            if ($removesActiveOwner && $activeOwnerIds->count() <= 1) {
                throw ValidationException::withMessages(['admin_role' => 'The last active Owner cannot be demoted or deactivated.']);
            }

            $changed = $locked->admin_role !== $role || (bool) $locked->is_active !== $active;
            $locked->forceFill([
                'admin_role' => $role,
                'is_active' => $active,
                'admin_session_version' => $changed
                    ? ((int) $locked->admin_session_version + 1)
                    : (int) $locked->admin_session_version,
            ])->save();
            $this->identities->ensure($locked);

            Log::info('admin.staff_updated', [
                'actor_id' => $actor->getKey(),
                'staff_user_id' => $locked->getKey(),
                'role' => $role,
                'active' => $active,
                'sessions_revoked' => $changed,
            ]);
        }, 3);
    }

    public function revokeSessions(User $actor, User $staff): void
    {
        $this->authorizeOwner($actor);

        if (! $staff->isAdmin()) {
            throw ValidationException::withMessages(['staff' => 'This is not a staff account.']);
        }

        User::query()->whereKey($staff->getKey())->increment('admin_session_version');

        Log::info('admin.staff_sessions_revoked', [
            'actor_id' => $actor->getKey(),
            'staff_user_id' => $staff->getKey(),
        ]);
    }

    /**
     * @return array{invitation: StaffInvitation, email_sent: bool, accept_url: ?string}
     */
    private function deliverInvitation(
        User $actor,
        StaffInvitation $invitation,
        string $plainToken,
        bool $regenerated,
    ): array {
        $acceptUrl = $this->makeAcceptUrl($invitation, $plainToken);
        $emailSent = $this->attemptInvitationEmail($invitation, $acceptUrl);

        Log::info($regenerated ? 'admin.staff_invitation_regenerated' : 'admin.staff_invited', [
            'actor_id' => $actor->getKey(),
            'invitation_id' => $invitation->getKey(),
            'email_sent' => $emailSent,
            'role' => $invitation->admin_role,
        ]);

        return [
            'invitation' => $invitation,
            'email_sent' => $emailSent,
            'accept_url' => $emailSent ? null : $acceptUrl,
        ];
    }

    private function attemptInvitationEmail(StaffInvitation $invitation, string $acceptUrl): bool
    {
        try {
            $this->mailer->send($invitation, $acceptUrl);

            return true;
        } catch (Throwable) {
            Log::warning('admin.staff_invitation_mail_failed', [
                'invitation_id' => $invitation->getKey(),
            ]);

            return false;
        }
    }

    private function makeAcceptUrl(StaffInvitation $invitation, string $plainToken): string
    {
        return URL::temporarySignedRoute(
            'admin.staff-invitations.accept',
            $invitation->expires_at,
            ['invitation' => $invitation->getKey(), 'token' => $plainToken],
        );
    }

    private function authorizeOwner(User $actor): void
    {
        if (! $actor->hasAdminPermission(AdminRole::STAFF_MANAGE)) {
            throw new AuthorizationException('Only an active Owner can manage staff.');
        }
    }
}
