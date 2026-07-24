<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Merge config defaults with SiteSetting static_pages overrides.
 * Empty override fields do not wipe defaults (fill-empty semantics for installer).
 */
class StaticPageContent
{
    /** @return list<string> */
    public static function slugs(): array
    {
        return [
            'home',
            'shop',
            'studio',
            'about',
            'professionals',
            'projects',
            'blog',
            'contact',
        ];
    }

    public static function label(string $slug): string
    {
        return match ($slug) {
            'home' => 'Homepage',
            'shop' => 'Shop Index',
            'studio' => 'Studio Index',
            'about' => 'About',
            'professionals' => 'Professionals',
            'projects' => 'Projects Index',
            'blog' => 'Blog Index',
            'contact' => 'Contact',
            default => ucwords(str_replace('-', ' ', $slug)),
        };
    }

    /** @return array<string, mixed> */
    public static function page(string $slug): array
    {
        $defaults = config('seo.static_pages.'.$slug, []);
        $overrides = [];

        if (Schema::hasTable('site_settings')) {
            $all = SiteSetting::getValue('static_pages', []) ?? [];
            $overrides = is_array($all[$slug] ?? null) ? $all[$slug] : [];
        }

        return self::mergeFillEmpty(
            is_array($defaults) ? $defaults : [],
            $overrides
        );
    }

    /**
     * Recursively merge: non-empty override values win; empty strings/null keep defaults.
     * List arrays in overrides replace wholesale when non-empty.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function mergeFillEmpty(array $defaults, array $overrides): array
    {
        $result = $defaults;

        foreach ($overrides as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                if ($value !== []) {
                    $result[$key] = $value;
                }

                continue;
            }

            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($defaults[$key])) {
                $result[$key] = self::mergeFillEmpty($defaults[$key], $value);

                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Fill only empty keys in existing SiteSetting payload (idempotent installer).
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public static function fillEmptyOnly(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                $current = is_array($existing[$key] ?? null) ? $existing[$key] : [];
                $existing[$key] = self::fillEmptyOnly($current, $value);

                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                if (! isset($existing[$key]) || $existing[$key] === [] || $existing[$key] === null) {
                    $existing[$key] = $value;
                }

                continue;
            }

            $current = $existing[$key] ?? null;
            if ($current === null || $current === '' || (is_string($current) && trim($current) === '')) {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }
}
