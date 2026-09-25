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

    public const ORDERS_REFUND = 'orders.refund';

    public const ORDERS_DELETE_TEST = 'orders.delete_test';

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

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return [
            self::OWNER => 'Full control of the administration system, including staff access, roles and security.',
            self::ADMINISTRATOR => 'Day-to-day administration of store, content and settings. Cannot invite staff or change staff access.',
            self::CATALOG_MANAGER => 'Manages products, categories, media and related catalogue SEO.',
            self::CONTENT_EDITOR => 'Manages projects, editorial pages, media and content SEO.',
            self::SALES_MANAGER => 'Handles enquiries, leads and customer records.',
            self::ORDER_MANAGER => 'Processes orders and can view related customer information.',
            self::VIEWER => 'Read-only access to operational modules. Cannot change records or manage staff.',
        ];
    }

    /** @return array<string, string> */
    public static function permissionLabels(): array
    {
        return [
            self::DASHBOARD_VIEW => 'View dashboard',
            self::CATALOG_VIEW => 'View catalogue',
            self::CATALOG_MANAGE => 'Manage catalogue',
            self::CATALOG_PUBLISH => 'Publish catalogue',
            self::ORDERS_VIEW => 'View orders',
            self::ORDERS_MANAGE => 'Manage orders',
            self::ORDERS_REFUND => 'Refund orders',
            self::ORDERS_DELETE_TEST => 'Delete inert test orders',
            self::ENQUIRIES_VIEW => 'View enquiries',
            self::ENQUIRIES_MANAGE => 'Manage enquiries',
            self::CUSTOMERS_VIEW => 'View customers',
            self::CUSTOMERS_MANAGE => 'Manage customers',
            self::CONTENT_VIEW => 'View content',
            self::CONTENT_MANAGE => 'Manage content',
            self::CONTENT_PUBLISH => 'Publish content',
            self::MEDIA_VIEW => 'View media',
            self::MEDIA_MANAGE => 'Manage media',
            self::SEO_VIEW => 'View SEO',
            self::SEO_MANAGE => 'Manage SEO',
            self::SETTINGS_VIEW => 'View settings',
            self::SETTINGS_MANAGE => 'Manage settings',
            self::STAFF_VIEW => 'View staff & roles',
            self::STAFF_MANAGE => 'Manage staff & roles',
            self::SECURITY_MANAGE_SELF => 'Manage own MFA and passkeys',
        ];
    }

    /**
     * Display groups for the Staff & Roles editor. This does not change
     * authorization; effective grants still come from the resolver.
     *
     * @return array<string, list<string>>
     */
    public static function permissionGroups(): array
    {
        return [
            'Dashboard' => [
                self::DASHBOARD_VIEW,
            ],
            'Catalog' => [
                self::CATALOG_VIEW,
                self::CATALOG_MANAGE,
                self::CATALOG_PUBLISH,
            ],
            'Orders' => [
                self::ORDERS_VIEW,
                self::ORDERS_MANAGE,
                self::ORDERS_REFUND,
                self::ORDERS_DELETE_TEST,
            ],
            'Customers' => [
                self::CUSTOMERS_VIEW,
                self::CUSTOMERS_MANAGE,
            ],
            'Content and media' => [
                self::CONTENT_VIEW,
                self::CONTENT_MANAGE,
                self::CONTENT_PUBLISH,
                self::MEDIA_VIEW,
                self::MEDIA_MANAGE,
                self::SEO_VIEW,
                self::SEO_MANAGE,
            ],
            'Leads and enquiries' => [
                self::ENQUIRIES_VIEW,
                self::ENQUIRIES_MANAGE,
            ],
            'Settings and security' => [
                self::SETTINGS_VIEW,
                self::SETTINGS_MANAGE,
                self::STAFF_VIEW,
                self::STAFF_MANAGE,
                self::SECURITY_MANAGE_SELF,
            ],
        ];
    }

    /**
     * Optional short explanations for the compact permission editor.
     *
     * @return array<string, string>
     */
    public static function permissionExplanations(): array
    {
        return [
            self::DASHBOARD_VIEW => 'Open the administration dashboard.',
            self::CATALOG_MANAGE => 'Create and edit products and categories.',
            self::CATALOG_PUBLISH => 'Make catalogue changes visible on the storefront.',
            self::ORDERS_MANAGE => 'Update order status and fulfilment.',
            self::ORDERS_REFUND => 'Refund a captured payment or cancel a paid order.',
            self::ORDERS_DELETE_TEST => 'Delete a pending or cancelled order that has no payment, gateway, refund, reconciliation, or stock evidence.',
            self::CUSTOMERS_MANAGE => 'Edit customer records.',
            self::CONTENT_MANAGE => 'Create and edit projects, pages and posts.',
            self::CONTENT_PUBLISH => 'Publish editorial content.',
            self::MEDIA_MANAGE => 'Upload and organise media.',
            self::SEO_MANAGE => 'Edit search metadata.',
            self::ENQUIRIES_MANAGE => 'Respond to and close leads and enquiries.',
            self::SETTINGS_MANAGE => 'Change site settings.',
            self::STAFF_VIEW => 'Open the Staff & Roles page.',
            self::STAFF_MANAGE => 'Invite staff and change role access.',
            self::SECURITY_MANAGE_SELF => 'Update this account\'s MFA and passkeys.',
        ];
    }

    public static function group(string $role): string
    {
        return match ($role) {
            self::OWNER => 'owner',
            self::ADMINISTRATOR => 'administrator',
            default => 'operational',
        };
    }

    public static function groupLabel(string $role): string
    {
        return match (self::group($role)) {
            'owner' => 'Owner',
            'administrator' => 'Administrator',
            default => 'Operational',
        };
    }

    /**
     * Effective permission matrix from AdminPermissionResolver. Do not copy
     * this mapping into Blade. Per-user overrides remain unsupported.
     *
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
    public static function permissionMatrix(): array
    {
        return app(AdminPermissionResolver::class)->permissionMatrix();
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
            self::ORDERS_REFUND,
            self::ORDERS_DELETE_TEST,
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
            self::ADMINISTRATOR => self::without(self::STAFF_MANAGE, self::ORDERS_REFUND, self::ORDERS_DELETE_TEST),
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

        return app(AdminPermissionResolver::class)->isGranted($user->admin_role, $permission);
    }

    /**
     * Existing production admins keep access to existing modules until an Owner
     * is explicitly assigned. Legacy accounts can never manage staff.
     *
     * @return list<string>
     */
    private static function legacyPermissions(): array
    {
        return self::without(self::STAFF_VIEW, self::STAFF_MANAGE, self::ORDERS_REFUND, self::ORDERS_DELETE_TEST);
    }

    /** @return list<string> */
    private static function without(string ...$permissions): array
    {
        return array_values(array_diff(self::permissions(), $permissions));
    }
}
