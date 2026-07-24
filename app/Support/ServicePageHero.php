<?php

namespace App\Support;

use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class ServicePageHero
{
    /** @return array<string, mixed> */
    public static function stored(string $slug): array
    {
        if (! Schema::hasTable('site_settings')) {
            return [];
        }

        $all = SiteSetting::getValue('service_page_heroes', []) ?? [];

        return is_array($all[$slug] ?? null) ? $all[$slug] : [];
    }

    /** @return array<string, mixed>|null */
    public static function heroForService(Service $service): ?array
    {
        $configHero = data_get(config("service-pages.{$service->slug}"), 'hero');
        $base = is_array($configHero) ? $configHero : [
            'label' => 'Studio',
            'title' => $service->name,
            'subtitle' => $service->summary,
        ];

        if (filled($service->image)) {
            $base['image'] = $service->image;
        }

        $serviceKey = 'service:'.$service->slug;
        if (in_array($serviceKey, PageHeroContent::slugs(), true)) {
            $base = LandingPageContent::mergePage($base, PageHeroContent::hero($serviceKey));
        }

        $merged = LandingPageContent::mergePage($base, self::stored($service->slug));
        $hero = LandingPageContent::withResolvedImages(['hero' => $merged])['hero'] ?? [];

        return $hero !== [] ? $hero : null;
    }
}
