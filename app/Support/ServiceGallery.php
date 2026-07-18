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

    /** @return list<array<string, mixed>> */
    public static function catalogFor(string $serviceSlug): array
    {
        if ($serviceSlug === 'partitions') {
            return require database_path('data/partition-gallery-products.php');
        }

        $catalog = require database_path('data/service-gallery-catalog.php');

        return $catalog[$serviceSlug] ?? [];
    }

    /** @return Collection<int, Product> */
    public static function productsFor(Service $service): Collection
    {
        $slugs = ProductCatalog::productSlugsForService($service->slug);

        if ($slugs !== []) {
            $products = Product::query()
                ->with('category')
                ->where('is_active', true)
                ->whereIn('slug', $slugs)
                ->get()
                ->keyBy('slug');

            return collect($slugs)
                ->map(fn (string $slug) => $products->get($slug))
                ->filter()
                ->values();
        }

        return static::queryFor($service)->orderBy('name')->get();
    }

    public static function queryFor(Service $service): Builder
    {
        $slugs = ProductCatalog::productSlugsForService($service->slug);

        if ($slugs !== []) {
            return Product::query()
                ->with('category')
                ->where('is_active', true)
                ->whereIn('slug', $slugs);
        }

        return Product::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->whereIn('slug', $service->relatedCategorySlugs()));
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
        return $service->slug === 'corten-steel-facade' ? 'Request Quote' : 'Order Now';
    }
}
