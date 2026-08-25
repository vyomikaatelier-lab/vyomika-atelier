<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ShopCatalog
{
    public static function supportsInventoryHide(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('products')
            && Schema::hasTable('categories')
            && Schema::hasColumn('products', 'hide_when_out_of_stock')
            && Schema::hasColumn('categories', 'hide_when_unavailable');

        return $ready;
    }

    public static function supportsHideFromNav(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $ready = Schema::hasTable('categories')
            && Schema::hasTable('services')
            && Schema::hasColumn('categories', 'hide_from_nav')
            && Schema::hasColumn('services', 'hide_from_nav');

        return $ready;
    }

    /**
     * Category slugs whose products belong in the shop.
     *
     * The curated list in StorefrontRoutes owns the canonical URLs and nav
     * labels; it is not the whole storefront. Active shop-section categories
     * created in the admin are unioned in so their products are not filtered
     * out of every shop query.
     *
     * @return list<string>
     */
    public static function categorySlugs(): array
    {
        $slugs = StorefrontRoutes::shopCategorySlugs();

        if (! Schema::hasTable('categories')) {
            return $slugs;
        }

        return array_values(array_unique(array_merge(
            $slugs,
            Category::query()
                ->where('is_active', true)
                ->where('section', Product::SECTION_SHOP)
                ->pluck('slug')
                ->all()
        )));
    }

    /** Redirect obsolete / studio-only category URLs away from the shop. */
    public static function studioCategoryRedirectUrl(string $slug): ?string
    {
        return match ($slug) {
            'partitions', 'fluted-panels', 'room-dividers' => route('studio.show', 'pvd-partitions'),
            'slim-profile-door-system' => route('studio.show', 'slim-profile-door-systems'),
            'main-entrance-pvd-doors' => route('studio.show', 'main-entrance-pvd-doors'),
            'rack-systems-metal-pvd' => route('studio.show', 'metal-pvd-rack-systems'),
            'metal-furniture' => route('shop.show', 'bespoke-metal-furniture'),
            'home-decor' => StorefrontRoutes::primaryShopUrl(),
            'railings' => route('railings.index'),
            default => null,
        };
    }

    /**
     * Deactivating a category in the admin has to take its products off the
     * storefront, including for the curated slugs.
     *
     * @param Builder<\App\Models\Product> $query
     */
    public static function applyShopScope(Builder $query): Builder
    {
        return $query
            ->whereHas('category', fn ($q) => $q
                ->where('is_active', true)
                ->whereIn('slug', self::categorySlugs()))
            ->where('section', Product::SECTION_SHOP)
            ->where('purchase_mode', Product::PURCHASE_MODE_CHECKOUT);
    }

    /**
     * Shop listings and galleries: also respect per-product out-of-stock hide flags.
     *
     * @param Builder<Product> $query
     */
    public static function applyListingScope(Builder $query): Builder
    {
        $query = self::applyShopScope($query);
        $query = ProductPublicationPolicy::applyGalleryScope($query);

        if (! self::supportsInventoryHide()) {
            return $query;
        }

        return $query->unlessHiddenForStock();
    }

    /**
     * Categories shown in shop navigation. When hide_when_unavailable is enabled,
     * the category is omitted once every product in it is hidden for stock.
     *
     * @param Builder<Category> $query
     */
    public static function applyStorefrontCategoryScope(Builder $query): Builder
    {
        if (! self::supportsInventoryHide()) {
            return $query->whereHas('products', fn (Builder $productQuery) => self::applyShopScope(
                $productQuery->where('is_active', true)
            ));
        }

        $anyShopProduct = fn (Builder $productQuery) => self::applyShopScope(
            $productQuery->where('is_active', true)
        );

        $availableShopProduct = fn (Builder $productQuery) => self::applyListingScope(
            $productQuery->where('is_active', true)
        );

        return $query->where(function (Builder $categoryQuery) use ($anyShopProduct, $availableShopProduct) {
            $categoryQuery
                ->where(function (Builder $inner) use ($anyShopProduct) {
                    $inner->where('hide_when_unavailable', false)
                        ->whereHas('products', $anyShopProduct);
                })
                ->orWhere(function (Builder $inner) use ($availableShopProduct) {
                    $inner->where('hide_when_unavailable', true)
                        ->whereHas('products', $availableShopProduct);
                });
        });
    }

    public static function isCategoryVisibleInNav(string $slug): bool
    {
        if (! Schema::hasTable('categories')) {
            return true;
        }

        $category = Category::query()->where('slug', $slug)->first();

        if (! $category) {
            return true;
        }

        if (! $category->is_active) {
            return false;
        }

        if (self::supportsHideFromNav() && $category->hide_from_nav) {
            return false;
        }

        if (! self::supportsInventoryHide() || ! $category->hide_when_unavailable) {
            return true;
        }

        return $category->hasStorefrontAvailableProducts();
    }

    public static function isServiceVisibleInNav(string $serviceSlug): bool
    {
        if (! Schema::hasTable('services')) {
            return true;
        }

        $service = Service::query()->where('slug', $serviceSlug)->first();

        if (! $service) {
            return true;
        }

        if (! $service->is_active) {
            return false;
        }

        if (self::supportsHideFromNav() && $service->hide_from_nav) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nav
     * @return array<int, array<string, mixed>>
     */
    public static function filterNav(array $nav): array
    {
        if (! Schema::hasTable('categories')) {
            return $nav;
        }

        return collect($nav)->map(function (array $item) {
            $label = $item['label'] ?? '';

            if ($label === 'Shop' && ! empty($item['children']) && is_array($item['children'])) {
                $item['children'] = self::filterShopLinks($item['children']);
            }

            if ($label === 'Studio' && ! empty($item['children']) && is_array($item['children'])) {
                $item['children'] = self::filterStudioLinks($item['children']);
            }

            return $item;
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array<string, mixed>>
     */
    public static function filterShopLinks(array $links): array
    {
        if (! Schema::hasTable('categories')) {
            return $links;
        }

        return collect($links)
            ->filter(function (array $link) {
                $route = $link['route'] ?? '';

                if ($route === 'shop.mirror-frames.index') {
                    return self::isCategoryVisibleInNav('mirror-frames');
                }

                if ($route === 'shop.index') {
                    return false;
                }

                $slug = $link['params']['slug'] ?? null;

                return is_string($slug) ? self::isCategoryVisibleInNav($slug) : true;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array<string, mixed>>
     */
    public static function filterStudioLinks(array $links): array
    {
        if (! Schema::hasTable('services')) {
            return $links;
        }

        return collect($links)
            ->filter(function (array $link) {
                $urlSlug = $link['params']['slug'] ?? null;

                if (! is_string($urlSlug)) {
                    return true;
                }

                $serviceSlug = StorefrontRoutes::serviceSlugForStudioUrl($urlSlug);

                return $serviceSlug ? self::isServiceVisibleInNav($serviceSlug) : true;
            })
            ->values()
            ->all();
    }
}
