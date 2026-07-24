<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Merge config defaults with SiteSetting overrides for independent landing pages
 * (Railings, Corten Steel). List sections in overrides replace defaults wholesale.
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

    public static function publicRoute(string $slug): string
    {
        return match ($slug) {
            'railings' => route('railings.index'),
            'corten-steel' => route('corten-steel.show'),
            default => url('/'),
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
    public static function storedOverride(string $slug): array
    {
        if (! Schema::hasTable('site_settings')) {
            return [];
        }

        $pages = SiteSetting::getValue('landing_pages', []) ?? [];
        $override = $pages[$slug] ?? null;

        return is_array($override) ? $override : [];
    }

    /** @param  array<string, mixed>  $override */
    public static function storeOverride(string $slug, array $override): void
    {
        $pages = SiteSetting::getValue('landing_pages', []) ?? [];
        $existing = is_array($pages[$slug] ?? null) ? $pages[$slug] : [];
        $pages[$slug] = self::mergePage($existing, $override);
        SiteSetting::setValue('landing_pages', $pages);
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

        return self::mergePage(
            is_array($defaults) ? $defaults : [],
            $overrides
        );
    }

    /**
     * Associative arrays merge recursively; numeric lists in overrides replace defaults.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function mergePage(array $defaults, array $overrides): array
    {
        $result = $defaults;

        foreach ($overrides as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $result[$key] = $value;

                continue;
            }

            if (
                is_array($value)
                && isset($defaults[$key])
                && is_array($defaults[$key])
                && ! array_is_list($defaults[$key])
            ) {
                $result[$key] = self::mergePage($defaults[$key], $value);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     * @return list<array<string, mixed>>
     */
    public static function activeItems(?array $items): array
    {
        return collect($items ?? [])
            ->filter(fn ($item) => is_array($item) && ($item['active'] ?? true) !== false && ($item['active'] ?? true) !== '0')
            ->values()
            ->all();
    }

    /**
     * Resolve image fields on a page tree for Blade (hero.image, item images, etc.).
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function withResolvedImages(array $page): array
    {
        return self::mapImages($page);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function mapImages(array $node): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = self::mapImages($value);

                continue;
            }

            if (
                is_string($value)
                && in_array($key, ['image', 'image_mobile', 'image_tablet', 'mobile_image', 'og_image'], true)
            ) {
                $node[$key] = MediaUrl::resolve($value) ?? $value;
            }
        }

        return $node;
    }
}
