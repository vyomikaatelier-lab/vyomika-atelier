<?php

namespace App\Support;

use App\Models\Product;

/**
 * Authoritative Shop / Studio product classification from catalog source data.
 */
class ProductCatalog
{
    /** @var array<string, array{section: string, service_slug: ?string, shop_category: ?string, category: string}>|null */
    private static ?array $slugMap = null;

    /** @var array<string, list<array<string, mixed>>>|null */
    private static ?array $serviceCatalog = null;

    /** @return array<string, list<array<string, mixed>>> */
    private static function serviceCatalog(): array
    {
        return self::$serviceCatalog ??= CatalogData::serviceGallery();
    }

    /** @return array<string, array{section: string, service_slug: ?string, shop_category: ?string, category: string}> */
    public static function slugMap(): array
    {
        if (self::$slugMap !== null) {
            return self::$slugMap;
        }

        $map = [];

        foreach (require database_path('data/partition-gallery-products.php') as $item) {
            $map[$item['slug']] = [
                'section' => 'studio',
                'service_slug' => 'partitions',
                'shop_category' => null,
                'category' => $item['category'],
            ];
        }

        foreach (require database_path('data/mirror-frames-catalog.php') as $item) {
            $map[$item['slug']] = [
                'section' => 'shop',
                'service_slug' => null,
                'shop_category' => 'mirror-frames',
                'category' => $item['category'],
            ];
        }

        $serviceCatalog = self::serviceCatalog();

        foreach ($serviceCatalog['slim-profile-door-system'] ?? [] as $item) {
            $map[$item['slug']] = [
                'section' => 'studio',
                'service_slug' => 'slim-profile-door-system',
                'shop_category' => null,
                'category' => $item['category'],
            ];
        }

        foreach ($serviceCatalog['main-entrance-pvd-doors'] ?? [] as $item) {
            if (($item['category'] ?? '') === 'door-handles') {
                $map[$item['slug']] = [
                    'section' => 'shop',
                    'service_slug' => null,
                    'shop_category' => 'door-handles',
                    'category' => $item['category'],
                ];
            } else {
                $map[$item['slug']] = [
                    'section' => 'studio',
                    'service_slug' => 'main-entrance-pvd-doors',
                    'shop_category' => null,
                    'category' => $item['category'],
                ];
            }
        }

        foreach ($serviceCatalog['rack-systems-metal-pvd'] ?? [] as $item) {
            $map[$item['slug']] = [
                'section' => 'studio',
                'service_slug' => 'rack-systems-metal-pvd',
                'shop_category' => null,
                'category' => $item['category'],
            ];
        }

        foreach ($serviceCatalog['bespoke-metal-furniture'] ?? [] as $item) {
            $shopCategory = self::normalizeShopCategory($item['category']);
            $map[$item['slug']] = [
                'section' => 'shop',
                'service_slug' => null,
                'shop_category' => $shopCategory,
                'category' => $item['category'],
            ];
        }

        return self::$slugMap = $map;
    }

    /** @return list<string> Category slugs recognised as belonging to Studio. */
    public static function studioCategorySlugs(): array
    {
        return ['partitions', 'fluted-panels', 'room-dividers', 'metal-furniture'];
    }

    /** @return list<string> Category slugs recognised as belonging to Railings. */
    public static function railingsCategorySlugs(): array
    {
        return ['railings'];
    }

    /**
     * Category slugs valid as a "parent" for a given product section.
     * Used by the admin product form to filter category options by section.
     *
     * @return list<string>
     */
    public static function categorySlugsForSection(string $section): array
    {
        return match ($section) {
            'shop' => StorefrontRoutes::shopCategorySlugs(),
            'studio' => self::studioCategorySlugs(),
            'railings' => self::railingsCategorySlugs(),
            default => [],
        };
    }

    /** Which section (shop|studio|railings|null) a category slug belongs to, if known. */
    public static function sectionForCategorySlug(string $categorySlug): ?string
    {
        foreach (['shop', 'studio', 'railings'] as $section) {
            if (in_array($categorySlug, self::categorySlugsForSection($section), true)) {
                return $section;
            }
        }

        return null;
    }

    public static function isShopProduct(?string $productSlug, ?string $categorySlug = null): bool
    {
        return self::sectionFor($productSlug, $categorySlug) === 'shop';
    }

    public static function isStudioProduct(?string $productSlug, ?string $categorySlug = null): bool
    {
        return self::sectionFor($productSlug, $categorySlug) === 'studio';
    }

    public static function sectionFor(?string $productSlug, ?string $categorySlug = null): string
    {
        if ($productSlug && isset(self::slugMap()[$productSlug])) {
            return self::slugMap()[$productSlug]['section'];
        }

        if ($categorySlug && StorefrontRoutes::isShopCategory($categorySlug)) {
            return 'shop';
        }

        if (in_array($categorySlug, ['partitions', 'fluted-panels', 'room-dividers'], true)) {
            return 'studio';
        }

        if ($categorySlug === 'door-handles') {
            return 'shop';
        }

        if ($categorySlug === 'metal-furniture' && $productSlug) {
            return self::inferMetalFurnitureSection($productSlug);
        }

        if ($productSlug) {
            $inferred = self::inferSectionFromSlug($productSlug);
            if ($inferred !== 'unknown') {
                return $inferred;
            }
        }

        return 'unknown';
    }

    /**
     * Infer shop/studio section from product slug keywords when category is missing
     * or ambiguous (e.g. procedurally generated gallery SKUs).
     */
    public static function inferSectionFromSlug(string $productSlug): string
    {
        $slug = strtolower($productSlug);

        if (preg_match('/(^|-)(railing|balustrade|handrail)(-|$)/', $slug)) {
            return 'railings';
        }

        if (preg_match('/(partition|fluted|room-divider|slim-profile|pivot-door|entrance-door|rack-system)/', $slug)) {
            return 'studio';
        }

        if (preg_match('/(mirror-frame|coffee-table|corner-table|glass-table|door-handle|pull-handle|side-table|console-table|nest-table)/', $slug)) {
            return 'shop';
        }

        return 'unknown';
    }

    /** Infer a shop category slug from product slug keywords. */
    public static function inferShopCategoryFromSlug(string $productSlug): ?string
    {
        $slug = strtolower($productSlug);

        return match (true) {
            str_contains($slug, 'mirror') => 'mirror-frames',
            str_contains($slug, 'coffee-table') => 'coffee-tables',
            str_contains($slug, 'corner-table') => 'corner-tables',
            str_contains($slug, 'glass-table') => 'glass-tables',
            str_contains($slug, 'door-handle') || str_contains($slug, 'pull-handle') => 'door-handles',
            str_contains($slug, 'table') || str_contains($slug, 'furniture') => 'bespoke-metal-furniture',
            default => null,
        };
    }

    public static function serviceSlugForProduct(?string $productSlug, ?string $categorySlug = null): ?string
    {
        if ($productSlug && isset(self::slugMap()[$productSlug])) {
            return self::slugMap()[$productSlug]['service_slug'];
        }

        if (self::sectionFor($productSlug, $categorySlug) !== 'studio') {
            return null;
        }

        if (in_array($categorySlug, ['partitions', 'fluted-panels', 'room-dividers'], true)) {
            return 'partitions';
        }

        if ($categorySlug === 'metal-furniture' && $productSlug) {
            return self::inferMetalFurnitureService($productSlug);
        }

        return null;
    }

    public static function shopCategorySlugForProduct(?string $productSlug, ?string $categorySlug = null): ?string
    {
        if ($productSlug && isset(self::slugMap()[$productSlug])) {
            return self::slugMap()[$productSlug]['shop_category'];
        }

        if ($categorySlug && StorefrontRoutes::isShopCategory($categorySlug)) {
            return $categorySlug;
        }

        if ($categorySlug === 'metal-furniture') {
            return 'bespoke-metal-furniture';
        }

        if ($categorySlug === 'door-handles') {
            return 'door-handles';
        }

        return null;
    }

    public static function studioUrlForProduct(?string $productSlug, ?string $categorySlug = null): ?string
    {
        $serviceSlug = self::serviceSlugForProduct($productSlug, $categorySlug);

        return $serviceSlug ? StorefrontRoutes::studioUrlForService($serviceSlug) : null;
    }

    public static function studioServiceLabel(?string $productSlug, ?string $categorySlug = null): ?string
    {
        $serviceSlug = self::serviceSlugForProduct($productSlug, $categorySlug);

        return $serviceSlug ? StorefrontRoutes::studioServiceLabel($serviceSlug) : null;
    }

    public static function categorySlugForProduct(?string $productSlug): ?string
    {
        if ($productSlug && isset(self::slugMap()[$productSlug])) {
            return self::slugMap()[$productSlug]['category'];
        }

        return null;
    }

    /** @return list<string> */
    public static function productSlugsForService(string $serviceSlug): array
    {
        return collect(self::slugMap())
            ->filter(fn (array $meta) => $meta['section'] === 'studio' && $meta['service_slug'] === $serviceSlug)
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function productSlugsForShopCategory(string $shopCategorySlug): array
    {
        return collect(self::slugMap())
            ->filter(fn (array $meta) => $meta['section'] === 'shop' && $meta['shop_category'] === $shopCategorySlug)
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Product slugs for a shop category landing page (gallery grid).
     * Bespoke Metal Furniture is an umbrella page over its dedicated catalog slice.
     *
     * @return list<string>
     */
    public static function productSlugsForShopPage(string $shopCategorySlug): array
    {
        if ($shopCategorySlug === 'bespoke-metal-furniture') {
            return self::bespokeMetalFurnitureCatalogSlugs();
        }

        if ($shopCategorySlug === 'mirror-frames') {
            return self::productSlugsForShopCategory('mirror-frames');
        }

        if ($shopCategorySlug === 'door-handles') {
            return self::productSlugsForShopCategory('door-handles');
        }

        // Coffee / corner / glass table pages list by catalog subtype, not umbrella shop tag.
        return collect(self::slugMap())
            ->filter(fn (array $meta) => $meta['section'] === 'shop' && ($meta['category'] ?? '') === $shopCategorySlug)
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function bespokeMetalFurnitureCatalogSlugs(): array
    {
        static $slugs = null;

        if ($slugs !== null) {
            return $slugs;
        }

        $catalog = self::serviceCatalog();

        return $slugs = array_values(array_column($catalog['bespoke-metal-furniture'] ?? [], 'slug'));
    }

    public static function isBespokeMetalFurnitureProduct(?string $productSlug): bool
    {
        return $productSlug && in_array($productSlug, self::bespokeMetalFurnitureCatalogSlugs(), true);
    }

    /**
     * Database-first: an explicit `products.section` always wins. The slug/
     * category maps below are only consulted as a fallback for legacy rows
     * that predate the classification migration (see Phase 2 notes on
     * App\Models\Product).
     */
    private static function isStudioSection(Product $product, ?string $slug, ?string $categorySlug): bool
    {
        if ($product->section !== null) {
            return $product->section === Product::SECTION_STUDIO;
        }

        return self::isStudioProduct($slug, $categorySlug);
    }

    /**
     * @return list<array{label: string, url?: string}>
     */
    public static function breadcrumbsFor(Product $product, ?string $shopContextSlug = null): array
    {
        $slug = $product->slug;
        $categorySlug = $product->category?->slug;

        if (self::isStudioSection($product, $slug, $categorySlug)) {
            $serviceLabel = self::studioServiceLabel($slug, $categorySlug) ?? 'Studio';
            $studioUrl = self::studioUrlForProduct($slug, $categorySlug);

            return [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Studio', 'url' => route('studio.index')],
                ['label' => $serviceLabel, 'url' => $studioUrl ? route('studio.show', $studioUrl) : null],
                ['label' => $product->name],
            ];
        }

        $contextSlug = self::resolveShopContextSlug($shopContextSlug);
        $shopCategorySlug = $contextSlug ?? self::shopCategorySlugForProduct($slug, $categorySlug);
        $items = [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => route('shop.index')],
        ];

        if ($shopCategorySlug) {
            $items[] = [
                'label' => StorefrontRoutes::shopCategoryLabel($shopCategorySlug),
                'url' => StorefrontRoutes::shopCategoryUrl($shopCategorySlug),
            ];
        }

        $items[] = ['label' => $product->name];

        return $items;
    }

    public static function sectionLabelFor(Product $product, ?string $shopContextSlug = null): ?string
    {
        $slug = $product->slug;
        $categorySlug = $product->category?->slug;

        if (self::isStudioSection($product, $slug, $categorySlug)) {
            return self::studioServiceLabel($slug, $categorySlug);
        }

        $contextSlug = self::resolveShopContextSlug($shopContextSlug);
        if ($contextSlug) {
            return StorefrontRoutes::shopCategoryLabel($contextSlug);
        }

        $shopCategorySlug = self::shopCategorySlugForProduct($slug, $categorySlug);

        return $shopCategorySlug ? StorefrontRoutes::shopCategoryLabel($shopCategorySlug) : null;
    }

    public static function productUrl(Product $product, ?string $shopContextSlug = null): string
    {
        $url = route('shop.show', $product->slug);
        $contextSlug = self::resolveShopContextSlug($shopContextSlug);

        if ($contextSlug) {
            return $url.'?from='.urlencode($contextSlug);
        }

        return $url;
    }

    public static function resolveShopContextSlug(?string $shopContextSlug): ?string
    {
        return $shopContextSlug && StorefrontRoutes::isShopCategory($shopContextSlug)
            ? $shopContextSlug
            : null;
    }

    public static function estimateLabelForProduct(?string $productSlug, ?string $categorySlug = null): string
    {
        $serviceSlug = self::serviceSlugForProduct($productSlug, $categorySlug);

        return match ($serviceSlug) {
            'partitions' => 'partition',
            'rack-systems-metal-pvd' => 'display rack',
            'slim-profile-door-system', 'main-entrance-pvd-doors' => 'door',
            default => 'product',
        };
    }

    /** @return list<string> */
    public static function careGuidelinesForProduct(?string $productSlug, ?string $categorySlug = null): array
    {
        $serviceSlug = self::serviceSlugForProduct($productSlug, $categorySlug);

        if (! $serviceSlug) {
            return [
                'Material: Grade 304/316 stainless with PVD coating',
                'Designed in: Delhi, India',
                'Fabrication: VYOMIKA SALES — custom dimensions',
                'Care: Wipe with soft microfibre; avoid abrasives and harsh chemicals',
                'Delivery: Pan-India shipping from our Delhi studio',
            ];
        }

        return (new \App\Models\Service(['slug' => $serviceSlug]))->careGuidelines();
    }

    private static function normalizeShopCategory(string $category): string
    {
        if (StorefrontRoutes::isShopCategory($category)) {
            return $category;
        }

        return $category === 'metal-furniture' ? 'bespoke-metal-furniture' : $category;
    }

    private static function inferMetalFurnitureSection(string $productSlug): string
    {
        return self::inferMetalFurnitureService($productSlug) ? 'studio' : 'shop';
    }

    private static function inferMetalFurnitureService(string $productSlug): ?string
    {
        $slug = strtolower($productSlug);

        if (self::isBespokeMetalFurnitureProduct($productSlug)) {
            return null;
        }

        if (str_contains($slug, 'rack')) {
            return 'rack-systems-metal-pvd';
        }

        if (str_contains($slug, 'slim-') || str_contains($slug, 'slim-profile')) {
            return 'slim-profile-door-system';
        }

        if (! str_contains($slug, 'door') && ! str_contains($slug, 'suite') && ! str_contains($slug, 'entrance')) {
            return null;
        }

        if (preg_match('/\b(pivot|sliding|hinged|folding|stacking|frameless)\b/', $slug)) {
            return 'slim-profile-door-system';
        }

        if (str_contains($slug, 'entrance') || str_contains($slug, 'main-') || str_contains($slug, 'grand')
            || str_contains($slug, 'security') || str_contains($slug, 'monumental')) {
            return 'main-entrance-pvd-doors';
        }

        if (str_contains($slug, 'door') || str_contains($slug, 'gate') || str_contains($slug, 'portal')) {
            return 'main-entrance-pvd-doors';
        }

        return null;
    }
}
