<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ShopCatalog
{
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
            'home-decor' => route('shop.index'),
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
            ->where('section', \App\Models\Product::SECTION_SHOP)
            ->where('purchase_mode', \App\Models\Product::PURCHASE_MODE_CHECKOUT);
    }
}
