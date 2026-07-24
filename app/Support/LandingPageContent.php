<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Merge config defaults with SiteSetting overrides for independent landing pages
 * (Railings, Corten Steel).
 */
class LandingPageContent
{
    /** @return list<string> */
    public static function slugs(): array
    {
        return ['railings', 'corten-steel'];
    }

    public static function label(string $slug): string
    {
        return match ($slug) {
            'railings' => 'Railings Page',
            'corten-steel' => 'Corten Steel Page',
            default => ucwords(str_replace('-', ' ', $slug)),
        };
    }

    /** Config file key for a landing page slug. */
    public static function configKey(string $slug): string
    {
        return match ($slug) {
            'corten-steel' => 'corten',
            default => $slug,
        };
    }

    /** @return array<string, mixed> */
    public static function page(string $slug): array
    {
        $defaults = config(self::configKey($slug), []);
        $overrides = [];

        if (Schema::hasTable('site_settings')) {
            $pages = SiteSetting::getValue('landing_pages', []) ?? [];
            $overrides = is_array($pages[$slug] ?? null) ? $pages[$slug] : [];
        }

        return array_replace_recursive(
            is_array($defaults) ? $defaults : [],
            $overrides
        );
    }
}
