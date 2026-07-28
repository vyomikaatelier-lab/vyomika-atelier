<?php

namespace App\Support;

use App\Models\Exhibition;
use App\Models\LegalPage;
use App\Models\SiteSetting;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Schema;

class CmsSettings
{
    /**
     * Records what the last hydration actually did. A storefront that renders
     * config seed content is indistinguishable from one that has no saved
     * settings, so the outcome has to be inspectable after the fact.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $status = null;

    public static function hydrate(): void
    {
        self::$status = [
            'ran' => true,
            'table_found' => false,
            'applied' => [],
            'error' => null,
        ];

        if (! Schema::hasTable('site_settings')) {
            return;
        }

        self::$status['table_found'] = true;

        $brand = SiteSetting::getValue('brand');
        if (is_array($brand)) {
            config(['site.brand' => array_merge(config('site.brand', []), $brand)]);
            self::$status['applied'][] = 'brand';
        }

        $social = SiteSetting::getValue('social');
        if (is_array($social)) {
            config(['site.social' => array_merge(config('site.social', []), $social)]);
            self::$status['applied'][] = 'social';
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
                self::$status['applied'][] = 'hero';
            }
        }

        $homepage = SiteSetting::getValue('homepage');
        if (is_array($homepage) && is_array($homepage['announcement'] ?? null)) {
            // The stored announcement is authoritative: once an admin has saved
            // the homepage, blanking the text must hide the bar rather than fall
            // back to the config default.
            config(['site.announcement' => array_merge(
                ['text' => null, 'link_label' => null, 'link_href' => null],
                $homepage['announcement']
            )]);
            self::$status['applied'][] = 'homepage.announcement';
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

    /**
     * What the last hydration in this process achieved. `ran => false` means the
     * storefront is rendering config seed content because hydration never
     * happened, which looks identical to "the admin saved nothing".
     *
     * @return array<string, mixed>
     */
    public static function hydrationStatus(): array
    {
        return self::$status ?? [
            'ran' => false,
            'table_found' => false,
            'applied' => [],
            'error' => null,
        ];
    }

    /** Keep a failed boot-time hydration visible instead of losing the reason. */
    public static function recordHydrationFailure(\Throwable $e): void
    {
        self::$status = [
            'ran' => true,
            'table_found' => self::$status['table_found'] ?? false,
            'applied' => self::$status['applied'] ?? [],
            'error' => $e->getMessage(),
        ];
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
            // Config seed content is only a first-deploy preview. Once the admin
            // owns rows here, hiding them all must empty the section rather than
            // resurrect the hardcoded events.
            return Exhibition::query()->exists()
                ? []
                : config('about.exhibitions.events', []);
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

        foreach (['image', 'image_mobile', 'image_tablet'] as $imageField) {
            if (filled($override[$imageField] ?? null)) {
                $slide[$imageField] = MediaUrl::resolve($override[$imageField]) ?? $override[$imageField];
            }
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
