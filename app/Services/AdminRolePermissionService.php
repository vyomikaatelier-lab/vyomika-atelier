<?php

namespace App\Services;

use App\Models\AdminRolePermissionAudit;
use App\Models\AdminRolePermissionOverride;
use App\Models\User;
use App\Support\AdminPermissionResolver;
use App\Support\AdminRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminRolePermissionService
{
    public function __construct(private readonly AdminPermissionResolver $resolver) {}

    /**
     * Apply Owner-submitted permission values atomically.
     *
     * Unknown role/permission keys are ignored after catalog validation.
     * No-op values do not write audit rows or revoke sessions.
     *
     * @param  array<string, array<string, mixed>>  $submitted
     */
    public function update(User $actor, array $submitted): int
    {
        $this->authorizeOwner($actor);

        $changes = $this->normalizeSubmitted($submitted);

        if ($changes === []) {
            return 0;
        }

        return DB::transaction(function () use ($actor, $changes): int {
            $applied = 0;
            $affectedRoles = [];

            foreach ($changes as $change) {
                if ($this->applyChange($actor, $change['role'], $change['permission'], $change['enabled'])) {
                    $applied++;
                    $affectedRoles[$change['role']] = true;
                    $this->resolver->flush();
                }
            }

            foreach (array_keys($affectedRoles) as $role) {
                $this->revokeAffectedSessions($actor, $role);
            }

            if ($applied > 0) {
                $this->resolver->flush();
            }

            return $applied;
        }, 3);
    }

    /**
     * @return list<array{role: string, permission: string, enabled: bool}>
     */
    private function normalizeSubmitted(array $submitted): array
    {
        $changes = [];
        $roles = array_keys(AdminRole::labels());
        $permissions = AdminRole::permissions();

        foreach ($submitted as $role => $values) {
            if (! is_string($role) || ! in_array($role, $roles, true) || ! is_array($values)) {
                throw ValidationException::withMessages(['permissions' => 'A submitted role is not in the approved catalog.']);
            }

            foreach ($values as $permission => $enabled) {
                if (! is_string($permission) || ! in_array($permission, $permissions, true)) {
                    throw ValidationException::withMessages(['permissions' => 'A submitted permission is not in the approved catalog.']);
                }

                $changes[] = [
                    'role' => $role,
                    'permission' => $permission,
                    'enabled' => $this->toBoolean($enabled),
                ];
            }
        }

        return $changes;
    }

    private function applyChange(User $actor, string $role, string $permission, bool $enabled): bool
    {
        if ($this->resolver->isLocked($role, $permission)) {
            $forced = $this->resolver->isGranted($role, $permission);
            if ($enabled !== $forced) {
                throw ValidationException::withMessages([
                    'permissions' => $this->resolver->lockReason($role, $permission) ?? 'That permission cannot be changed.',
                ]);
            }

            return false;
        }

        $previous = $this->resolver->isGranted($role, $permission);
        $default = in_array($permission, AdminRole::permissionsFor($role), true);

        /** @var AdminRolePermissionOverride|null $override */
        $override = AdminRolePermissionOverride::query()
            ->where('admin_role', $role)
            ->where('permission', $permission)
            ->lockForUpdate()
            ->first();

        $currentStored = $override !== null ? (bool) $override->enabled : $default;
        $nextEffective = $enabled;

        if ($previous === $nextEffective && $currentStored === $nextEffective && (($override === null && $default === $nextEffective) || ($override !== null && (bool) $override->enabled === $nextEffective))) {
            return false;
        }

        if ($previous === $nextEffective) {
            $this->syncOverrideRow($override, $actor, $role, $permission, $enabled, $default);

            return false;
        }

        $this->syncOverrideRow($override, $actor, $role, $permission, $enabled, $default);

        AdminRolePermissionAudit::query()->create([
            'actor_user_id' => $actor->getKey(),
            'actor_staff_id' => $actor->staff_id,
            'admin_role' => $role,
            'permission' => $permission,
            'previous_enabled' => $previous,
            'new_enabled' => $nextEffective,
            'created_at' => now(),
        ]);

        Log::info('admin.role_permission_updated', [
            'actor_id' => $actor->getKey(),
            'role' => $role,
            'permission' => $permission,
            'previous' => $previous,
            'enabled' => $nextEffective,
        ]);

        return true;
    }

    private function syncOverrideRow(
        ?AdminRolePermissionOverride $override,
        User $actor,
        string $role,
        string $permission,
        bool $enabled,
        bool $default,
    ): void {
        if ($enabled === $default) {
            $override?->delete();

            return;
        }

        if ($override) {
            $override->forceFill([
                'enabled' => $enabled,
                'updated_by' => $actor->getKey(),
            ])->save();

            return;
        }

        AdminRolePermissionOverride::query()->create([
            'admin_role' => $role,
            'permission' => $permission,
            'enabled' => $enabled,
            'updated_by' => $actor->getKey(),
        ]);
    }

    private function revokeAffectedSessions(User $actor, string $role): void
    {
        User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('admin_role', $role)
            ->whereKeyNot($actor->getKey())
            ->lockForUpdate()
            ->increment('admin_session_version');
    }

    private function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function authorizeOwner(User $actor): void
    {
        if (! $actor->isOwner() || ! $actor->is_active || ! $actor->hasAdminPermission(AdminRole::STAFF_MANAGE)) {
            throw new AuthorizationException('Only an active Owner can change role permissions.');
        }
    }
}
