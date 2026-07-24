@extends('layouts.admin')
@section('title', isset($service) ? 'Edit Service' : 'Add Service')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($service) ? 'Edit' : 'Add' }} Service</h1>
<form method="POST" action="{{ isset($service) ? route('admin.services.update', $service) : route('admin.services.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-3xl">
    @csrf @if(isset($service)) @method('PUT') @endif
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">Name</label><input name="name" value="{{ old('name', $service->name ?? '') }}" required class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Slug</label><input name="slug" value="{{ old('slug', $service->slug ?? '') }}" placeholder="Auto from name" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Summary</label><textarea name="summary" rows="2" class="w-full border px-3 py-2 rounded">{{ old('summary', $service->summary ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Content</label><textarea name="content" rows="6" class="w-full border px-3 py-2 rounded">{{ old('content', $service->content ?? '') }}</textarea></div>
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">Lead form</label><select name="lead_form" class="w-full border px-3 py-2 rounded"><option value="popup" @selected(old('lead_form', $service->lead_form ?? 'popup') === 'popup')>Popup</option><option value="inline" @selected(old('lead_form', $service->lead_form ?? '') === 'inline')>Inline</option></select></div>
        <div><label class="block text-sm mb-1">Rate per sq ft</label><input type="number" step="0.01" name="rate_per_sqft" value="{{ old('rate_per_sqft', $service->rate_per_sqft ?? 1800) }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Cover image</p>
        @if(isset($service) && $service->image)
            <img src="{{ \App\Support\MediaUrl::resolve($service->image) }}" alt="" class="w-40 h-28 object-cover rounded border">
        @endif
        <input name="image" value="{{ old('image', $service->image ?? '') }}" placeholder="Image URL" class="w-full border px-3 py-2 rounded">
        <input type="file" name="image_file" accept="image/*">
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $service->meta_title ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Meta description</label><input name="meta_description" value="{{ old('meta_description', $service->meta_description ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div class="flex flex-wrap gap-4 text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="has_calculator" value="1" @checked(old('has_calculator', $service->has_calculator ?? false))> Has calculator</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="has_designs" value="1" @checked(old('has_designs', $service->has_designs ?? false))> Has designs</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))> Active</label>
    </div>

    @php
        $designRows = old('designs', isset($service) ? $service->designs->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'slug' => $d->slug,
            'description' => $d->description,
            'product_slug' => $d->product_slug,
            'image' => $d->image,
            'is_active' => $d->is_active,
        ])->all() : []);
    @endphp
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Service designs</p>
        @foreach($designRows as $index => $design)
        <div class="border rounded p-3 bg-white space-y-2">
            @if(!empty($design['id']))<input type="hidden" name="designs[{{ $index }}][id]" value="{{ $design['id'] }}">@endif
            <div class="grid md:grid-cols-2 gap-3">
                <input name="designs[{{ $index }}][name]" value="{{ $design['name'] ?? '' }}" placeholder="Design name" class="border px-3 py-2 rounded text-sm">
                <input name="designs[{{ $index }}][slug]" value="{{ $design['slug'] ?? '' }}" placeholder="Slug" class="border px-3 py-2 rounded text-sm">
            </div>
            <textarea name="designs[{{ $index }}][description]" rows="2" placeholder="Description" class="w-full border px-3 py-2 rounded text-sm">{{ $design['description'] ?? '' }}</textarea>
            <div class="grid md:grid-cols-2 gap-3">
                <select name="designs[{{ $index }}][product_slug]" class="border px-3 py-2 rounded text-sm">
                    <option value="">— Linked product —</option>
                    @foreach($products as $slug => $name)
                        <option value="{{ $slug }}" @selected(($design['product_slug'] ?? '') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="designs[{{ $index }}][is_active]" value="1" @checked($design['is_active'] ?? true)> Active</label>
            </div>
            @if(!empty($design['image']))
                <img src="{{ \App\Support\MediaUrl::resolve($design['image']) }}" alt="" class="w-24 h-24 object-cover rounded border">
                <input type="hidden" name="designs[{{ $index }}][image]" value="{{ $design['image'] }}">
            @endif
            <input type="file" name="designs[{{ $index }}][image_file]" accept="image/*" class="text-sm">
        </div>
        @endforeach
        @php $newIndex = count($designRows); @endphp
        <div class="border rounded p-3 bg-white space-y-2 border-dashed">
            <p class="text-xs text-gray-500">Add design</p>
            <div class="grid md:grid-cols-2 gap-3">
                <input name="designs[{{ $newIndex }}][name]" placeholder="Design name" class="border px-3 py-2 rounded text-sm">
                <input name="designs[{{ $newIndex }}][slug]" placeholder="Slug" class="border px-3 py-2 rounded text-sm">
            </div>
            <textarea name="designs[{{ $newIndex }}][description]" rows="2" placeholder="Description" class="w-full border px-3 py-2 rounded text-sm"></textarea>
            <select name="designs[{{ $newIndex }}][product_slug]" class="border px-3 py-2 rounded text-sm w-full">
                <option value="">— Linked product —</option>
                @foreach($products as $slug => $name)
                    <option value="{{ $slug }}">{{ $name }}</option>
                @endforeach
            </select>
            <input type="file" name="designs[{{ $newIndex }}][image_file]" accept="image/*" class="text-sm">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="designs[{{ $newIndex }}][is_active]" value="1" checked> Active</label>
        </div>
    </div>

    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
</form>
@endsection
