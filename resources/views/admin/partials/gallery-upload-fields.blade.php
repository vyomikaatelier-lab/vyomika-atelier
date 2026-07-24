@props([
    'gallery' => null,
    'directory' => 'uploads',
    'label' => 'Gallery',
])

@php
    $galleryItems = is_array($gallery) ? $gallery : [];
    $galleryLines = old('gallery_urls', implode("\n", $galleryItems));
@endphp

<div class="space-y-3 border rounded p-4 bg-gray-50">
    <p class="text-sm font-medium">{{ $label }}</p>

    @if($galleryItems !== [])
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($galleryItems as $item)
        <label class="block text-xs bg-white border rounded p-2">
            <img src="{{ \App\Support\MediaUrl::resolve($item) }}" alt="" class="w-full h-24 object-cover rounded mb-2">
            <span class="block truncate text-gray-500 mb-1">{{ $item }}</span>
            <span class="inline-flex items-center gap-1"><input type="checkbox" name="remove_gallery[]" value="{{ $item }}"> Remove</span>
        </label>
        @endforeach
    </div>
    @endif

    <div>
        <label class="block text-sm mb-1">Upload gallery images</label>
        <input type="file" name="gallery_files[]" accept="image/*" multiple class="w-full text-sm">
    </div>
    <div>
        <label class="block text-sm mb-1">Gallery URLs or stored paths (one per line)</label>
        <textarea name="gallery_urls" rows="4" class="w-full border px-3 py-2 rounded text-sm">{{ $galleryLines }}</textarea>
    </div>
</div>
