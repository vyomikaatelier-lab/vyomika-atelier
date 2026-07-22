<?php

namespace App\Support;

use App\Models\Exhibition;
use App\Models\LegalPage;
use App\Models\SiteSetting;
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
