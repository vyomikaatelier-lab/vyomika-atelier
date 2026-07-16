<?php

namespace App\Support;

use App\Http\Controllers\CollectionGalleryController;
use Illuminate\Database\Eloquent\Builder;

class ShopCatalog
{
    /** @return list<string> */
    public static function categorySlugs(): array
    {
        return [
            'mirror-frames',
            'coffee-tables',
            'corner-tables',
            'glass-tables',
            'door-handles',
        ];
    }

    /** Redirect legacy shop category URLs for studio-only categories. */
    public static function studioCategoryRedirectUrl(string $slug): ?string
    {
        return match ($slug) {
            'partitions', 'fluted-panels', 'room-dividers' => route('services.show', 'partitions'),
            'metal-furniture' => route('collections.gallery.index', 'bespoke-metal-furniture'),
            default => null,
        };
    }

    /** @param Builder<\App\Models\Product> $query */
    public static function applyShopScope(Builder $query): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->whereIn('slug', self::categorySlugs()));
    }
}
