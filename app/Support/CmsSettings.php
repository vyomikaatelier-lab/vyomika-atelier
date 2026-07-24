<?php

namespace App\Support;

use App\Models\Exhibition;
use App\Models\LegalPage;
use App\Models\SiteSetting;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Schema;

class CmsSettings
{
    public static function hydrate(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $brand = SiteSetting::getValue('brand');
        if (is_array($brand)) {
            config(['site.brand' => array_merge(config('site.brand', []), $brand)]);
        }

        $social = SiteSetting::getValue('social');
        if (is_array($social)) {
            config(['site.social' => array_merge(config('site.social', []), $social)]);
        }

        $seo = SiteSetting::getValue('seo');
        if (is_array($seo)) {
            config(['site.seo' => array_merge(config('site.seo', []), $seo)]);
        }

        $store = SiteSetting::getValue('store');
        if (is_array($store)) {
            config(['site.store' => array_merge(config('site.store', []), $store)]);
        }

        $nav = SiteSetting::getValue('nav');
        if (is_array($nav) && $nav !== []) {
            config(['site.nav' => $nav]);
        }

        $hero = SiteSetting::getValue('hero');
        if (is_array($hero) && $hero !== []) {
            $slides = config('site.hero.slides', []);
            if ($slides !== []) {
                if (isset($hero['slides']) && is_array($hero['slides'])) {
                    foreach ($hero['slides'] as $index => $override) {
                        if (! is_array($override) || ! isset($slides[$index])) {
                            continue;
                        }
                        $slides[$index] = self::mergeHeroSlide($slides[$index], $override);
                    }
                } else {
                    $slides[0] = self::mergeHeroSlide($slides[0], [
                        'title' => $hero['title'] ?? null,
                        'description' => $hero['subtitle'] ?? null,
                        'image' => $hero['image'] ?? null,
                    ]);
                }

                config(['site.hero.slides' => $slides]);
            }
        }

        $homepage = SiteSetting::getValue('homepage');
        if (is_array($homepage) && $homepage !== []) {
            if (isset($homepage['announcement']) && is_array($homepage['announcement'])) {
                config(['site.announcement' => array_merge(config('site.announcement', []), $homepage['announcement'])]);
            }
        }

        $collectionPages = SiteSetting::getValue('collection_pages');
        if (is_array($collectionPages) && $collectionPages !== []) {
            config(['collections' => array_replace_recursive(config('collections', []), $collectionPages)]);
        }

        $business = SiteSetting::getValue('business');
        if (is_array($business)) {
            $business = array_filter($business, fn ($value) => filled($value));
            config(['legal.business' => array_merge(config('legal.business', []), $business)]);
        }

        $legalUpdated = SiteSetting::getValue('legal_last_updated');
        if (filled($legalUpdated)) {
            config(['legal.last_updated' => $legalUpdated]);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function exhibitions(): array
    {
        if (! Schema::hasTable('exhibitions')) {
            return config('about.exhibitions.events', []);
        }

        $rows = Exhibition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('year')
            ->get();

        if ($rows->isEmpty()) {
            return config('about.exhibitions.events', []);
        }

        return $rows->map(fn (Exhibition $event) => [
            'slug' => $event->slug,
            'name' => $event->name,
            'location' => $event->locationLabel(),
            'year' => $event->year,
            'summary' => $event->description,
            'images' => $event->gallery ?? array_filter([$event->cover_image]),
        ])->all();
    }

    /** @param  array<string, mixed>  $slide
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private static function mergeHeroSlide(array $slide, array $override): array
    {
        foreach (['kicker', 'title', 'description', 'cta_label', 'cta_href'] as $field) {
            if (filled($override[$field] ?? null)) {
                $slide[$field] = $override[$field];
            }
        }

        if (filled($override['image'] ?? null)) {
            $slide['image'] = MediaUrl::resolve($override['image']) ?? $override['image'];
        }

        return $slide;
    }

    public static function legalPage(string $slug): ?array
    {
        if (! Schema::hasTable('legal_pages')) {
            return config("legal.pages.{$slug}");
        }

        $page = LegalPage::query()->where('slug', $slug)->first();
        if (! $page) {
            return config("legal.pages.{$slug}");
        }

        return [
            'title' => $page->title,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'sections' => $page->sections ?? [],
            'content_updated_at' => optional($page->content_updated_at)->format('j F Y'),
        ];
    }
}
