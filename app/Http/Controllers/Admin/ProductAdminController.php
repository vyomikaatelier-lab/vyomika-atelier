<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
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

        return view('admin.products.form', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['image'] = $this->resolveImage($request, $validated['image'] ?? null);

        Product::create($validated);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->get();

        return view('admin.products.form', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request);
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);

        $newImage = $this->resolveImage($request, $validated['image'] ?? $product->image);
        if ($newImage !== $product->image) {
            $this->deleteStoredImage($product->image);
            $validated['image'] = $newImage;
        }

        $product->update($validated);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->deleteStoredImage($product->image);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);
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
}
