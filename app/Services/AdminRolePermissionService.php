<?php

namespace App\Services;

use App\Models\AdminRolePermissionAudit;
use App\Models\AdminRolePermissionOverride;
use App\Models\User;
use App\Support\AdminPermissionResolver;
use App\Support\AdminRole;
use App\Support\SqliteBusy;
use App\Support\UniqueIndex;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminRolePermissionService
{
    public const ACCEPTED_ENABLED = ['0', '1'];

    public function __construct(private readonly AdminPermissionResolver $resolver) {}

    /**
     * Apply Owner-submitted permission values atomically.
     *
     * Accepted switch representation is the string (or integer/boolean equivalent)
     * 0 or 1. Missing keys are ignored and do not disable unsubmitted permissions.
     * Malformed values fail validation without writes, audits or session revocation.
     *
     * Concurrent first-time writes serialize on active Owner rows, which exist
     * before the sparse override. A unique-index collision retries as an update
     * of the existing row. The later committed transaction wins.
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

        return SqliteBusy::retry(fn () => DB::transaction(function () use ($actor, $changes): int {
            $this->lockPermissionWriters();

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
        }, 3));
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

        $default = in_array($permission, AdminRole::permissionsFor($role), true);

        /** @var AdminRolePermissionOverride|null $override */
        $override = AdminRolePermissionOverride::query()
            ->where('admin_role', $role)
            ->where('permission', $permission)
            ->lockForUpdate()
            ->first();

        $sync = $this->syncOverrideRow($override, $actor, $role, $permission, $enabled, $default);

        if (! $sync['changed'] || $sync['previous'] === $enabled) {
            return false;
        }

        AdminRolePermissionAudit::query()->create([
            'actor_user_id' => $actor->getKey(),
            'actor_staff_id' => $actor->staff_id,
            'admin_role' => $role,
            'permission' => $permission,
            'previous_enabled' => $sync['previous'],
            'new_enabled' => $enabled,
            'created_at' => now(),
        ]);

        Log::info('admin.role_permission_updated', [
            'actor_id' => $actor->getKey(),
            'role' => $role,
            'permission' => $permission,
            'previous' => $sync['previous'],
            'enabled' => $enabled,
        ]);

        return true;
    }

    /**
     * @return array{changed: bool, previous: bool}
     */
    private function syncOverrideRow(
        ?AdminRolePermissionOverride $override,
        User $actor,
        string $role,
        string $permission,
        bool $enabled,
        bool $default,
    ): array {
        $previous = $override !== null ? (bool) $override->enabled : $default;

        if ($enabled === $default) {
            if ($override === null) {
                return ['changed' => false, 'previous' => $previous];
            }

            $override->delete();

            return ['changed' => true, 'previous' => $previous];
        }

        if ($override) {
            if ((bool) $override->enabled === $enabled) {
                return ['changed' => false, 'previous' => $previous];
            }

            $override->forceFill([
                'enabled' => $enabled,
                'updated_by' => $actor->getKey(),
            ])->save();

            return ['changed' => true, 'previous' => $previous];
        }

        try {
            AdminRolePermissionOverride::query()->create([
                'admin_role' => $role,
                'permission' => $permission,
                'enabled' => $enabled,
                'updated_by' => $actor->getKey(),
            ]);

            return ['changed' => true, 'previous' => $previous];
        } catch (QueryException $exception) {
            if (! UniqueIndex::isDuplicate($exception, 'admin_rpo_role_perm_uq', 'admin_role_permission_overrides', 'permission')) {
                throw $exception;
            }

            /** @var AdminRolePermissionOverride $existing */
            $existing = AdminRolePermissionOverride::query()
                ->where('admin_role', $role)
                ->where('permission', $permission)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->syncOverrideRow($existing, $actor, $role, $permission, $enabled, $default);
        }
    }

    private function lockPermissionWriters(): void
    {
        User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('admin_role', AdminRole::OWNER)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
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
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        throw ValidationException::withMessages([
            'permissions' => 'Each permission switch must be 0 or 1.',
        ]);
    }

    private function authorizeOwner(User $actor): void
    {
        if (! $actor->isOwner() || ! $actor->is_active || ! $actor->hasAdminPermission(AdminRole::STAFF_MANAGE)) {
            throw new AuthorizationException('Only an active Owner can change role permissions.');
        }
    }
}
