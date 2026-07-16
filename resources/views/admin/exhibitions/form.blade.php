@extends('layouts.admin')
@section('title', isset($exhibition) ? 'Edit Exhibition' : 'Add Exhibition')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($exhibition) ? 'Edit' : 'Add' }} Exhibition</h1>
<form method="POST" action="{{ isset($exhibition) ? route('admin.exhibitions.update', $exhibition) : route('admin.exhibitions.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-2xl">
    @csrf @if(isset($exhibition)) @method('PUT') @endif
    <div><label class="block text-sm mb-1">Event name</label><input name="name" value="{{ old('name', $exhibition->name ?? '') }}" required class="w-full border px-3 py-2 rounded"></div>
    <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">City</label><input name="city" value="{{ old('city', $exhibition->city ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Country</label><input name="country" value="{{ old('country', $exhibition->country ?? 'India') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Year</label><input type="number" name="year" value="{{ old('year', $exhibition->year ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Description</label><textarea name="description" rows="4" class="w-full border px-3 py-2 rounded">{{ old('description', $exhibition->description ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Cover image URL</label><input name="cover_image" value="{{ old('cover_image', $exhibition->cover_image ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Upload cover</label><input type="file" name="cover_file" accept="image/*"></div>
    <div><label class="block text-sm mb-1">Gallery URLs (one per line)</label><textarea name="gallery_urls" rows="4" class="w-full border px-3 py-2 rounded">{{ old('gallery_urls', isset($exhibition) && is_array($exhibition->gallery) ? implode("\n", $exhibition->gallery) : '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Display order</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $exhibition->sort_order ?? 0) }}" class="w-full border px-3 py-2 rounded"></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $exhibition->is_active ?? true))> Active</label>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
</form>
@endsection
