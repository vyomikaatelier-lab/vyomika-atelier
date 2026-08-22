@extends('layouts.admin')
@section('title', isset($project) ? 'Edit Project Work Item' : 'Add Project Work Item')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($project) ? 'Edit' : 'Add' }} Project Work Item</h1>
<form method="POST" action="{{ isset($project) ? route('admin.projects.update', $project) : route('admin.projects.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-3xl">
    @csrf @if(isset($project)) @method('PUT') @endif
    <input type="hidden" name="_page_save" value="1">
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">Project name *</label><input name="project_name" value="{{ old('project_name', $project->project_name ?? '') }}" required class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Work type</label><input name="work_type" value="{{ old('work_type', $project->work_type ?? '') }}" placeholder="e.g. PVD partitions" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">City</label><input name="city" value="{{ old('city', $project->city ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Client</label><input name="client" value="{{ old('client', $project->client ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Size</label><input name="size" value="{{ old('size', $project->size ?? '') }}" placeholder="e.g. 14 m partition run" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Price</label><input name="price" value="{{ old('price', $project->price ?? '') }}" placeholder="e.g. On quotation" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Display order</label><input type="number" name="display_order" min="0" value="{{ old('display_order', $project->display_order ?? 0) }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Description</label><textarea name="description" rows="5" class="w-full border px-3 py-2 rounded">{{ old('description', $project->description ?? '') }}</textarea></div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Project image</p>
        @if(isset($project) && $project->imageUrl())
            <img src="{{ $project->imageUrl() }}" alt="" class="w-48 aspect-[4/3] object-cover rounded border">
        @endif
        <div><label class="block text-sm mb-1">Image URL</label><input name="image_path" value="{{ old('image_path', $project->image_path ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Upload image</label><input type="file" name="image_file" accept="image/*"></div>
        <div><label class="block text-sm mb-1">Image alt text</label><input name="image_alt" value="{{ old('image_alt', $project->image_alt ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $project->is_active ?? true))> Published</label>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
</form>
@endsection
