<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_has_every_known_permission(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);

        $this->assertTrue($owner->isOwner());

        foreach (AdminRole::permissions() as $permission) {
            $this->assertTrue($owner->hasAdminPermission($permission), $permission);
        }
    }

    public function test_administrator_cannot_manage_staff_or_become_owner_implicitly(): void
    {
        $admin = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);

        $this->assertFalse($admin->isOwner());
        $this->assertTrue($admin->hasAdminPermission(AdminRole::STAFF_VIEW));
        $this->assertFalse($admin->hasAdminPermission(AdminRole::STAFF_MANAGE));
        $this->assertTrue($admin->hasAdminPermission(AdminRole::SETTINGS_MANAGE));
        $this->assertFalse($admin->hasAdminPermission(AdminRole::ORDERS_REFUND));
    }

    public function test_fixed_operational_roles_are_least_privilege(): void
    {
        $catalog = User::factory()->admin()->create(['admin_role' => AdminRole::CATALOG_MANAGER]);
        $sales = User::factory()->admin()->create(['admin_role' => AdminRole::SALES_MANAGER]);
        $orders = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);

        $this->assertTrue($catalog->hasAdminPermission(AdminRole::CATALOG_PUBLISH));
        $this->assertFalse($catalog->hasAdminPermission(AdminRole::ORDERS_VIEW));
        $this->assertTrue($sales->hasAdminPermission(AdminRole::ENQUIRIES_MANAGE));
        $this->assertFalse($sales->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        $this->assertTrue($orders->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        $this->assertFalse($orders->hasAdminPermission(AdminRole::CUSTOMERS_MANAGE));
        $this->assertFalse($orders->hasAdminPermission(AdminRole::ORDERS_REFUND));
    }

    public function test_customer_inactive_admin_and_unknown_role_fail_closed(): void
    {
        $customer = User::factory()->create();
        $inactive = User::factory()->admin()->disabled()->create(['admin_role' => AdminRole::OWNER]);
        $unknown = User::factory()->admin()->create(['admin_role' => 'invented-role']);

        foreach ([$customer, $inactive, $unknown] as $user) {
            $this->assertFalse($user->hasAdminPermission(AdminRole::DASHBOARD_VIEW));
            $this->assertFalse($user->hasAdminPermission(AdminRole::STAFF_MANAGE));
        }
    }

    public function test_legacy_admin_keeps_existing_module_access_but_cannot_access_staff(): void
    {
        $legacy = User::factory()->admin()->create(['admin_role' => null]);

        $this->assertFalse($legacy->isOwner());
        $this->assertSame('Legacy Admin', $legacy->adminRoleLabel());
        $this->assertTrue($legacy->hasAdminPermission(AdminRole::CATALOG_MANAGE));
        $this->assertTrue($legacy->hasAdminPermission(AdminRole::SETTINGS_MANAGE));
        $this->assertFalse($legacy->hasAdminPermission(AdminRole::STAFF_VIEW));
        $this->assertFalse($legacy->hasAdminPermission(AdminRole::STAFF_MANAGE));
        $this->assertFalse($legacy->hasAdminPermission(AdminRole::ORDERS_REFUND));
    }

    public function test_permission_matrix_matches_permissions_for_every_fixed_role(): void
    {
        $matrix = AdminRole::permissionMatrix();

        foreach (AdminRole::labels() as $role => $label) {
            $this->assertArrayHasKey($role, $matrix);
            $granted = array_keys(array_filter($matrix[$role]['permissions']));
            $expected = AdminRole::permissionsFor($role);
            sort($granted);
            sort($expected);
            $this->assertSame($expected, $granted, $role);
            $this->assertSame($label, $matrix[$role]['label']);
            $this->assertSame(AdminRole::descriptions()[$role], $matrix[$role]['description']);
        }

        $this->assertSame('owner', $matrix[AdminRole::OWNER]['group']);
        $this->assertSame('administrator', $matrix[AdminRole::ADMINISTRATOR]['group']);
        $this->assertSame('operational', $matrix[AdminRole::VIEWER]['group']);
        $this->assertSame(AdminRole::permissions(), array_keys($matrix[AdminRole::OWNER]['permissions']));
        $this->assertNotNull($matrix[AdminRole::OWNER]['locks'][AdminRole::STAFF_MANAGE]);
        $this->assertNotNull($matrix[AdminRole::VIEWER]['locks'][AdminRole::STAFF_MANAGE]);
        $this->assertNull($matrix[AdminRole::VIEWER]['locks'][AdminRole::ORDERS_VIEW]);
    }

    public function test_permission_groups_cover_every_known_permission_once(): void
    {
        $grouped = [];
        foreach (AdminRole::permissionGroups() as $permissions) {
            foreach ($permissions as $permission) {
                $grouped[] = $permission;
            }
        }

        $expected = AdminRole::permissions();
        sort($grouped);
        sort($expected);

        $this->assertSame($expected, $grouped);
        $this->assertArrayHasKey('Dashboard', AdminRole::permissionGroups());
        $this->assertArrayHasKey('Settings and security', AdminRole::permissionGroups());
        $this->assertArrayHasKey(AdminRole::STAFF_MANAGE, AdminRole::permissionExplanations());
    }
}
