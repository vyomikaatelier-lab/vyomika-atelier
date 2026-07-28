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

        $catalog = CatalogData::serviceGallery();

        return $catalog[$serviceSlug] ?? [];
    }

    /**
     * The catalog slug list is a curated running order, not an allow-list: any
     * other product the admin filed under this service's categories is
     * appended after it.
     *
     * @return Collection<int, Product>
     */
    public static function productsFor(Service $service): Collection
    {
        $slugs = ProductCatalog::productSlugsForService($service->slug);
        $fromCategories = static::categoryQueryFor($service)->orderBy('name')->get();

        if ($slugs === []) {
            return $fromCategories;
        }

        $curated = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_gallery_visible', true)
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        return collect($slugs)
            ->map(fn (string $slug) => $curated->get($slug))
            ->filter()
            ->concat($fromCategories)
            ->unique('id')
            ->values();
    }

    public static function queryFor(Service $service): Builder
    {
        $slugs = ProductCatalog::productSlugsForService($service->slug);
        $categorySlugs = $service->relatedCategorySlugs();

        $query = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_gallery_visible', true);

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

        return Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_gallery_visible', true)
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
        return $service->slug === 'corten-steel-facade' ? 'Request Quote' : 'Order Now';
    }
}
