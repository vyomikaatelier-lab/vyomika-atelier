<?php

namespace App\Support;

use App\Models\Category;
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
                // Gallery source may still label fluted/room-divider rows; storefront taxonomy is partitions only.
                'category' => 'partitions',
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
                'category' => 'slim-profile-door-system',
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
                    'category' => 'main-entrance-pvd-doors',
                ];
            }
        }

        foreach ($serviceCatalog['rack-systems-metal-pvd'] ?? [] as $item) {
            $map[$item['slug']] = [
                'section' => 'studio',
                'service_slug' => 'rack-systems-metal-pvd',
                'shop_category' => null,
                'category' => 'rack-systems-metal-pvd',
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

    /** @return list<array{name: string, slug: string, section: string}> */
    public static function canonicalCategories(): array
    {
        return [
            // Shop
            ['name' => 'Mirror Frames', 'slug' => 'mirror-frames', 'section' => 'shop'],
            ['name' => 'Corner Tables', 'slug' => 'corner-tables', 'section' => 'shop'],
            ['name' => 'Coffee Tables', 'slug' => 'coffee-tables', 'section' => 'shop'],
            ['name' => 'Glass Tables', 'slug' => 'glass-tables', 'section' => 'shop'],
            ['name' => 'Door Handles', 'slug' => 'door-handles', 'section' => 'shop'],
            ['name' => 'Bespoke Metal Furniture', 'slug' => 'bespoke-metal-furniture', 'section' => 'shop'],
            // Studio (aligned to services)
            ['name' => 'PVD Partitions', 'slug' => 'partitions', 'section' => 'studio'],
            ['name' => 'Slim Profile Door Systems', 'slug' => 'slim-profile-door-system', 'section' => 'studio'],
            ['name' => 'Main Entrance PVD Doors', 'slug' => 'main-entrance-pvd-doors', 'section' => 'studio'],
            ['name' => 'PVD Metal Rack Systems', 'slug' => 'rack-systems-metal-pvd', 'section' => 'studio'],
        ];
    }

    /** @return list<string> Slugs kept in DB but never reactivated by sync. */
    public static function obsoleteCategorySlugs(): array
    {
        return [
            'fluted-panels',
            'room-dividers',
            'metal-furniture',
            'home-decor',
            'railings',
        ];
    }

    /** @return array<string, array{name: string, section: string}> */
    public static function obsoleteCategoryMeta(): array
    {
        return [
            'fluted-panels' => ['name' => 'Fluted Panels', 'section' => 'studio'],
            'room-dividers' => ['name' => 'Room Dividers', 'section' => 'studio'],
            'metal-furniture' => ['name' => 'Metal Furniture', 'section' => 'studio'],
            'home-decor' => ['name' => 'Home Decor', 'section' => 'shop'],
            'railings' => ['name' => 'Railings', 'section' => 'railings'],
        ];
    }

    public static function syncCanonicalCategories(): int
    {
        $synced = 0;

        foreach (self::canonicalCategories() as $index => $cat) {
            Category::query()->updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'section' => $cat['section'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
            $synced++;
        }

        foreach (self::obsoleteCategoryMeta() as $slug => $meta) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'section' => $meta['section'],
                    'is_active' => false,
                ]
            );
        }

        Category::query()
            ->whereIn('slug', self::obsoleteCategorySlugs())
            ->update(['is_active' => false]);

        return $synced;
    }

    public static function assignUnclassifiedProducts(): int
    {
        $updated = 0;

        Product::query()
            ->with('category')
            ->where(function ($query) {
                $query->whereNull('category_id')
                    ->orWhere(fn ($q) => $q->unclassified());
            })
            ->orderBy('slug')
            ->each(function (Product $product) use (&$updated): void {
                $categorySlug = self::categorySlugForProduct($product->slug)
                    ?? self::inferCategorySlugForProduct($product);

                if ($categorySlug === null) {
                    return;
                }

                $category = Category::query()->where('slug', $categorySlug)->first();
                if ($category === null) {
                    return;
                }

                $resolvedSection = $category->resolvedSection();
                $changes = [];

                if ($product->category_id !== $category->id) {
                    $changes['category_id'] = $category->id;
                }

                if ($resolvedSection !== null && $product->section !== $resolvedSection) {
                    $changes['section'] = $resolvedSection;
                    $changes['purchase_mode'] = Product::SECTION_PURCHASE_MODE_MAP[$resolvedSection] ?? null;
                    $changes['pricing_type'] = match ($resolvedSection) {
                        Product::SECTION_SHOP => Product::PRICING_FIXED,
                        Product::SECTION_STUDIO => Product::PRICING_SQUARE_FOOT,
                        Product::SECTION_RAILINGS => Product::PRICING_QUOTATION_ONLY,
                        default => null,
                    };
                }

                if ($changes !== []) {
                    $product->update($changes);
                    $updated++;
                }
            });

        return $updated;
    }

    public static function inferCategorySlugForProduct(Product $product): ?string
    {
        $section = self::inferSectionFromSlug($product->slug);
        $current = $product->category?->slug;

        if ($section === 'railings' || $current === 'railings') {
            return 'railings';
        }

        if (in_array($current, ['fluted-panels', 'room-dividers'], true)) {
            return 'partitions';
        }

        if ($current === 'home-decor') {
            return 'bespoke-metal-furniture';
        }

        if ($current === 'metal-furniture') {
            return self::categorySlugForMetalFurnitureProduct($product->slug);
        }

        if ($section === 'shop') {
            return self::inferShopCategoryFromSlug($product->slug);
        }

        if ($section === 'studio') {
            if (preg_match('/(partition|fluted|room-divider|divider)/', $product->slug)) {
                return 'partitions';
            }

            return self::categorySlugForMetalFurnitureProduct($product->slug);
        }

        return $current;
    }

    /**
     * Resolve approved category for legacy metal-furniture (or keyword-matched) products.
     */
    public static function categorySlugForMetalFurnitureProduct(string $productSlug): string
    {
        if (self::isBespokeMetalFurnitureProduct($productSlug)) {
            return 'bespoke-metal-furniture';
        }

        return self::inferMetalFurnitureService($productSlug) ?? 'bespoke-metal-furniture';
    }

    /** @return list<string> Category slugs recognised as belonging to Studio. */
    public static function studioCategorySlugs(): array
    {
        return [
            'partitions',
            'slim-profile-door-system',
            'main-entrance-pvd-doors',
            'rack-systems-metal-pvd',
        ];
    }

    /** @return list<string> Railings is an independent page, not a product category. */
    public static function railingsCategorySlugs(): array
    {
        return [];
    }

    /** @return list<string> */
    private static function hardcodedCategorySlugsForSection(string $section): array
    {
        return match ($section) {
            'shop' => StorefrontRoutes::shopCategorySlugs(),
            'studio' => self::studioCategorySlugs(),
            'railings' => self::railingsCategorySlugs(),
            default => [],
        };
    }

    /**
     * Category slugs valid as a "parent" for a given product section.
     * Used by the admin product form to filter category options by section.
     *
     * @return list<string>
     */
    public static function categorySlugsForSection(string $section): array
    {
        $dbSlugs = Category::query()
            ->where('is_active', true)
            ->where('section', $section)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->all();

        if ($dbSlugs !== []) {
            return $dbSlugs;
        }

        return self::hardcodedCategorySlugsForSection($section);
    }

    /** Which section (shop|studio|railings|null) a category slug belongs to, if known. */
    public static function sectionForCategorySlug(string $categorySlug, ?Category $category = null): ?string
    {
        if ($category?->section !== null && in_array($category->section, Product::SECTIONS, true)) {
            return $category->section;
        }

        $dbSection = Category::query()->where('slug', $categorySlug)->value('section');
        if ($dbSection !== null && in_array($dbSection, Product::SECTIONS, true)) {
            return $dbSection;
        }

        foreach (Product::SECTIONS as $section) {
            if (in_array($categorySlug, self::hardcodedCategorySlugsForSection($section), true)) {
                return $section;
            }
        }

        // Legacy archived category recognition.
        if (in_array($categorySlug, ['partitions', 'fluted-panels', 'room-dividers', 'metal-furniture', 'slim-profile-door-system', 'main-entrance-pvd-doors', 'rack-systems-metal-pvd'], true)) {
            return 'studio';
        }

        if ($categorySlug === 'railings') {
            return 'railings';
        }

        if ($categorySlug === 'home-decor') {
            return 'shop';
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

        if ($categorySlug === 'home-decor') {
            return 'shop';
        }

        // Legacy + approved studio category recognition.
        if (in_array($categorySlug, ['partitions', 'fluted-panels', 'room-dividers', 'slim-profile-door-system', 'main-entrance-pvd-doors', 'rack-systems-metal-pvd'], true)) {
            return 'studio';
        }

        if ($categorySlug === 'door-handles') {
            return 'shop';
        }

        if ($categorySlug === 'railings') {
            return 'railings';
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

        // Legacy archived categories still resolve to partitions service.
        if (in_array($categorySlug, ['partitions', 'fluted-panels', 'room-dividers'], true)) {
            return 'partitions';
        }

        if (in_array($categorySlug, self::studioCategorySlugs(), true) && $categorySlug !== 'partitions') {
            return $categorySlug;
        }

        if ($categorySlug === 'metal-furniture' && $productSlug) {
            return self::inferMetalFurnitureService($productSlug);
        }

        if ($productSlug) {
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

        if (in_array($categorySlug, ['metal-furniture', 'home-decor'], true)) {
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

        return in_array($category, ['metal-furniture', 'home-decor'], true)
            ? 'bespoke-metal-furniture'
            : $category;
    }

    private static function inferMetalFurnitureSection(string $productSlug): string
    {
        return self::inferMetalFurnitureService($productSlug) ? 'studio' : 'shop';
    }

    public static function inferMetalFurnitureService(string $productSlug): ?string
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
