<?php

namespace App\Support;

use App\Models\User;

final class AdminRole
{
    public const OWNER = 'owner';

    public const ADMINISTRATOR = 'administrator';

    public const CATALOG_MANAGER = 'catalog_manager';

    public const CONTENT_EDITOR = 'content_editor';

    public const SALES_MANAGER = 'sales_manager';

    public const ORDER_MANAGER = 'order_manager';

    public const VIEWER = 'viewer';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const CATALOG_VIEW = 'catalog.view';

    public const CATALOG_MANAGE = 'catalog.manage';

    public const CATALOG_PUBLISH = 'catalog.publish';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_MANAGE = 'orders.manage';

    public const ENQUIRIES_VIEW = 'enquiries.view';

    public const ENQUIRIES_MANAGE = 'enquiries.manage';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_MANAGE = 'customers.manage';

    public const CONTENT_VIEW = 'content.view';

    public const CONTENT_MANAGE = 'content.manage';

    public const CONTENT_PUBLISH = 'content.publish';

    public const MEDIA_VIEW = 'media.view';

    public const MEDIA_MANAGE = 'media.manage';

    public const SEO_VIEW = 'seo.view';

    public const SEO_MANAGE = 'seo.manage';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const STAFF_VIEW = 'staff.view';

    public const STAFF_MANAGE = 'staff.manage';

    public const SECURITY_MANAGE_SELF = 'security.manage-self';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::OWNER => 'Owner',
            self::ADMINISTRATOR => 'Administrator',
            self::CATALOG_MANAGER => 'Catalog Manager',
            self::CONTENT_EDITOR => 'Content Editor',
            self::SALES_MANAGER => 'Sales & Leads Manager',
            self::ORDER_MANAGER => 'Order Manager',
            self::VIEWER => 'Viewer',
        ];
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        return [
            self::DASHBOARD_VIEW,
            self::CATALOG_VIEW,
            self::CATALOG_MANAGE,
            self::CATALOG_PUBLISH,
            self::ORDERS_VIEW,
            self::ORDERS_MANAGE,
            self::ENQUIRIES_VIEW,
            self::ENQUIRIES_MANAGE,
            self::CUSTOMERS_VIEW,
            self::CUSTOMERS_MANAGE,
            self::CONTENT_VIEW,
            self::CONTENT_MANAGE,
            self::CONTENT_PUBLISH,
            self::MEDIA_VIEW,
            self::MEDIA_MANAGE,
            self::SEO_VIEW,
            self::SEO_MANAGE,
            self::SETTINGS_VIEW,
            self::SETTINGS_MANAGE,
            self::STAFF_VIEW,
            self::STAFF_MANAGE,
            self::SECURITY_MANAGE_SELF,
        ];
    }

    /** @return list<string> */
    public static function permissionsFor(?string $role): array
    {
        return match ($role) {
            self::OWNER => self::permissions(),
            self::ADMINISTRATOR => self::without(self::STAFF_MANAGE),
            self::CATALOG_MANAGER => [
                self::DASHBOARD_VIEW,
                self::CATALOG_VIEW,
                self::CATALOG_MANAGE,
                self::CATALOG_PUBLISH,
                self::MEDIA_VIEW,
                self::MEDIA_MANAGE,
                self::SEO_VIEW,
                self::SECURITY_MANAGE_SELF,
            ],
            self::CONTENT_EDITOR => [
                self::DASHBOARD_VIEW,
                self::CONTENT_VIEW,
                self::CONTENT_MANAGE,
                self::CONTENT_PUBLISH,
                self::MEDIA_VIEW,
                self::MEDIA_MANAGE,
                self::SEO_VIEW,
                self::SEO_MANAGE,
                self::SECURITY_MANAGE_SELF,
            ],
            self::SALES_MANAGER => [
                self::DASHBOARD_VIEW,
                self::ORDERS_VIEW,
                self::ENQUIRIES_VIEW,
                self::ENQUIRIES_MANAGE,
                self::CUSTOMERS_VIEW,
                self::CUSTOMERS_MANAGE,
                self::SECURITY_MANAGE_SELF,
            ],
            self::ORDER_MANAGER => [
                self::DASHBOARD_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_MANAGE,
                self::CUSTOMERS_VIEW,
                self::SECURITY_MANAGE_SELF,
            ],
            self::VIEWER => [
                self::DASHBOARD_VIEW,
                self::CATALOG_VIEW,
                self::ORDERS_VIEW,
                self::ENQUIRIES_VIEW,
                self::CUSTOMERS_VIEW,
                self::CONTENT_VIEW,
                self::MEDIA_VIEW,
                self::SEO_VIEW,
                self::SETTINGS_VIEW,
                self::SECURITY_MANAGE_SELF,
            ],
            default => [],
        };
    }

    public static function isValid(?string $role): bool
    {
        return $role !== null && array_key_exists($role, self::labels());
    }

    public static function hasPermission(User $user, string $permission): bool
    {
        if (! $user->isAdmin() || ! $user->is_active || ! in_array($permission, self::permissions(), true)) {
            return false;
        }

        if ($user->admin_role === null) {
            return in_array($permission, self::legacyPermissions(), true);
        }

        return in_array($permission, self::permissionsFor($user->admin_role), true);
    }

    /**
     * Existing production admins keep access to existing modules until an Owner
     * is explicitly assigned. Legacy accounts can never manage staff.
     *
     * @return list<string>
     */
    private static function legacyPermissions(): array
    {
        return self::without(self::STAFF_VIEW, self::STAFF_MANAGE);
    }

    /** @return list<string> */
    private static function without(string ...$permissions): array
    {
        return array_values(array_diff(self::permissions(), $permissions));
    }
}
