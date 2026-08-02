<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class CollectionContent
{
    /** Legacy admin keys that map to a canonical shop collection slug. */
    private const OVERRIDE_SLUG_ALIASES = [
        'metal-furniture' => 'bespoke-metal-furniture',
        'home-decor' => 'bespoke-metal-furniture',
    ];

    /** @return list<string> */
    public static function overrideKeysFor(string $slug): array
    {
        $legacy = array_keys(array_filter(
            self::OVERRIDE_SLUG_ALIASES,
            fn (string $canonical) => $canonical === $slug
        ));

        return array_values(array_unique(array_merge($legacy, [$slug])));
    }

    /**
     * Merge stored overrides for a slug, including legacy keys saved before
     * bespoke-metal-furniture became the canonical shop category slug.
     *
     * @return array<string, mixed>
     */
    public static function storedOverrides(string $slug): array
    {
        if (! Schema::hasTable('site_settings')) {
            return [];
        }

        $pages = SiteSetting::getValue('collection_pages', []) ?? [];
        if (! is_array($pages)) {
            return [];
        }

        $merged = [];

        foreach (self::overrideKeysFor($slug) as $key) {
            $override = $pages[$key] ?? null;
            if (is_array($override)) {
                $merged = array_replace_recursive($merged, $override);
            }
        }

        return $merged;
    }

    /**
     * Normalize legacy override keys to canonical collection slugs.
     *
     * @param  array<string, mixed>  $pages
     * @return array<string, mixed>
     */
    public static function normalizeStoredPages(array $pages): array
    {
        foreach (self::OVERRIDE_SLUG_ALIASES as $legacy => $canonical) {
            if (! isset($pages[$legacy]) || ! is_array($pages[$legacy])) {
                continue;
            }

            $existing = is_array($pages[$canonical] ?? null) ? $pages[$canonical] : [];
            $pages[$canonical] = array_replace_recursive($pages[$legacy], $existing);
            unset($pages[$legacy]);
        }

        return $pages;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        $configSlugs = array_keys(config('collections', []));
        $overrideSlugs = [];

        if (Schema::hasTable('site_settings')) {
            $overrideSlugs = array_keys(self::normalizeStoredPages(SiteSetting::getValue('collection_pages', []) ?? []));
        }

        return array_values(array_unique(array_merge($configSlugs, $overrideSlugs)));
    }

    /** @return array<string, mixed>|null */
    public static function page(string $slug): ?array
    {
        $defaults = config("collections.{$slug}");
        $overrides = self::storedOverrides($slug);

        if (! is_array($defaults) && $overrides === []) {
            return null;
        }

        return array_replace_recursive($defaults ?? [], $overrides);
    }

    /** @param  array<string, mixed>  $page */
    public static function withResolvedImages(array $page): array
    {
        return LandingPageContent::withResolvedImages($page);
    }
}
