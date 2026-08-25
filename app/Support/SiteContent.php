<?php

namespace App\Support;

class SiteContent
{
    /** @return array<string, string> */
    public static function homepageSectionLabels(): array
    {
        return [
            'category_banners' => 'Category banners',
            'best_sellers' => 'Best sellers product grid',
            'trending' => 'Trending products',
            'spotlights' => 'Spotlights',
            'cta_band' => 'CTA band',
            'testimonials' => 'Testimonials',
            'blog' => 'Blog strip',
            'trust_badges' => 'Trust badges',
        ];
    }

    public static function homepageSectionEnabled(string $key): bool
    {
        $sections = self::get('homepage.sections', []);

        if (! is_array($sections)) {
            return true;
        }

        return ($sections[$key] ?? true) !== false;
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        $data = config('site', []);

        if ($key === null) {
            return $data;
        }

        return data_get($data, $key, $default);
    }

    public static function brand(): array
    {
        return self::arrayValue('brand');
    }

    public static function announcement(): array
    {
        return AnnouncementGuard::sanitize(self::arrayValue('announcement'));
    }

    public static function heroSlides(): array
    {
        return self::get('hero.slides', []);
    }

    public static function bestSellers(): array
    {
        return self::get('best_sellers', []);
    }

    public static function categoryBanners(): array
    {
        return self::get('category_banners', []);
    }

    public static function trending(): array
    {
        return self::get('trending', []);
    }

    public static function spotlights(): array
    {
        return self::get('spotlights', []);
    }

    public static function testimonials(): array
    {
        return self::get('testimonials', []);
    }

    public static function featuredProduct(): array
    {
        return self::get('featured_product', []);
    }

    public static function blogSection(): array
    {
        return self::get('blog', []);
    }

    public static function trustBadges(): array
    {
        return self::get('trust_badges', []);
    }

    public static function footer(): array
    {
        $footer = self::arrayValue('footer');
        $footer['shop_links'] = StorefrontNavigation::shopLinks();
        $footer['service_links'] = StorefrontNavigation::studioLinks();

        return $footer;
    }

    public static function social(): array
    {
        return self::arrayValue('social');
    }

    /** @return array<string, mixed> */
    private static function arrayValue(string $key, array $default = []): array
    {
        $value = self::get($key, $default);

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?? $default;
        }

        return $default;
    }

    public static function formatPrice(int|float|null $amount): string
    {
        if ($amount === null) {
            return '';
        }

        return '₹' . number_format((float) $amount, 0);
    }

    public static function portfolio(): array
    {
        return self::get('portfolio', []);
    }

    public static function services(): array
    {
        return self::get('services', []);
    }

    public static function shop(): array
    {
        return self::get('shop', []);
    }

    public static function blog(): array
    {
        return self::get('blog.posts', self::get('blog', []));
    }

    public static function team(): array
    {
        return self::get('team', []);
    }
}
