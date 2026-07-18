<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductAdminController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->latest()->paginate(15);

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $categorySections = $this->categorySectionMap($categories);

        return view('admin.products.form', compact('categories', 'categorySections'));
    }

    public function store(Request $request)
    {
        $slug = Str::slug($request->input('name', ''));
        $validated = $this->validateProduct($request, new Product(['slug' => $slug]));
        $validated['slug'] = $slug;
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_gallery_visible'] = $request->boolean('is_gallery_visible', true);
        $validated['image'] = $this->resolveImage($request, $validated['image'] ?? null);
        $validated['gallery'] = $this->parseGallery($request);

        Product::create($validated);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->get();
        $categorySections = $this->categorySectionMap($categories);

        return view('admin.products.form', compact('product', 'categories', 'categorySections'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request, $product);
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_gallery_visible'] = $request->boolean('is_gallery_visible', true);

        $newImage = $this->resolveImage($request, $validated['image'] ?? $product->image);
        if ($newImage !== $product->image) {
            $this->deleteStoredImage($product->image);
            $validated['image'] = $newImage;
        }

        $product->update([...$validated, 'gallery' => $this->parseGallery($request)]);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->deleteStoredImage($product->image);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    private function validateProduct(Request $request, ?Product $existing = null): array
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'section' => ['required', 'in:'.implode(',', Product::SECTIONS)],
            'purchase_mode' => ['required', 'in:'.implode(',', Product::PURCHASE_MODES)],
            'pricing_type' => ['required', 'in:'.implode(',', Product::PRICING_TYPES)],
        ]);

        $category = Category::query()->find($validated['category_id']);
        $productSlug = $existing?->slug ?? Str::slug($validated['name']);
        $section = $validated['section'];

        // Shop→checkout, Studio→enquiry, Railings→quote. Purchase mode is
        // derived, not freely chosen, so a tampered value is always rejected.
        $expectedPurchaseMode = Product::SECTION_PURCHASE_MODE_MAP[$section] ?? null;
        if ($validated['purchase_mode'] !== $expectedPurchaseMode) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'purchase_mode' => "Purchase mode must be \"{$expectedPurchaseMode}\" for the {$section} section.",
            ]);
        }

        if ($category) {
            $knownSectionSlugs = ProductCatalog::categorySlugsForSection($section);
            $categoryKnownSection = ProductCatalog::sectionForCategorySlug($category->slug);

            if ($knownSectionSlugs !== [] && ! in_array($category->slug, $knownSectionSlugs, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'category_id' => "The selected parent category does not belong to the {$section} section.",
                ]);
            }

            if ($knownSectionSlugs === [] && $categoryKnownSection === null
                && ProductCatalog::sectionFor($productSlug, $category->slug) === 'unknown') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'category_id' => 'Choose a category that is recognised for the storefront (Shop, Studio, or Railings).',
                ]);
            }
        }

        return $validated;
    }

    /**
     * Map of category_id => section slug, used by the admin form to filter
     * the "Parent category" dropdown by the selected Section.
     *
     * @param \Illuminate\Support\Collection<int, Category> $categories
     * @return array<int, string>
     */
    private function categorySectionMap($categories): array
    {
        return $categories
            ->mapWithKeys(fn (Category $category) => [
                $category->id => ProductCatalog::sectionForCategorySlug($category->slug) ?? 'other',
            ])
            ->all();
    }

    private function resolveImage(Request $request, ?string $fallback): ?string
    {
        if ($request->hasFile('image_file')) {
            return $request->file('image_file')->store('products', 'public');
        }

        $url = $request->input('image');

        return filled($url) ? $url : $fallback;
    }

    private function deleteStoredImage(?string $image): void
    {
        if ($image && ! str_starts_with($image, 'http')) {
            Storage::disk('public')->delete($image);
        }
    }

    /** @return array<int, string>|null */
    private function parseGallery(Request $request): ?array
    {
        $raw = $request->input('gallery_urls');
        if (! filled($raw)) {
            return null;
        }

        $urls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));

        return $urls ?: null;
    }
}
