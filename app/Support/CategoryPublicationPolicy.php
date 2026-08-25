<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Facades\Schema;

/**
 * Public visibility rules for storefront categories.
 */
class CategoryPublicationPolicy
{
    public static function isPubliclyAccessible(?Category $category): bool
    {
        return $category !== null && $category->is_active;
    }

    public static function isNavListed(?Category $category): bool
    {
        return self::isPubliclyAccessible($category);
    }

    public static function isSitemapListed(?Category $category): bool
    {
        return self::isPubliclyAccessible($category);
    }

    public static function isSitemapListedBySlug(string $slug): bool
    {
        if (! Schema::hasTable('categories')) {
            return true;
        }

        $category = Category::query()->where('slug', $slug)->first();

        if (! $category) {
            return true;
        }

        return $category->is_active;
    }
}
