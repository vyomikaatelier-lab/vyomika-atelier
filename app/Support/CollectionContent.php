<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class CollectionContent
{
    private const MIRROR_FRAMES_SLUG = 'mirror-frames';

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
        if (! self::siteSettingsAvailable()) {
            return [];
        }

        $pages = SiteSetting::getValue('collection_pages', []) ?? [];
        if (! is_array($pages)) {
            return [];
        }

        $merged = [];

        if ($slug === self::MIRROR_FRAMES_SLUG) {
            $pageHeroes = SiteSetting::getValue('page_heroes', []) ?? [];
            $legacyHero = is_array($pageHeroes[$slug] ?? null) ? $pageHeroes[$slug] : [];
            if ($legacyHero !== []) {
                $merged = array_replace_recursive($merged, ['hero' => $legacyHero]);
            }
        }

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
        $configSlugs[] = self::MIRROR_FRAMES_SLUG;
        $overrideSlugs = [];

        if (self::siteSettingsAvailable()) {
            $overrideSlugs = array_keys(self::normalizeStoredPages(SiteSetting::getValue('collection_pages', []) ?? []));
        }

        return array_values(array_unique(array_merge($configSlugs, $overrideSlugs)));
    }

    /** @return array<string, mixed>|null */
    public static function configDefaults(string $slug): ?array
    {
        if ($slug === self::MIRROR_FRAMES_SLUG) {
            $defaults = config('mirror-frames');

            return is_array($defaults) ? $defaults : null;
        }

        $defaults = config("collections.{$slug}");

        return is_array($defaults) ? $defaults : null;
    }

    public static function configHeroLayout(string $slug): string
    {
        return (string) data_get(self::configDefaults($slug), 'hero.hero_layout', 'default');
    }

    /** @return array<string, mixed>|null */
    public static function page(string $slug): ?array
    {
        $defaults = self::configDefaults($slug);
        $overrides = self::storedOverrides($slug);

        if (! is_array($defaults) && $overrides === []) {
            return null;
        }

        $page = array_replace_recursive($defaults ?? [], $overrides);
        $hero = data_get($page, 'hero');

        if (is_array($hero)) {
            $page['hero'] = array_merge($hero, [
                'hero_layout' => self::resolvedHeroLayout($slug),
            ]);
        }

        return $page;
    }

    /**
     * Hero payload for collection admin forms (canonical override, else config default).
     *
     * @return array<string, mixed>
     */
    public static function heroForAdmin(string $slug): array
    {
        $hero = data_get(self::page($slug), 'hero', []);

        return is_array($hero) ? $hero : [];
    }

    /**
     * Resolve hero_layout from the canonical slug's stored override, falling back to config.
     * Legacy alias keys (e.g. metal-furniture) must not override the config default.
     */
    public static function resolvedHeroLayout(string $slug): string
    {
        $configLayout = self::configHeroLayout($slug);

        if (self::siteSettingsAvailable()) {
            $pages = SiteSetting::getValue('collection_pages', []) ?? [];
            $canonicalStoredHero = data_get(is_array($pages) ? $pages : [], "{$slug}.hero", []);

            if (is_array($canonicalStoredHero)
                && array_key_exists('hero_layout', $canonicalStoredHero)
                && filled($canonicalStoredHero['hero_layout'])) {
                $stored = (string) $canonicalStoredHero['hero_layout'];

                if ($configLayout === 'compact' && $stored === 'default') {
                    return 'compact';
                }

                return $stored;
            }
        }

        return $configLayout;
    }

    /**
     * Normalize a hero payload before persisting — legacy "default" must not override config compact.
     *
     * @param  array<string, mixed>  $hero
     * @return array<string, mixed>
     */
    public static function normalizeStoredHero(string $slug, array $hero): array
    {
        if (($hero['hero_layout'] ?? '') === 'default' && self::configHeroLayout($slug) === 'compact') {
            $hero['hero_layout'] = 'compact';
        }

        return $hero;
    }

    /** @param  array<string, mixed>  $page */
    public static function withResolvedImages(array $page): array
    {
        return LandingPageContent::withResolvedImages($page);
    }

    private static function siteSettingsAvailable(): bool
    {
        return ! PackageDiscovery::running() && Schema::hasTable('site_settings');
    }
}
