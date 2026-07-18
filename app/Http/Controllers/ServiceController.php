<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceDesign;
use App\Support\CortenContent;
use App\Support\ServiceGallery;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::where('is_active', true)->orderBy('name')->get();

        return view('services.index', compact('services'));
    }

    public function show(string $slug)
    {
        if ($slug === 'bespoke-metal-furniture') {
            return redirect()->route('shop.show', 'bespoke-metal-furniture', 301);
        }

        $service = Service::where('slug', $slug)->where('is_active', true)
            ->with(['designs' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        $related = $this->relatedProducts($service);

        $galleryProducts = collect();
        if ($service->usesGalleryOnlyLayout()) {
            $galleryProducts = ServiceGallery::productsFor($service);
        }

        if ($service->slug === 'corten-steel-facade') {
            return view('services.corten-steel', [
                'service' => $service,
                'page' => CortenContent::all(),
            ]);
        }

        return view('services.show', compact('service', 'related', 'galleryProducts'));
    }

    public function design(string $serviceSlug, string $designSlug)
    {
        if (in_array($serviceSlug, Service::noDesignPageSlugs(), true)) {
            abort(404);
        }

        $service = Service::where('slug', $serviceSlug)->where('is_active', true)->firstOrFail();
        $design = ServiceDesign::where('service_id', $service->id)
            ->where('slug', $designSlug)
            ->where('is_active', true)
            ->firstOrFail();

        if ($service->usesGalleryOnlyLayout()) {
            if ($slug = $design->resolvedProductSlug()) {
                return redirect()->route('shop.show', $slug);
            }

            return redirect()->route('services.show', $serviceSlug);
        }

        $related = $this->relatedProducts($service);

        return view('services.design', compact('service', 'design', 'related'));
    }

    private function relatedProducts(Service $service)
    {
        $slugs = $service->relatedCategorySlugs();
        if ($slugs === []) {
            return collect();
        }

        return Product::query()
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->whereIn('slug', $slugs))
            ->inRandomOrder()
            ->limit(4)
            ->get();
    }
}
