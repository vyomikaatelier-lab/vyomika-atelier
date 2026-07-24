<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index(Request $request)
    {
        $query = Product::with('category')->latest();

        if ($request->query('filter') === 'unclassified') {
            $query->unclassified();
        }

        $products = $query->paginate(15)->withQueryString();
        $unclassifiedCount = Product::query()->unclassified()->count();

        return view('admin.products.index', compact('products', 'unclassifiedCount'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $categorySections = $this->categorySectionMap($categories);

        return view('admin.products.form', compact('categories', 'categorySections'));
    }

    public function store(Request $request)
    {
        $slug = Str::slug($request->input('slug') ?: $request->input('name', ''));
        $validated = $this->validateProduct($request, new Product(['slug' => $slug]));
        $validated['slug'] = $slug;
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_gallery_visible'] = $request->boolean('is_gallery_visible', true);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', null, 'products');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', null, 'products');

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
        $validated['slug'] = Str::slug($request->input('slug') ?: $validated['name']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_gallery_visible'] = $request->boolean('is_gallery_visible', true);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', $product->image, 'products');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', $product->gallery, 'products');

        $product->update($validated);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->deleteStoredPath($product->image);

        foreach ($product->gallery ?? [] as $path) {
            $this->deleteStoredPath($path);
        }

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    private function validateProduct(Request $request, ?Product $existing = null): array
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($existing?->id)],
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'gallery_urls' => 'nullable|string',
            'gallery_files' => 'nullable|array',
            'gallery_files.*' => 'image|mimes:jpeg,jpg,png,webp|max:4096',
            'section' => ['required', 'in:'.implode(',', Product::SECTIONS)],
            'purchase_mode' => ['required', 'in:'.implode(',', Product::PURCHASE_MODES)],
            'pricing_type' => ['required', 'in:'.implode(',', Product::PRICING_TYPES)],
        ]);

        $category = Category::query()->find($validated['category_id']);
        $productSlug = $existing?->slug ?? Str::slug($request->input('slug') ?: $validated['name']);
        $section = $validated['section'];

        $expectedPurchaseMode = Product::SECTION_PURCHASE_MODE_MAP[$section] ?? null;
        if ($validated['purchase_mode'] !== $expectedPurchaseMode) {
            throw ValidationException::withMessages([
                'purchase_mode' => "Purchase mode must be \"{$expectedPurchaseMode}\" for the {$section} section.",
            ]);
        }

        if ($category) {
            $categorySection = $category->resolvedSection();
            $knownSectionSlugs = ProductCatalog::categorySlugsForSection($section);

            if ($categorySection !== null && $categorySection !== $section) {
                throw ValidationException::withMessages([
                    'category_id' => "The selected parent category belongs to the {$categorySection} section, not {$section}.",
                ]);
            }

            if ($knownSectionSlugs !== [] && ! in_array($category->slug, $knownSectionSlugs, true)) {
                throw ValidationException::withMessages([
                    'category_id' => "The selected parent category does not belong to the {$section} section.",
                ]);
            }

            if ($knownSectionSlugs === [] && $categorySection === null
                && ProductCatalog::sectionFor($productSlug, $category->slug) === 'unknown') {
                throw ValidationException::withMessages([
                    'category_id' => 'Choose a category that is recognised for the storefront (Shop, Studio, or Railings).',
                ]);
            }
        }

        $preview = ($existing ?? new Product())->fill([
            ...$validated,
            'slug' => $productSlug,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($preview->is_active && ! $preview->isClassified()) {
            throw ValidationException::withMessages([
                'section' => 'Active products must have a valid section, parent category, purchase mode, and pricing type.',
            ]);
        }

        return $validated;
    }

    /**
     * @param \Illuminate\Support\Collection<int, Category> $categories
     * @return array<int, string>
     */
    private function categorySectionMap($categories): array
    {
        return $categories
            ->mapWithKeys(fn (Category $category) => [
                $category->id => $category->resolvedSection() ?? 'other',
            ])
            ->all();
    }
}
