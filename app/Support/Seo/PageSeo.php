<?php

namespace App\Support\Seo;

use App\Models\SiteSetting;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Schema;

/**
 * Normalised SEO payload for storefront pages.
 *
 * @phpstan-type SeoArray array{
 *   title?: string|null,
 *   description?: string|null,
 *   canonical?: string|null,
 *   robots?: string|null,
 *   og_title?: string|null,
 *   og_description?: string|null,
 *   og_image?: string|null,
 *   og_type?: string|null,
 *   primary_keyword?: string|null
 * }
 */
class PageSeo
{
    /** @param SeoArray $overrides */
    public static function make(array $overrides = []): array
    {
        $defaults = self::siteDefaults();

        $title = filled($overrides['title'] ?? null)
            ? (string) $overrides['title']
            : (string) ($defaults['title'] ?? 'Vyomika Atelier');

        $description = filled($overrides['description'] ?? null)
            ? (string) $overrides['description']
            : (string) ($defaults['description'] ?? '');

        $canonical = filled($overrides['canonical'] ?? null)
            ? (string) $overrides['canonical']
            : url()->current();

        $ogImage = $overrides['og_image'] ?? $defaults['og_image'] ?? null;
        if (is_string($ogImage) && $ogImage !== '') {
            $ogImage = MediaUrl::resolve($ogImage) ?? $ogImage;
        } else {
            $ogImage = null;
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => filled($overrides['robots'] ?? null) ? (string) $overrides['robots'] : 'index,follow',
            'og_title' => filled($overrides['og_title'] ?? null) ? (string) $overrides['og_title'] : $title,
            'og_description' => filled($overrides['og_description'] ?? null) ? (string) $overrides['og_description'] : $description,
            'og_image' => $ogImage,
            'og_type' => filled($overrides['og_type'] ?? null) ? (string) $overrides['og_type'] : 'website',
            'primary_keyword' => $overrides['primary_keyword'] ?? null,
        ];
    }

    /** @return array{title?: string, description?: string, og_image?: string|null} */
    public static function siteDefaults(): array
    {
        $seo = config('site.seo', []);
        if ((! is_array($seo) || $seo === []) && Schema::hasTable('site_settings')) {
            $seo = SiteSetting::getValue('seo', []) ?? [];
        }
        $seo = is_array($seo) ? $seo : [];

        return [
            'title' => $seo['default_title'] ?? (config('site.brand.name', 'Vyomika Atelier').' — PVD Partitions & Metal Furniture'),
            'description' => $seo['default_description'] ?? (config('site.brand.name', 'Vyomika Atelier').' designs and fabricates PVD partitions, slim profile doors, entrance doors, railings, Corten Steel and bespoke metal furniture for projects across India.'),
            'og_image' => $seo['default_og_image'] ?? null,
        ];
    }

    /** @return array{ga4?: string|null, gsc?: string|null} */
    public static function analytics(): array
    {
        $analytics = [];
        if (Schema::hasTable('site_settings')) {
            $analytics = SiteSetting::getValue('analytics', []) ?? [];
        }
        $analytics = is_array($analytics) ? $analytics : [];

        return [
            'ga4' => filled($analytics['ga4_measurement_id'] ?? null) ? (string) $analytics['ga4_measurement_id'] : null,
            'gsc' => filled($analytics['gsc_verification'] ?? null) ? (string) $analytics['gsc_verification'] : null,
        ];
    }
}
