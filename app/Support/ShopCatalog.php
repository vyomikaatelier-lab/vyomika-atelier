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

    /** @param Builder<\App\Models\Product> $query */
    public static function applyShopScope(Builder $query): Builder
    {
        return $query
            ->whereHas('category', fn ($q) => $q->whereIn('slug', self::categorySlugs()))
            ->where('section', \App\Models\Product::SECTION_SHOP)
            ->where('purchase_mode', \App\Models\Product::PURCHASE_MODE_CHECKOUT);
    }
}
