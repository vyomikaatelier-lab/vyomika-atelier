<?php

namespace App\Support;

use App\Models\AdminRolePermissionOverride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of effective admin permissions.
 *
 * Code defaults in AdminRole::permissionsFor() remain the fallback when the
 * overrides table is genuinely absent (controlled mixed-deploy window).
 * Connection, query and schema-inspection failures propagate so a disabled
 * permission cannot be restored from defaults.
 *
 * No shared cache: revocation is visible on the next request. Per-request
 * memoization only avoids repeat queries inside one process.
 */
class AdminPermissionResolver
{
    /** @var array<string, bool>|null */
    private ?array $requestOverrides = null;

    /**
     * Owner permissions that cannot be turned off.
     *
     * @return list<string>
     */
    public static function ownerEssentialPermissions(): array
    {
        return [
            AdminRole::DASHBOARD_VIEW,
            AdminRole::SETTINGS_VIEW,
            AdminRole::SETTINGS_MANAGE,
            AdminRole::STAFF_VIEW,
            AdminRole::STAFF_MANAGE,
            AdminRole::SECURITY_MANAGE_SELF,
        ];
    }

    /**
     * Permissions that remain Owner-only even if an override row exists.
     *
     * @return list<string>
     */
    public static function ownerOnlyPermissions(): array
    {
        return [
            AdminRole::STAFF_MANAGE,
        ];
    }

    /** @return list<string> */
    public function permissionsFor(?string $role): array
    {
        if (! AdminRole::isValid($role)) {
            return [];
        }

        $granted = [];

        foreach (AdminRole::permissions() as $permission) {
            if ($this->isGranted($role, $permission)) {
                $granted[] = $permission;
            }
        }

        return $granted;
    }

    public function isGranted(string $role, string $permission): bool
    {
        if (! AdminRole::isValid($role) || ! in_array($permission, AdminRole::permissions(), true)) {
            return false;
        }

        if ($this->isForcedOn($role, $permission)) {
            return true;
        }

        if ($this->isForcedOff($role, $permission)) {
            return false;
        }

        $overrides = $this->overrides();

        if (array_key_exists($role.'.'.$permission, $overrides)) {
            return (bool) $overrides[$role.'.'.$permission];
        }

        return in_array($permission, AdminRole::permissionsFor($role), true);
    }

    public function isLocked(string $role, string $permission): bool
    {
        return $this->lockReason($role, $permission) !== null;
    }

    public function lockReason(string $role, string $permission): ?string
    {
        if (! AdminRole::isValid($role) || ! in_array($permission, AdminRole::permissions(), true)) {
            return 'Unknown role or permission.';
        }

        if ($role === AdminRole::OWNER && in_array($permission, self::ownerEssentialPermissions(), true)) {
            return 'Required for the Owner role and cannot be turned off.';
        }

        if (in_array($permission, self::ownerOnlyPermissions(), true) && $role !== AdminRole::OWNER) {
            return 'Only the Owner can manage staff, invitations and role access.';
        }

        return null;
    }

    public function isForcedOn(string $role, string $permission): bool
    {
        return $role === AdminRole::OWNER
            && in_array($permission, self::ownerEssentialPermissions(), true);
    }

    public function isForcedOff(string $role, string $permission): bool
    {
        return in_array($permission, self::ownerOnlyPermissions(), true)
            && $role !== AdminRole::OWNER;
    }

    /**
     * @return array<string, array{
     *     role: string,
     *     label: string,
     *     group: string,
     *     group_label: string,
     *     description: string,
     *     permissions: array<string, bool>,
     *     locks: array<string, ?string>
     * }>
     */
    public function permissionMatrix(): array
    {
        $matrix = [];

        foreach (AdminRole::labels() as $role => $label) {
            $permissions = [];
            $locks = [];

            foreach (AdminRole::permissions() as $permission) {
                $permissions[$permission] = $this->isGranted($role, $permission);
                $locks[$permission] = $this->lockReason($role, $permission);
            }

            $matrix[$role] = [
                'role' => $role,
                'label' => $label,
                'group' => AdminRole::group($role),
                'group_label' => AdminRole::groupLabel($role),
                'description' => AdminRole::descriptions()[$role],
                'permissions' => $permissions,
                'locks' => $locks,
            ];
        }

        return $matrix;
    }

    public function flush(): void
    {
        $this->requestOverrides = null;
    }

    /** @return array<string, bool> */
    private function overrides(): array
    {
        if ($this->requestOverrides !== null) {
            return $this->requestOverrides;
        }

        if (! $this->overridesTableExists()) {
            return $this->requestOverrides = [];
        }

        $map = [];

        foreach ($this->fetchOverrideRows() as $override) {
            if (! AdminRole::isValid($override->admin_role)
                || ! in_array($override->permission, AdminRole::permissions(), true)) {
                continue;
            }

            $map[$override->admin_role.'.'.$override->permission] = (bool) $override->enabled;
        }

        return $this->requestOverrides = $map;
    }

    protected function overridesTableExists(): bool
    {
        return Schema::hasTable('admin_role_permission_overrides');
    }

    /** @return Collection<int, AdminRolePermissionOverride> */
    protected function fetchOverrideRows(): Collection
    {
        return AdminRolePermissionOverride::query()->get();
    }
}
