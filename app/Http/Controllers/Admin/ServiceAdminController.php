<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceDesign;
use App\Models\SiteSetting;
use App\Support\ResponsiveHero;
use App\Support\ServicePageHero;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index(Request $request)
    {
        $query = Service::query()->orderBy('name');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $services = $query->withCount('designs')->paginate(20)->withQueryString();

        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.form', [
            'products' => Product::query()->orderBy('name')->pluck('name', 'slug'),
            'hero' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateService($request);
        $validated['slug'] = Str::slug($request->input('slug') ?: $validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['has_calculator'] = $request->boolean('has_calculator');
        $validated['has_designs'] = $request->boolean('has_designs');
        $validated['image'] = $this->resolveServiceHeroImages($request, null)['image'] ?? $this->resolveImageField($request, 'image_file', 'image', null, 'services');

        $service = Service::create($validated);
        $this->syncDesigns($request, $service);

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(Service $service)
    {
        $service->load('designs');

        return view('admin.services.form', [
            'service' => $service,
            'products' => Product::query()->orderBy('name')->pluck('name', 'slug'),
            'hero' => $this->serviceHeroForForm($service),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $validated = $this->validateService($request, $service);
        $validated['slug'] = Str::slug($request->input('slug') ?: $validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['has_calculator'] = $request->boolean('has_calculator');
        $validated['has_designs'] = $request->boolean('has_designs');
        $validated['image'] = $this->resolveServiceHeroImages($request, $service)['image'] ?? $this->resolveImageField($request, 'image_file', 'image', $service->image, 'services');

        $service->update($validated);
        $this->syncDesigns($request, $service);

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        $this->deleteStoredPath($service->image);

        foreach ($service->designs as $design) {
            $this->deleteStoredPath($design->image);
        }

        $service->delete();

        return redirect()->route('admin.services.index')->with('success', 'Service deleted.');
    }

    private function validateService(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('services', 'slug')->ignore($service?->id)],
            'summary' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'lead_form' => 'required|in:popup,inline',
            'rate_per_sqft' => 'nullable|numeric|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            ...ResponsiveHero::flatValidationRules('hero'),
            'designs' => 'nullable|array',
            'designs.*.id' => 'nullable|integer|exists:service_designs,id',
            'designs.*.name' => 'nullable|string|max:255',
            'designs.*.slug' => 'nullable|string|max:255',
            'designs.*.description' => 'nullable|string|max:2000',
            'designs.*.product_slug' => 'nullable|string|max:255',
            'designs.*.image' => 'nullable|string|max:500',
            'designs.*.image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);
    }

    private function syncDesigns(Request $request, Service $service): void
    {
        $rows = $request->input('designs', []);
        $keepIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $design = null;
            if (! empty($row['id'])) {
                $design = ServiceDesign::query()
                    ->where('service_id', $service->id)
                    ->whereKey($row['id'])
                    ->first();
            }

            $slug = Str::slug($row['slug'] ?? $name);
            $image = $design?->image;

            if ($request->hasFile("designs.{$index}.image_file")) {
                $uploaded = $this->storeUpload($request, "designs.{$index}.image_file", 'service-designs');
                if ($uploaded) {
                    $this->deleteStoredPath($image);
                    $image = $uploaded;
                }
            } elseif (filled($row['image'] ?? null)) {
                $image = $row['image'];
            }

            $payload = [
                'name' => $name,
                'slug' => $slug,
                'description' => $row['description'] ?? null,
                'product_slug' => filled($row['product_slug'] ?? null) ? $row['product_slug'] : null,
                'image' => $image,
                'is_active' => $request->boolean("designs.{$index}.is_active"),
            ];

            if ($design) {
                $design->update($payload);
                $keepIds[] = $design->id;
            } else {
                $created = $service->designs()->create($payload);
                $keepIds[] = $created->id;
            }
        }

        $service->designs()
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(function (ServiceDesign $design) {
                $this->deleteStoredPath($design->image);
                $design->delete();
            });
    }

    /** @return array<string, mixed> */
    private function serviceHeroForForm(Service $service): array
    {
        $configHero = data_get(config("service-pages.{$service->slug}"), 'hero', []);
        $base = is_array($configHero) ? $configHero : [];
        if (filled($service->image)) {
            $base['image'] = $service->image;
        }

        return array_merge($base, ServicePageHero::stored($service->slug));
    }

    /** @return array<string, string|null> */
    private function resolveServiceHeroImages(Request $request, ?Service $service): array
    {
        $slug = $service?->slug ?? Str::slug((string) $request->input('slug', $request->input('name', 'service')));
        $current = $this->serviceHeroForForm($service ?? new Service(['slug' => $slug, 'image' => null]));
        $heroImages = $this->persistResponsiveHeroFlatFields($request, 'hero', $current, 'services');

        $pages = SiteSetting::getValue('service_page_heroes', []) ?? [];
        if ($heroImages !== []) {
            $pages[$slug] = array_merge($pages[$slug] ?? [], $heroImages);
            SiteSetting::setValue('service_page_heroes', $pages);
        }

        return $heroImages;
    }
}
