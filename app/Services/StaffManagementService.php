<?php

namespace App\Services;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffManagementService
{
    public function __construct(private readonly StaffIdentityService $identities) {}

    /**
     * Create a pending invitation and return a one-time acceptance URL.
     *
     * The plain token is never persisted. Default delivery is a manual secure
     * link shown once to the Owner — SMTP is not invoked.
     *
     * @return array{invitation: StaffInvitation, accept_url: string}
     */
    public function invite(User $actor, string $name, string $email, string $role): array
    {
        $this->authorizeOwner($actor);
        $email = strtolower(trim($email));

        if (! AdminRole::isValid($role) || $role === AdminRole::OWNER) {
            throw ValidationException::withMessages(['admin_role' => 'Select an approved non-owner staff role.']);
        }

        $plainToken = Str::random(64);
        $invitation = DB::transaction(function () use ($actor, $name, $email, $role, $plainToken): StaffInvitation {
            User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
            }

            $pendingExists = StaffInvitation::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'email' => 'A pending invitation already exists for this email. Regenerate that link instead of creating another.',
                ]);
            }

            return StaffInvitation::query()->create([
                'name' => trim($name),
                'email' => $email,
                'admin_role' => $role,
                'token_hash' => hash('sha256', $plainToken),
                'invited_by' => $actor->getKey(),
                'expires_at' => now()->addHours(48),
            ]);
        });

        $acceptUrl = $this->makeAcceptUrl($invitation, $plainToken);

        Log::info('admin.staff_invited', [
            'actor_id' => $actor->getKey(),
            'invitation_id' => $invitation->getKey(),
            'role' => $role,
        ]);

        return [
            'invitation' => $invitation,
            'accept_url' => $acceptUrl,
        ];
    }

    /**
     * Rotate the token and expiry, invalidating the previous link immediately.
     *
     * @return array{invitation: StaffInvitation, accept_url: string}
     */
    public function regenerateLink(User $actor, StaffInvitation $invitation): array
    {
        $this->authorizeOwner($actor);
        $plainToken = Str::random(64);
        $invitation = DB::transaction(function () use ($invitation, $plainToken): StaffInvitation {
            /** @var StaffInvitation $locked */
            $locked = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if ($locked->accepted_at !== null || $locked->revoked_at !== null || $locked->expires_at?->isPast()) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be regenerated.']);
            }

            $locked->forceFill([
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(48),
            ])->save();

            return $locked;
        });

        $acceptUrl = $this->makeAcceptUrl($invitation, $plainToken);

        Log::info('admin.staff_invitation_regenerated', [
            'actor_id' => $actor->getKey(),
            'invitation_id' => $invitation->getKey(),
        ]);

        return [
            'invitation' => $invitation,
            'accept_url' => $acceptUrl,
        ];
    }

    public function revokeInvitation(User $actor, StaffInvitation $invitation): void
    {
        $this->authorizeOwner($actor);

        if ($invitation->accepted_at === null && $invitation->revoked_at === null) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }

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
            $locked->forceFill(['accepted_at' => now()])->save();

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
