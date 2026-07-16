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
            'bespoke-metal-furniture',
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
        $slugs = collect(static::catalogFor($service->slug))->pluck('slug')->filter()->values();

        if ($slugs->isEmpty()) {
            return static::queryFor($service)->orderBy('name')->get();
        }

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        return $slugs
            ->map(fn (string $slug) => $products->get($slug))
            ->filter()
            ->values();
    }

    public static function queryFor(Service $service): Builder
    {
        $query = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->whereIn('slug', $service->relatedCategorySlugs()));

        return match ($service->slug) {
            'slim-profile-door-system' => $query->where(fn ($q) => $q
                ->where('slug', 'like', '%door%')
                ->orWhere('slug', 'like', '%pivot%')
                ->orWhere('slug', 'like', '%sliding%')
                ->orWhere('slug', 'like', '%hinged%')),
            'main-entrance-pvd-doors' => $query->where(fn ($q) => $q
                ->where('slug', 'like', '%door%')
                ->orWhere('slug', 'like', '%handle%')
                ->orWhere('slug', 'like', '%pull%')),
            'rack-systems-metal-pvd' => $query->where('slug', 'like', '%rack%'),
            'bespoke-metal-furniture' => $query->where(fn ($q) => $q
                ->where('slug', 'not like', '%door%')
                ->where('slug', 'not like', '%rack%')
                ->where('slug', 'not like', '%handle%')
                ->where('slug', 'not like', '%partition%')
                ->where('slug', 'not like', '%panel%')
                ->where('slug', 'not like', '%divider%')),
            default => $query,
        };
    }

    public static function galleryHeading(Service $service): string
    {
        return match ($service->slug) {
            'partitions' => 'Explore Partition Designs',
            'slim-profile-door-system' => 'Explore Door Designs',
            'main-entrance-pvd-doors' => 'Explore Entrance Doors',
            'rack-systems-metal-pvd' => 'Explore Rack Designs',
            'bespoke-metal-furniture' => 'Explore Furniture Designs',
            default => 'Design Gallery',
        };
    }

    public static function galleryHeroSubtitle(Service $service, int $count): string
    {
        $label = match ($service->slug) {
            'partitions' => 'partition designs',
            'slim-profile-door-system', 'main-entrance-pvd-doors' => 'door designs',
            'rack-systems-metal-pvd' => 'rack designs',
            'bespoke-metal-furniture' => 'furniture designs',
            default => 'designs',
        };

        $action = $service->slug === 'bespoke-metal-furniture'
            ? 'select a piece to request a quote'
            : 'select a style to configure & order';

        return $count.' '.$label.' — '.$action;
    }

    public static function galleryCtaLabel(Service $service): string
    {
        return $service->slug === 'corten-steel-facade' ? 'Request Quote' : 'Order Now';
    }
}
