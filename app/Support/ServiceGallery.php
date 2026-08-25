<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ServiceGallery
{
    /** @return list<string> */
    public static function galleryServiceSlugs(): array
    {
        return [
            'partitions',
            'slim-profile-door-system',
            'main-entrance-pvd-doors',
            'rack-systems-metal-pvd',
        ];
    }

    public static function usesGalleryOnlyLayout(Service $service): bool
    {
        return $service->slug !== 'corten-steel-facade';
    }

    /** @return list<string> */
    public static function portraitGalleryServiceSlugs(): array
    {
        return self::galleryServiceSlugs();
    }

    public static function usesPortraitGalleryLayout(Service|string $service): bool
    {
        return true;
    }

    /** @return list<array<string, mixed>> */
    public static function catalogFor(string $serviceSlug): array
    {
        if ($serviceSlug === 'partitions') {
            return require database_path('data/partition-gallery-products.php');
        }

        $catalog = CatalogData::serviceGallery();

        return $catalog[$serviceSlug] ?? [];
    }

    /**
     * Products for a studio service gallery, ordered by admin sort_order.
     * Catalog slugs extend the category scope but no longer dictate order.
     *
     * @return Collection<int, Product>
     */
    public static function productsFor(Service $service): Collection
    {
        $slugs = ProductCatalog::productSlugsForService($service->slug);
        $categorySlugs = $service->relatedCategorySlugs();

        if ($slugs === [] && $categorySlugs === []) {
            return collect();
        }

        $query = ProductPublicationPolicy::applyGalleryScope(
            Product::query()->with('category')
        )->unlessHiddenForStock();

        $query->where(function ($q) use ($slugs, $categorySlugs) {
            $hasCategories = $categorySlugs !== [];

            if ($hasCategories) {
                $q->whereHas('category', fn ($c) => $c->whereIn('slug', $categorySlugs));
            }

            if ($slugs !== []) {
                if ($hasCategories) {
                    $q->orWhereIn('slug', $slugs);
                } else {
                    $q->whereIn('slug', $slugs);
                }
            }
        });

        return $query->orderedForDisplay()->get();
    }

    public static function queryFor(Service $service): Builder
    {
        $slugs = ProductCatalog::productSlugsForService($service->slug);
        $categorySlugs = $service->relatedCategorySlugs();

        $query = ProductPublicationPolicy::applyGalleryScope(
            Product::query()->with('category')
        )->unlessHiddenForStock();

        if ($slugs === []) {
            return $query->whereHas('category', fn ($q) => $q->whereIn('slug', $categorySlugs));
        }

        return $query->where(fn ($q) => $q
            ->whereIn('slug', $slugs)
            ->orWhereHas('category', fn ($c) => $c->whereIn('slug', $categorySlugs)));
    }

    /** @return Builder<Product> */
    private static function categoryQueryFor(Service $service): Builder
    {
        $categorySlugs = $service->relatedCategorySlugs();

        return ProductPublicationPolicy::applyGalleryScope(
            Product::query()->with('category')
        )->unlessHiddenForStock()
            ->when(
                $categorySlugs === [],
                fn (Builder $q) => $q->whereRaw('0 = 1'),
                fn (Builder $q) => $q->whereHas('category', fn ($c) => $c->whereIn('slug', $categorySlugs))
            );
    }

    public static function galleryHeading(Service $service): string
    {
        return match ($service->slug) {
            'partitions' => 'Explore Partition Designs',
            'slim-profile-door-system' => 'Explore Door Designs',
            'main-entrance-pvd-doors' => 'Explore Entrance Doors',
            'rack-systems-metal-pvd' => 'Explore Rack Designs',
            default => 'Design Gallery',
        };
    }

    public static function galleryHeroSubtitle(Service $service, int $count = 0): string
    {
        return 'Select a style to configure & order';
    }

    public static function galleryCtaLabel(Service $service): string
    {
        return 'Request Quote';
    }
}
