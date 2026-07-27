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

        $section = $request->query('section');
        if (filled($section) && in_array($section, Product::SECTIONS, true)) {
            $query->where('section', $section);
        }

        $categoryFilter = null;
        if ($request->filled('category_id')) {
            $categoryFilter = Category::query()->find($request->integer('category_id'));
        } elseif ($request->filled('category')) {
            $categoryFilter = Category::query()->where('slug', $request->query('category'))->first();
        }

        if ($categoryFilter) {
            $query->where('category_id', $categoryFilter->id);
        }

        $products = $query->paginate(15)->withQueryString();
        $unclassifiedCount = Product::query()->unclassified()->count();

        $categories = Category::query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Category $category) => $category->resolvedSection() ?? 'other');

        $sectionLabels = [
            Product::SECTION_SHOP => 'Shop',
            Product::SECTION_STUDIO => 'Studio',
            Product::SECTION_RAILINGS => 'Railings',
        ];

        $sectionOrder = [
            Product::SECTION_SHOP,
            Product::SECTION_STUDIO,
            Product::SECTION_RAILINGS,
            'other',
        ];

        $categorySectionOrder = [
            Product::SECTION_SHOP,
            Product::SECTION_STUDIO,
            'other',
        ];

        return view('admin.products.index', [
            'products' => $products,
            'unclassifiedCount' => $unclassifiedCount,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
            'activeSection' => in_array($section, Product::SECTIONS, true) ? $section : null,
            'sectionLabels' => $sectionLabels,
            'sectionOrder' => $sectionOrder,
            'categorySectionOrder' => $categorySectionOrder,
            'totalProductCount' => Product::query()->count(),
        ]);
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $categorySections = $this->categorySectionMap($categories);

        return view('admin.products.form', compact('categories', 'categorySections'));
    }

    public function store(Request $request)
    {
        if ($this->multipartPayloadFailed($request)) {
            return back()->with('error', 'Upload too large for the server limit. Save text changes first, then upload the main image only (max 4 MB).');
        }

        $slug = Str::slug($request->input('slug') ?: $request->input('name', ''));
        $validated = $this->validateProduct($request, new Product(['slug' => $slug]));
        $validated['slug'] = $slug;
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_gallery_visible'] = $request->boolean('is_gallery_visible', true);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', null, 'products');
        $validated = $this->normalizeProductPrices($validated);

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
        if ($this->multipartPayloadFailed($request)) {
            return back()->with('error', 'Upload too large for the server limit. Save text changes first, then upload the main image only (max 4 MB).');
        }

        $validated = $this->validateProduct($request, $product);
        $validated['slug'] = Str::slug($request->input('slug') ?: $validated['name']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_gallery_visible'] = $request->boolean('is_gallery_visible', true);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', $product->image, 'products');
        $validated = $this->normalizeProductPrices($validated);

        $product->update($validated);

        return redirect()
            ->route('admin.products.edit', ['product' => $product, 'saved' => 1])
            ->with('success', 'Product updated. Price saved: ₹'.number_format((float) $product->fresh()->price, 0));
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
        // Blank template rows from the admin form must not trip required_with.
        $this->stripEmptySizeOptionRows($request);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($existing?->id)],
            'description' => 'nullable|string',
            'headline_text' => 'nullable|string|max:2000',
            'swatches_note' => 'nullable|string|max:2000',
            'tab_specifications' => 'nullable|string',
            'tab_packaging' => 'nullable|string',
            'tab_shipping' => 'nullable|string',
            'dim_width_ft' => 'nullable|integer|min:0|max:50',
            'dim_width_in' => 'nullable|numeric|min:0|max:11.99',
            'dim_height_ft' => 'nullable|integer|min:0|max:50',
            'dim_height_in' => 'nullable|numeric|min:0|max:11.99',
            'dim_width_cm' => 'nullable|numeric|min:0.1|max:9999|required_with:dim_height_cm',
            'dim_height_cm' => 'nullable|numeric|min:0.1|max:9999|required_with:dim_width_cm',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'section' => ['required', 'in:'.implode(',', Product::SECTIONS)],
            'purchase_mode' => ['required', 'in:'.implode(',', Product::PURCHASE_MODES)],
            'pricing_type' => ['required', 'in:'.implode(',', Product::PRICING_TYPES)],
            'size_options' => 'nullable|array',
            'size_options.*.label' => 'required|string|max:50',
            'size_options.*.price' => 'required|numeric|min:0',
            'size_options.*.compare_price' => 'nullable|numeric|min:0',
            'size_options.*.discount_percent' => 'nullable|integer|min:0|max:99',
            'size_options.*.size_inches' => 'nullable|numeric|min:0',
            'size_options.*.sku_suffix' => 'nullable|string|max:20',
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

        if ($category?->slug !== 'mirror-frames') {
            $validated['dim_width_cm'] = null;
            $validated['dim_height_cm'] = null;
        } else {
            $validated = array_merge($validated, $this->normalizeMirrorDimensionsFromRequest($validated));
        }

        unset(
            $validated['dim_width_ft'],
            $validated['dim_width_in'],
            $validated['dim_height_ft'],
            $validated['dim_height_in'],
        );

        $validated['size_options'] = $this->normalizeSizeOptions($validated['size_options'] ?? null);
        if ($category?->slug === 'door-handles') {
            if ($validated['size_options'] !== null) {
                $validated['price'] = min(array_column($validated['size_options'], 'price'));
                // Per-size compare/discount lives on size_options — hide product-level sale UI.
                $validated['compare_price'] = null;
            }
        } else {
            // Clear so size/price variants never leak onto mirrors or other categories.
            $validated['size_options'] = null;
        }

        $validated['tab_specifications'] = Product::normalizeTabLines($validated['tab_specifications'] ?? null);

        return $validated;
    }

    /**
     * Prefer feet+inches admin inputs; fall back to legacy cm fields.
     *
     * @param  array<string, mixed>  $validated
     * @return array{dim_width_cm: float|null, dim_height_cm: float|null}
     */
    private function normalizeMirrorDimensionsFromRequest(array $validated): array
    {
        $hasFtIn = filled($validated['dim_width_ft'] ?? null)
            || filled($validated['dim_width_in'] ?? null)
            || filled($validated['dim_height_ft'] ?? null)
            || filled($validated['dim_height_in'] ?? null);

        if ($hasFtIn) {
            $widthCm = Product::cmFromFeetInches(
                (float) ($validated['dim_width_ft'] ?? 0),
                (float) ($validated['dim_width_in'] ?? 0)
            );
            $heightCm = Product::cmFromFeetInches(
                (float) ($validated['dim_height_ft'] ?? 0),
                (float) ($validated['dim_height_in'] ?? 0)
            );

            if ($widthCm < 0.1 || $heightCm < 0.1) {
                throw ValidationException::withMessages([
                    'dim_width_ft' => 'Enter both width and height in feet and inches (at least 0.1 cm equivalent).',
                    'dim_height_ft' => 'Enter both width and height in feet and inches (at least 0.1 cm equivalent).',
                ]);
            }

            return [
                'dim_width_cm' => $widthCm,
                'dim_height_cm' => $heightCm,
            ];
        }

        return [
            'dim_width_cm' => filled($validated['dim_width_cm'] ?? null)
                ? round((float) $validated['dim_width_cm'], 2)
                : null,
            'dim_height_cm' => filled($validated['dim_height_cm'] ?? null)
                ? round((float) $validated['dim_height_cm'], 2)
                : null,
        ];
    }

    /**
     * Drop completely blank size-option rows before validation so the admin
     * form's empty template row does not fail required field rules.
     * Partially filled rows are kept so validation can still reject them.
     */
    private function stripEmptySizeOptionRows(Request $request): void
    {
        $rows = $request->input('size_options');
        if (! is_array($rows)) {
            return;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $price = $row['price'] ?? null;
            $comparePrice = $row['compare_price'] ?? null;
            $sizeInches = $row['size_inches'] ?? null;
            $skuSuffix = $row['sku_suffix'] ?? null;
            $discountPercent = $row['discount_percent'] ?? null;

            $priceBlank = $price === null || $price === '';
            $compareBlank = $comparePrice === null || $comparePrice === '';
            $inchesBlank = $sizeInches === null || $sizeInches === '';
            $skuBlank = $skuSuffix === null || trim((string) $skuSuffix) === '';
            $discountBlank = $discountPercent === null || $discountPercent === '';

            if ($label === '' && $priceBlank && $compareBlank && $inchesBlank && $skuBlank && $discountBlank) {
                continue;
            }

            $filtered[] = $row;
        }

        $request->merge([
            'size_options' => $filtered === [] ? null : array_values($filtered),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return list<array{label: string, size_inches: ?float, price: float, compare_price: ?float, sku_suffix: ?string}>|null
     */
    private function normalizeSizeOptions(?array $rows): ?array
    {
        if ($rows === null) {
            return null;
        }

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $price = $row['price'] ?? null;

            if ($label === '' || ! is_numeric($price)) {
                continue;
            }

            $compareRaw = $row['compare_price'] ?? null;
            $comparePrice = filled($compareRaw) && is_numeric($compareRaw)
                ? round((float) $compareRaw, 2)
                : null;

            if ($comparePrice === null) {
                $discountRaw = $row['discount_percent'] ?? null;
                if (filled($discountRaw) && is_numeric($discountRaw)) {
                    $discountPct = (int) $discountRaw;
                    $salePrice = round((float) $price, 2);
                    if ($discountPct > 0 && $discountPct < 100 && $salePrice >= 0) {
                        $comparePrice = round($salePrice / (1 - $discountPct / 100), 2);
                    }
                }
            }

            $options[] = [
                'label' => $label,
                'size_inches' => filled($row['size_inches'] ?? null)
                    ? round((float) $row['size_inches'], 2)
                    : null,
                'price' => round((float) $price, 2),
                'compare_price' => $comparePrice,
                'sku_suffix' => filled($row['sku_suffix'] ?? null)
                    ? trim((string) $row['sku_suffix'])
                    : null,
            ];
        }

        return $options === [] ? null : $options;
    }

    /** @param  array<string, mixed>  $validated */
    private function normalizeProductPrices(array $validated): array
    {
        $validated['price'] = round((float) ($validated['price'] ?? 0), 2);
        $validated['compare_price'] = filled($validated['compare_price'] ?? null)
            ? round((float) $validated['compare_price'], 2)
            : null;

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
