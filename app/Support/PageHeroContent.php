<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Responsive hero overrides for pages not covered by landing/collection admins.
 */
class PageHeroContent
{
    /** @return array<string, array{label: string, group: string, defaults: callable(): array<string, mixed>, preview: callable(): string}> */
    public static function definitions(): array
    {
        $definitions = [
            'about' => [
                'label' => 'About',
                'group' => 'Core pages',
                'defaults' => fn () => data_get(config('about'), 'hero', []),
                'preview' => fn () => url('/about'),
            ],
            'professionals' => [
                'label' => 'Professionals',
                'group' => 'Core pages',
                'defaults' => fn () => data_get(config('professionals'), 'hero', []),
                'preview' => fn () => route('professionals.index'),
            ],
            'mirror-frames' => [
                'label' => 'Mirror Frames',
                'group' => 'Shop collections',
                'defaults' => fn () => data_get(config('mirror-frames'), 'hero', []),
                'preview' => fn () => route('shop.mirror-frames.index'),
            ],
        ];

        foreach (array_keys(config('service-pages', [])) as $slug) {
            $definitions['service:'.$slug] = [
                'label' => 'Studio service: '.ucwords(str_replace('-', ' ', $slug)),
                'group' => 'Studio services',
                'defaults' => fn () => data_get(config("service-pages.{$slug}"), 'hero', []),
                'preview' => fn () => ($studioUrl = StorefrontRoutes::studioUrlForService($slug))
                    ? route('studio.show', $studioUrl)
                    : route('services.show', $slug),
            ];
        }

        return $definitions;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::definitions());
    }

    public static function label(string $slug): string
    {
        return self::definitions()[$slug]['label'] ?? $slug;
    }

    public static function previewUrl(string $slug): string
    {
        $definition = self::definitions()[$slug] ?? null;

        return $definition ? ($definition['preview'])() : url('/');
    }

    /** @return array<string, mixed> */
    public static function defaultHero(string $slug): array
    {
        $definition = self::definitions()[$slug] ?? null;
        if (! $definition) {
            return [];
        }

        $defaults = ($definition['defaults'])();

        return is_array($defaults) ? $defaults : [];
    }

    /** @return array<string, mixed> */
    public static function hero(string $slug): array
    {
        abort_unless(in_array($slug, self::slugs(), true), 404);

        $defaults = self::defaultHero($slug);
        $overrides = [];

        if (Schema::hasTable('site_settings')) {
            $all = SiteSetting::getValue('page_heroes', []) ?? [];
            $overrides = is_array($all[$slug] ?? null) ? $all[$slug] : [];
        }

        return LandingPageContent::mergePage($defaults, $overrides);
    }

    /** @return array<string, mixed> */
    public static function heroWithResolvedImages(string $slug): array
    {
        return LandingPageContent::withResolvedImages(['hero' => self::hero($slug)])['hero'] ?? [];
    }

    /** @return array<string, mixed>|null */
    public static function serviceHero(string $serviceSlug): ?array
    {
        $key = 'service:'.$serviceSlug;
        if (! in_array($key, self::slugs(), true)) {
            return null;
        }

        $hero = self::heroWithResolvedImages($key);

        return $hero !== [] ? $hero : null;
    }
}
