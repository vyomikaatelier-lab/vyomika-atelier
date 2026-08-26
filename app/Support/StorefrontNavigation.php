<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;

/**
 * Single publication-aware source for Shop/Studio links on the storefront.
 *
 * Header, mobile navigation, footer, homepage category tiles, and the sitemap
 * must all read this class so Admin activation/deactivation cannot drift.
 */
class StorefrontNavigation
{
    /** @return list<string> */
    private static function genericShopLabels(): array
    {
        return [
            'all products',
            'view all products',
            'browse shop',
            'browse all shop',
        ];
    }

    /**
     * Desktop/mobile nav items. Shop and Studio children are live queries,
     * not the config/JSON child lists.
     *
     * @return list<array<string, mixed>>
     */
    public static function nav(): array
    {
        $shop = self::shopLinks();
        $studio = self::studioLinks();

        $items = [];

        foreach (config('site.nav', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = $item['label'] ?? '';

            if ($label === 'Shop') {
                if ($shop === []) {
                    continue;
                }
                $item['children'] = $shop;
                $items[] = $item;

                continue;
            }

            if ($label === 'Studio') {
                if ($studio === []) {
                    continue;
                }
                $item['children'] = $studio;
                $items[] = $item;

                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<array{label: string, route: string, params?: array<string, string>, href: string}>
     */
    public static function shopLinks(): array
    {
        if (! Schema::hasTable('categories') || ! Schema::hasTable('products')) {
            return [];
        }

        $query = ShopCatalog::applyStorefrontCategoryScope(
            Category::query()
                ->where('is_active', true)
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->where('section', Product::SECTION_SHOP)
                ->whereNotIn('slug', ProductCatalog::obsoleteCategorySlugs())
        )->whereHas('products', fn ($products) => ProductPublicationPolicy::applyGalleryScope($products));

        if (ShopCatalog::supportsHideFromNav()) {
            $query->where('hide_from_nav', false);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category) => self::shopLinkFromCategory($category))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, route: string, params?: array<string, string>, href: string}>
     */
    public static function studioLinks(): array
    {
        if (! Schema::hasTable('services') || ! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return [];
        }

        $links = [];

        foreach (StorefrontRoutes::studioServiceMap() as $urlSlug => $serviceSlug) {
            $service = Service::query()->where('slug', $serviceSlug)->first();

            if (! self::isStudioServiceListed($service)) {
                continue;
            }

            $links[] = [
                'label' => StorefrontRoutes::studioServiceLabel($serviceSlug),
                'route' => 'studio.show',
                'params' => ['slug' => $urlSlug],
                'href' => route('studio.show', $urlSlug),
            ];
        }

        return $links;
    }

    /**
     * Homepage collection row: five curated Shop + Studio tiles with self-hosted fallbacks.
     *
     * @return list<array{title: string, subtitle: string, href: string, image: string, cta: string}>
     */
    public static function homepageCategoryTiles(): array
    {
        $definitions = [
            [
                'kind' => 'shop',
                'slug' => 'mirror-frames',
                'title' => 'Mirror Frames',
                'fallback_image' => '/images/shop-heroes/mirror-frames-hero.png',
                'fallback_subtitle' => 'Designer mirrors in premium PVD finishes for living spaces and hospitality.',
            ],
            [
                'kind' => 'shop',
                'slug' => 'corner-tables',
                'title' => 'Corner Tables',
                'fallback_image' => '/images/shop-heroes/corner-tables-hero.png',
                'fallback_subtitle' => 'Sculptural side tables and accent pieces in champagne, rose gold, and matte black.',
            ],
            [
                'kind' => 'shop',
                'slug' => 'coffee-tables',
                'title' => 'Coffee Tables',
                'fallback_image' => '/images/shop-heroes/coffee-tables-hero.png',
                'fallback_subtitle' => 'Statement coffee tables engineered for modern Indian interiors.',
            ],
            [
                'kind' => 'studio',
                'url_slug' => 'pvd-partitions',
                'service_slug' => 'partitions',
                'title' => 'PVD Partitions',
                'fallback_image' => '/images/blog/heroes/glass-partitions-open-plan-hero-card.jpg',
                'fallback_subtitle' => 'Custom-made, fluted, and laser-cut PVD partition systems for offices and homes.',
            ],
            [
                'kind' => 'studio',
                'url_slug' => 'slim-profile-door-systems',
                'service_slug' => 'slim-profile-door-system',
                'title' => 'Slim Profile Door Systems',
                'fallback_image' => '/images/shop-heroes/slim-profile-doors-hero.png',
                'fallback_subtitle' => 'Ultra-slim PVD door frames with premium glass and concealed hardware.',
            ],
        ];

        $tiles = [];

        foreach ($definitions as $definition) {
            if (($definition['kind'] ?? '') === 'shop') {
                $slug = (string) ($definition['slug'] ?? '');
                if ($slug === '' || ! self::isShopCategoryListed($slug)) {
                    continue;
                }

                $category = Category::query()->where('slug', $slug)->first();
                $subtitle = self::plainText($category?->description ?? '');
                $tiles[] = [
                    'title' => $definition['title'],
                    'subtitle' => $subtitle !== '' ? $subtitle : ($definition['fallback_subtitle'] ?? ''),
                    'href' => $slug === 'mirror-frames'
                        ? route('shop.mirror-frames.index')
                        : route('shop.show', $slug),
                    'image' => self::categoryImage($category) ?? ($definition['fallback_image'] ?? ''),
                    'cta' => 'View collection',
                ];

                continue;
            }

            $urlSlug = (string) ($definition['url_slug'] ?? '');
            $serviceSlug = (string) ($definition['service_slug'] ?? '');
            $service = $serviceSlug !== ''
                ? Service::query()->where('slug', $serviceSlug)->first()
                : null;

            if (! self::isStudioServiceListed($service)) {
                continue;
            }

            $image = null;
            if ($service && filled($service->image)) {
                $image = MediaUrl::resolve($service->image) ?? $service->image;
            }

            $subtitle = self::plainText($service?->summary ?? '');
            $tiles[] = [
                'title' => $definition['title'],
                'subtitle' => $subtitle !== '' ? $subtitle : ($definition['fallback_subtitle'] ?? ''),
                'href' => route('studio.show', $urlSlug),
                'image' => $image ?? ($definition['fallback_image'] ?? ''),
                'cta' => 'View collection',
            ];
        }

        return $tiles;
    }

    public static function publicServicesRedirectUrl(?string $serviceSlug = null): string
    {
        $serviceSlug = trim((string) $serviceSlug);
        if ($serviceSlug !== '') {
            $studioUrl = StorefrontRoutes::studioUrlForService($serviceSlug);
            if ($studioUrl) {
                return route('studio.show', $studioUrl);
            }
        }

        return self::primaryPublishedShopUrl();
    }

    public static function primaryPublishedShopUrl(): string
    {
        $links = self::shopLinks();
        $preferred = StorefrontRoutes::primaryShopCategorySlug();

        foreach ($links as $link) {
            $slug = $link['slug'] ?? ($link['params']['slug'] ?? null);
            if ($slug === $preferred) {
                return $link['href'];
            }
        }

        if ($links !== []) {
            return $links[0]['href'];
        }

        return StorefrontRoutes::primaryShopUrl();
    }

    public static function primaryPublishedShopLabel(): string
    {
        $links = self::shopLinks();
        $preferred = StorefrontRoutes::primaryShopCategorySlug();

        foreach ($links as $link) {
            $slug = $link['slug'] ?? ($link['params']['slug'] ?? null);
            if ($slug === $preferred) {
                return $link['label'];
            }
        }

        if ($links !== []) {
            return $links[0]['label'];
        }

        return StorefrontRoutes::shopCategoryLabel(StorefrontRoutes::primaryShopCategorySlug());
    }

    /**
     * Resolve an Admin/config CTA that points at the retired generic /shop listing.
     *
     * @return array{href: string, label: string}
     */
    public static function resolveCta(?string $href, ?string $label = null): array
    {
        $href = trim((string) $href);
        $label = $label === null ? '' : trim($label);

        if ($href === '' || self::isGenericShopUrl($href)) {
            $href = self::primaryPublishedShopUrl();
        }

        if ($label === '' || self::isGenericShopLabel($label)) {
            $label = 'Shop '.self::primaryPublishedShopLabel();
        }

        return [
            'href' => $href,
            'label' => $label,
        ];
    }

    public static function resolveHref(?string $href): string
    {
        return self::resolveCta($href, null)['href'];
    }

    public static function isGenericShopUrl(?string $href): bool
    {
        $href = trim((string) $href);
        if ($href === '') {
            return false;
        }

        $path = parse_url($href, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return false;
        }

        return rtrim($path, '/') === '/shop';
    }

    public static function isGenericShopLabel(?string $label): bool
    {
        $normalized = strtolower(trim((string) $label));

        return in_array($normalized, self::genericShopLabels(), true);
    }

    public static function isShopCategoryListed(string $slug): bool
    {
        foreach (self::shopLinks() as $link) {
            if (($link['slug'] ?? ($link['params']['slug'] ?? null)) === $slug) {
                return true;
            }
        }

        return false;
    }

    public static function isStudioServiceListed(?Service $service): bool
    {
        if (! $service || ! $service->is_active || ! filled($service->slug)) {
            return false;
        }

        if (! StorefrontRoutes::studioUrlForService($service->slug)) {
            return false;
        }

        if (ShopCatalog::supportsHideFromNav() && $service->hide_from_nav) {
            return false;
        }

        return self::serviceHasPublicProducts($service);
    }

    /**
     * @return array{label: string, route: string, params?: array<string, string>, href: string}|null
     */
    private static function shopLinkFromCategory(Category $category): ?array
    {
        $slug = trim((string) $category->slug);
        if ($slug === '') {
            return null;
        }

        $isMirrorFrames = $slug === 'mirror-frames';

        return [
            'label' => StorefrontRoutes::isShopCategory($slug)
                ? StorefrontRoutes::shopCategoryLabel($slug)
                : $category->name,
            'route' => $isMirrorFrames ? 'shop.mirror-frames.index' : 'shop.show',
            'params' => $isMirrorFrames ? [] : ['slug' => $slug],
            'slug' => $slug,
            'href' => $isMirrorFrames
                ? route('shop.mirror-frames.index')
                : route('shop.show', $slug),
        ];
    }

    private static function serviceHasPublicProducts(Service $service): bool
    {
        $categorySlugs = $service->relatedCategorySlugs();
        if ($categorySlugs === []) {
            return false;
        }

        return ProductPublicationPolicy::applyGalleryScope(Product::query())
            ->whereHas('category', fn ($category) => $category
                ->where('is_active', true)
                ->whereIn('slug', $categorySlugs))
            ->exists();
    }

    private static function categoryImage(?Category $category): ?string
    {
        if (! $category) {
            return null;
        }

        if (filled($category->image)) {
            return MediaUrl::resolve($category->image) ?? $category->image;
        }

        $page = CollectionContent::page($category->slug);
        $heroImage = data_get($page, 'hero.image');
        if (is_string($heroImage) && $heroImage !== '') {
            return MediaUrl::resolve($heroImage) ?? $heroImage;
        }

        $og = $category->og_image;

        return filled($og) ? (MediaUrl::resolve($og) ?? $og) : null;
    }

    private static function plainText(?string $value): string
    {
        $text = trim(strip_tags((string) $value));

        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > 90 ? mb_substr($text, 0, 87).'…' : $text;
    }
}
