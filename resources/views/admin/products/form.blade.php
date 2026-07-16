@extends('layouts.admin')

@section('title', isset($product) ? 'Edit Product' : 'Add Product')

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($product) ? 'Edit' : 'Add' }} Product</h1>

<form method="POST" enctype="multipart/form-data" action="{{ isset($product) ? route('admin.products.update', $product) : route('admin.products.store') }}" class="bg-white p-6 rounded-lg shadow max-w-2xl space-y-4">
    @csrf
    @if(isset($product)) @method('PUT') @endif

    <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" placeholder="Product Name" required class="w-full border px-3 py-2 rounded">
    <textarea name="description" placeholder="Description" rows="4" class="w-full border px-3 py-2 rounded">{{ old('description', $product->description ?? '') }}</textarea>
    <select name="category_id" class="w-full border px-3 py-2 rounded">
        <option value="">No category</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
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

    <div>
        <label class="text-sm text-gray-600 block mb-1">Gallery image URLs (one per line, optional)</label>
        <textarea name="gallery_urls" rows="3" placeholder="https://example.com/image-2.jpg" class="w-full border px-3 py-2 rounded">{{ old('gallery_urls', isset($product) ? implode("\n", $product->gallery ?? []) : '') }}</textarea>
    </div>

    <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))> Featured</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> Active</label>
    <button type="submit" class="bg-gray-900 text-white px-6 py-2 rounded">Save</button>
</form>
@endsection
