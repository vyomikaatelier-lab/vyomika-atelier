@extends('layouts.admin')

@section('title', isset($product) ? 'Edit Product' : 'Add Product')

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($product) ? 'Edit' : 'Add' }} Product</h1>

@php
    use App\Models\Product;

    $currentSection = old('section', $product->resolvedSection() ?? '');
    $currentPurchaseMode = old('purchase_mode', isset($product) ? $product->resolvedPurchaseMode() : (Product::SECTION_PURCHASE_MODE_MAP[$currentSection] ?? ''));
    $currentPricingType = old('pricing_type', isset($product) ? $product->resolvedPricingType() : '');
    $sectionLabels = ['shop' => 'Shop', 'studio' => 'Studio', 'railings' => 'Railings'];
    $purchaseModeLabels = ['checkout' => 'Checkout', 'enquiry' => 'Enquiry', 'quote' => 'Quote'];
    $pricingTypeLabels = ['fixed' => 'Fixed price', 'square_foot' => 'Per sq ft', 'quotation_only' => 'Quotation only'];
@endphp

<form method="POST" enctype="multipart/form-data" action="{{ isset($product) ? route('admin.products.update', $product) : route('admin.products.store') }}" class="bg-white p-6 rounded-lg shadow max-w-2xl space-y-4">
    @csrf
    @if(isset($product)) @method('PUT') @endif

    <div class="rounded border border-gray-200 bg-gray-50 p-3 text-sm">
        <p class="text-gray-600">Section decides storefront behaviour: <strong>Shop</strong> → Checkout (cart/order), <strong>Studio</strong> → Enquiry (custom order, no cart), <strong>Railings</strong> → Quote (project quotation only, never enters cart).</p>
    </div>

    <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" placeholder="Product Name" required class="w-full border px-3 py-2 rounded">
    <input type="text" name="slug" value="{{ old('slug', $product->slug ?? '') }}" placeholder="Slug (optional — auto from name)" class="w-full border px-3 py-2 rounded">
    <textarea name="description" placeholder="Description" rows="4" class="w-full border px-3 py-2 rounded">{{ old('description', $product->description ?? '') }}</textarea>

    <details class="border rounded p-3 bg-gray-50">
        <summary class="font-medium cursor-pointer text-sm">SEO</summary>
        <div class="mt-3 space-y-2">
            <input name="meta_title" value="{{ old('meta_title', $product->meta_title ?? '') }}" placeholder="SEO title (blank = product name)" class="w-full border px-3 py-2 rounded">
            <textarea name="meta_description" rows="2" placeholder="Meta description" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
            <input name="og_image" value="{{ old('og_image', $product->og_image ?? '') }}" placeholder="Open Graph image URL (blank = product image)" class="w-full border px-3 py-2 rounded">
        </div>
    </details>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="text-sm text-gray-600 block mb-1">Section</label>
            <select name="section" id="product-section" required class="w-full border px-3 py-2 rounded">
                <option value="">Select section</option>
                @foreach($sectionLabels as $value => $label)
                    <option value="{{ $value }}" @selected($currentSection === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('section')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-sm text-gray-600 block mb-1">Parent category / service</label>
            <select name="category_id" id="product-category" required class="w-full border px-3 py-2 rounded">
                <option value="">Select category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        data-section="{{ $categorySections[$category->id] ?? 'other' }}"
                        @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }} ({{ $category->slug }})</option>
                @endforeach
            </select>
            @error('category_id')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="text-sm text-gray-600 block mb-1">Purchase mode</label>
            <select name="purchase_mode" id="product-purchase-mode" required class="w-full border px-3 py-2 rounded">
                <option value="">Select purchase mode</option>
                @foreach($purchaseModeLabels as $value => $label)
                    <option value="{{ $value }}" @selected($currentPurchaseMode === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Auto-set from Section (Shop→Checkout, Studio→Enquiry, Railings→Quote).</p>
            @error('purchase_mode')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-sm text-gray-600 block mb-1">Pricing type</label>
            <select name="pricing_type" required class="w-full border px-3 py-2 rounded">
                <option value="">Select pricing type</option>
                @foreach($pricingTypeLabels as $value => $label)
                    <option value="{{ $value }}" @selected($currentPricingType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('pricing_type')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <input type="number" step="0.01" name="price" value="{{ old('price', $product->price ?? '') }}" placeholder="Price" required class="border px-3 py-2 rounded">
        <input type="number" step="0.01" name="compare_price" value="{{ old('compare_price', $product->compare_price ?? '') }}" placeholder="Compare Price" class="border px-3 py-2 rounded">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="SKU" class="border px-3 py-2 rounded">
        <input type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" placeholder="Stock" required class="border px-3 py-2 rounded">
    </div>

    <div>
        <label class="text-sm text-gray-600 block mb-1">Upload Image</label>
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" class="w-full border px-3 py-2 rounded">
        <p class="text-xs text-gray-500 mt-1">JPEG, PNG or WebP. Max 4 MB.</p>
    </div>
    <div>
        <label class="text-sm text-gray-600 block mb-1">Or Image URL</label>
        <input type="text" name="image" value="{{ old('image', isset($product) && str_starts_with($product->image ?? '', 'http') ? $product->image : '') }}" placeholder="https://..." class="w-full border px-3 py-2 rounded">
    </div>
    @if(isset($product) && $product->imageUrl())
        <img src="{{ $product->imageUrl() }}" alt="" class="w-32 h-40 object-cover rounded border">
    @endif

    @include('admin.partials.gallery-upload-fields', ['gallery' => isset($product) ? $product->gallery : null, 'directory' => 'products', 'label' => 'Product gallery'])

    <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))> Featured</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> Active</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_gallery_visible" value="1" @checked(old('is_gallery_visible', $product->is_gallery_visible ?? true))> Visible in gallery grids</label>
    <button type="submit" class="bg-gray-900 text-white px-6 py-2 rounded">Save</button>
</form>

<script>
(function () {
    var sectionSelect = document.getElementById('product-section');
    var categorySelect = document.getElementById('product-category');
    var purchaseModeSelect = document.getElementById('product-purchase-mode');
    if (!sectionSelect || !categorySelect || !purchaseModeSelect) return;

    var purchaseModeForSection = { shop: 'checkout', studio: 'enquiry', railings: 'quote' };

    function filterCategoryOptions() {
        var section = sectionSelect.value;
        var options = categorySelect.querySelectorAll('option[data-section]');
        options.forEach(function (opt) {
            var matches = !section || opt.dataset.section === section || opt.dataset.section === 'other';
            opt.hidden = !matches;
        });
    }

    function syncPurchaseMode() {
        var mode = purchaseModeForSection[sectionSelect.value];
        if (mode) purchaseModeSelect.value = mode;
    }

    sectionSelect.addEventListener('change', function () {
        filterCategoryOptions();
        syncPurchaseMode();
    });

    filterCategoryOptions();
})();
</script>
@endsection
