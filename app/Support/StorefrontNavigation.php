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
     * Homepage category tiles: the same published Shop + Studio sets as nav/footer.
     *
     * @return list<array{title: string, subtitle: string, href: string, image: ?string, cta: string}>
     */
    public static function homepageCategoryTiles(): array
    {
        $tiles = [];

        foreach (self::shopLinks() as $link) {
            $slug = $link['slug'] ?? ($link['params']['slug'] ?? null);
            $category = is_string($slug)
                ? Category::query()->where('slug', $slug)->first()
                : null;

            $tiles[] = [
                'title' => $link['label'],
                'subtitle' => self::plainText($category?->description ?? 'Shop collection'),
                'href' => $link['href'],
                'image' => self::categoryImage($category),
                'cta' => 'View collection',
            ];
        }

        foreach (self::studioLinks() as $link) {
            $urlSlug = $link['params']['slug'] ?? '';
            $serviceSlug = StorefrontRoutes::serviceSlugForStudioUrl((string) $urlSlug);
            $service = $serviceSlug
                ? Service::query()->where('slug', $serviceSlug)->first()
                : null;

            $tiles[] = [
                'title' => $link['label'],
                'subtitle' => self::plainText($service?->summary ?? 'Studio collection'),
                'href' => $link['href'],
                'image' => $service && filled($service->image)
                    ? (MediaUrl::resolve($service->image) ?? $service->image)
                    : null,
                'cta' => 'View collection',
            ];
        }

        return $tiles;
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
