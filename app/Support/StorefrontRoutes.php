<?php

namespace App\Support;

/**
 * Canonical URL slugs for Shop and Studio navigation.
 */
class StorefrontRoutes
{
    /** @return list<string> */
    public static function shopCategorySlugs(): array
    {
        return [
            'mirror-frames',
            'corner-tables',
            'coffee-tables',
            'glass-tables',
            'door-handles',
            'bespoke-metal-furniture',
        ];
    }

    /** @return array<string, string> studio URL slug => service DB slug */
    public static function studioServiceMap(): array
    {
        return [
            'pvd-partitions' => 'partitions',
            'slim-profile-door-systems' => 'slim-profile-door-system',
            'main-entrance-pvd-doors' => 'main-entrance-pvd-doors',
            'metal-pvd-rack-systems' => 'rack-systems-metal-pvd',
        ];
    }

    /** @return list<string> */
    public static function studioUrlSlugs(): array
    {
        return array_keys(self::studioServiceMap());
    }

    public static function isShopCategory(string $slug): bool
    {
        return in_array($slug, self::shopCategorySlugs(), true);
    }

    public static function isStudioUrl(string $slug): bool
    {
        return isset(self::studioServiceMap()[$slug]);
    }

    public static function serviceSlugForStudioUrl(string $urlSlug): ?string
    {
        return self::studioServiceMap()[$urlSlug] ?? null;
    }

    public static function studioUrlForService(string $serviceSlug): ?string
    {
        $map = array_flip(self::studioServiceMap());

        return $map[$serviceSlug] ?? null;
    }

    /** @return array<string, string> */
    public static function shopCategoryLabels(): array
    {
        return [
            'mirror-frames' => 'Mirror Frames',
            'corner-tables' => 'Corner Tables',
            'coffee-tables' => 'Coffee Tables',
            'glass-tables' => 'Glass Tables',
            'door-handles' => 'Door Handles',
            'bespoke-metal-furniture' => 'Bespoke Metal Furniture',
        ];
    }

    public static function shopCategoryLabel(string $slug): string
    {
        return self::shopCategoryLabels()[$slug] ?? ucwords(str_replace('-', ' ', $slug));
    }

    public static function shopCategoryUrl(string $slug): string
    {
        if ($slug === 'mirror-frames') {
            return route('shop.mirror-frames.index');
        }

        return route('shop.show', $slug);
    }

    public static function shopCategorySlugForProduct(?string $productSlug, ?string $categorySlug = null): ?string
    {
        return ProductCatalog::shopCategorySlugForProduct($productSlug, $categorySlug);
    }

    /** @return array<string, string> */
    public static function studioServiceLabels(): array
    {
        return [
            'partitions' => 'PVD Partitions',
            'slim-profile-door-system' => 'Slim Profile Door Systems',
            'main-entrance-pvd-doors' => 'Main Entrance PVD Doors',
            'rack-systems-metal-pvd' => 'Metal PVD Rack Systems',
        ];
    }

    public static function studioServiceLabel(string $serviceSlug): string
    {
        return self::studioServiceLabels()[$serviceSlug] ?? ucwords(str_replace('-', ' ', $serviceSlug));
    }

    public static function studioUrlForProduct(?string $productSlug, ?string $categorySlug = null): ?string
    {
        return ProductCatalog::studioUrlForProduct($productSlug, $categorySlug);
    }

    public static function isStudioProduct(?string $productSlug, ?string $categorySlug = null): bool
    {
        return ProductCatalog::isStudioProduct($productSlug, $categorySlug);
    }

    public static function isShopProduct(?string $productSlug, ?string $categorySlug = null): bool
    {
        return ProductCatalog::isShopProduct($productSlug, $categorySlug);
    }

    /**
     * @return list<array{label: string, url?: string}>
     */
    public static function productBreadcrumbs(\App\Models\Product $product): array
    {
        $from = request()->query('from');

        return ProductCatalog::breadcrumbsFor($product, is_string($from) ? $from : null);
    }

    public static function productSectionLabel(\App\Models\Product $product): ?string
    {
        $from = request()->query('from');

        return ProductCatalog::sectionLabelFor($product, is_string($from) ? $from : null);
    }

    public static function productUrl(\App\Models\Product $product, ?string $shopContextSlug = null): string
    {
        return ProductCatalog::productUrl($product, $shopContextSlug);
    }
}
