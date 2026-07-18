<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class ShopCatalog
{
    /** @return list<string> */
    public static function categorySlugs(): array
    {
        return StorefrontRoutes::shopCategorySlugs();
    }

    /** Redirect legacy shop category URLs for studio-only categories. */
    public static function studioCategoryRedirectUrl(string $slug): ?string
    {
        return match ($slug) {
            'partitions', 'fluted-panels', 'room-dividers' => route('studio.show', 'pvd-partitions'),
            'metal-furniture' => route('shop.show', 'bespoke-metal-furniture'),
            default => null,
        };
    }

    /** @param Builder<\App\Models\Product> $query */
    public static function applyShopScope(Builder $query): Builder
    {
        return $query
            ->whereHas('category', fn ($q) => $q->whereIn('slug', self::categorySlugs()))
            ->where('section', \App\Models\Product::SECTION_SHOP)
            ->where('purchase_mode', \App\Models\Product::PURCHASE_MODE_CHECKOUT);
    }
}
