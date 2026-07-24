@extends('layouts.admin')
@section('title', isset($category) ? 'Edit Category' : 'Add Category')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($category) ? 'Edit' : 'Add' }} Category</h1>
<form method="POST" action="{{ isset($category) ? route('admin.categories.update', $category) : route('admin.categories.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-2xl">
    @csrf
    @if(isset($category)) @method('PUT') @endif
    <div><label class="block text-sm mb-1">Name</label><input name="name" value="{{ old('name', $category->name ?? '') }}" required class="w-full border px-3 py-2 rounded"></div>
    @if(isset($category))
    <div>
        <label class="block text-sm mb-1">Slug</label>
        <input value="{{ $category->slug }}" readonly class="w-full border px-3 py-2 rounded bg-gray-100 text-gray-600">
        @php $mappedSection = \App\Support\ProductCatalog::sectionForCategorySlug($category->slug); @endphp
        <p class="text-xs text-gray-500 mt-1">Storefront section: <strong>{{ $mappedSection ? ucfirst($mappedSection) : 'Unmapped' }}</strong> — set via slug mapping in ProductCatalog.</p>
    </div>
    @endif
    <div><label class="block text-sm mb-1">Description</label><textarea name="description" rows="3" class="w-full border px-3 py-2 rounded">{{ old('description', $category->description ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Image URL</label><input name="image" value="{{ old('image', $category->image ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Upload image</label><input type="file" name="image_file" accept="image/*" class="w-full"></div>
    <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $category->meta_title ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $category->meta_description ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Display order</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="w-full border px-3 py-2 rounded"></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))> Active</label>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
</form>
@if(isset($category) && $category->products()->exists())
<form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="mt-6 bg-white p-6 rounded shadow max-w-2xl" onsubmit="return confirm('Delete and reassign products?')">
    @csrf @method('DELETE')
    <p class="text-sm mb-2">This category has linked products. Reassign before delete:</p>
    <select name="reassign_category_id" required class="border px-3 py-2 rounded text-sm mb-2">
        @foreach(\App\Models\Category::where('id', '!=', $category->id)->orderBy('name')->get() as $other)
            <option value="{{ $other->id }}">{{ $other->name }}</option>
        @endforeach
    </select>
    <button class="text-red-600 text-sm">Delete category</button>
</form>
@endif
@endsection
