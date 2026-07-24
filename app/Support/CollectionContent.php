<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class CollectionContent
{
    /** @return list<string> */
    public static function slugs(): array
    {
        $configSlugs = array_keys(config('collections', []));
        $overrideSlugs = [];

        if (Schema::hasTable('site_settings')) {
            $overrideSlugs = array_keys(SiteSetting::getValue('collection_pages', []) ?? []);
        }

        return array_values(array_unique(array_merge($configSlugs, $overrideSlugs)));
    }

    /** @return array<string, mixed>|null */
    public static function page(string $slug): ?array
    {
        $defaults = config("collections.{$slug}");
        $overrides = null;

        if (Schema::hasTable('site_settings')) {
            $overrides = SiteSetting::getValue('collection_pages', [])[$slug] ?? null;
        }

        if (! is_array($defaults) && ! is_array($overrides)) {
            return null;
        }

        return array_replace_recursive($defaults ?? [], $overrides ?? []);
    }
}
