@props([
    'gallery' => null,
    'directory' => 'uploads',
    'label' => 'Gallery',
])

@php
    $galleryItems = is_array($gallery) ? array_values($gallery) : [];
    $oldExisting = old('gallery_existing');
    if (is_array($oldExisting)) {
        $galleryItems = array_values(array_filter($oldExisting, fn ($item) => filled($item)));
    }
    $imageAccept = \App\Support\AdminImageUpload::acceptAttribute();
    $imageHint = \App\Support\AdminImageUpload::hintText();
@endphp

<div class="space-y-3 border rounded p-4 bg-gray-50" data-gallery-upload>
    <p class="text-sm font-medium">{{ $label }}</p>
    <p class="text-xs text-gray-600">Upload one image per row. Existing images can be replaced or removed individually.</p>
    <p class="text-xs text-gray-600">{{ $imageHint }}</p>
    <input type="hidden" name="gallery_managed" value="1">

    @if($galleryItems !== [])
    <div class="space-y-3" data-gallery-existing>
        <p class="text-xs font-medium text-gray-700 uppercase tracking-wide">Current images</p>
        @foreach($galleryItems as $index => $item)
        <div class="gallery-existing-row flex flex-wrap items-start gap-3 bg-white border rounded p-3">
            <img src="{{ \App\Support\MediaUrl::resolve($item) }}" alt="" class="w-28 h-20 object-cover rounded border shrink-0">
            <div class="flex-1 min-w-[12rem] space-y-2">
                <input type="hidden" name="gallery_existing[]" value="{{ $item }}">
                <p class="text-xs text-gray-500 truncate">{{ $item }}</p>
                <label class="inline-flex items-center gap-2 text-xs text-red-700">
                    <input type="checkbox" name="remove_gallery[]" value="{{ $item }}" @checked(is_array(old('remove_gallery')) && in_array($item, old('remove_gallery'), true))>
                    Remove this image
                </label>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Replace image</label>
                    <input type="file" name="gallery_replace[{{ $index }}]" accept="{{ $imageAccept }}" class="w-full text-xs @error('gallery_replace.'.$index) border-red-500 @enderror">
                    @error('gallery_replace.'.$index)<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="space-y-3" data-gallery-new-rows>
        <p class="text-xs font-medium text-gray-700 uppercase tracking-wide">Add images</p>
        <div class="gallery-new-row flex items-end gap-2">
            <div class="flex-1">
                <label class="block text-xs text-gray-600 mb-1">Image 1</label>
                <input type="file" name="gallery_files[]" accept="{{ $imageAccept }}" class="w-full text-sm @error('gallery_files.*') border-red-500 @enderror">
            </div>
            <button type="button" class="gallery-new-remove text-xs text-red-600 hover:underline hidden mb-2" aria-label="Remove row">Remove</button>
        </div>
    </div>
    <button type="button" data-gallery-add-row class="text-sm text-gray-700 border border-gray-300 bg-white px-3 py-1 rounded hover:bg-gray-50">+ Add another image</button>

    <details class="text-xs text-gray-500">
        <summary class="cursor-pointer">Advanced: paste URLs or paths</summary>
        <div class="mt-2">
            <label class="block text-sm mb-1 text-gray-700">One per line (optional)</label>
            <textarea name="gallery_urls" rows="3" class="w-full border px-3 py-2 rounded text-sm">{{ old('gallery_urls') }}</textarea>
        </div>
    </details>
</div>

<script>
(function () {
    var root = document.querySelector('[data-gallery-upload]');
    if (!root) return;

    var newRows = root.querySelector('[data-gallery-new-rows]');
    var addBtn = root.querySelector('[data-gallery-add-row]');
    if (!newRows || !addBtn) return;

    function bindNewRow(row) {
        var removeBtn = row.querySelector('.gallery-new-remove');
        if (!removeBtn) return;

        removeBtn.addEventListener('click', function () {
            var rows = newRows.querySelectorAll('.gallery-new-row');
            if (rows.length <= 1) {
                row.querySelector('input[type="file"]').value = '';
                return;
            }
            row.remove();
            renumberNewRows();
        });
    }

    function renumberNewRows() {
        var rows = newRows.querySelectorAll('.gallery-new-row');
        rows.forEach(function (row, index) {
            var label = row.querySelector('label');
            if (label) label.textContent = 'Image ' + (index + 1);
            var removeBtn = row.querySelector('.gallery-new-remove');
            if (removeBtn) removeBtn.classList.toggle('hidden', rows.length <= 1);
        });
    }

    newRows.querySelectorAll('.gallery-new-row').forEach(bindNewRow);
    renumberNewRows();

    addBtn.addEventListener('click', function () {
        var index = newRows.querySelectorAll('.gallery-new-row').length;
        var row = document.createElement('div');
        row.className = 'gallery-new-row flex items-end gap-2';
        row.innerHTML = ''
            + '<div class="flex-1">'
            + '<label class="block text-xs text-gray-600 mb-1">Image ' + (index + 1) + '</label>'
            + '<input type="file" name="gallery_files[]" accept="{{ $imageAccept }}" class="w-full text-sm">'
            + '</div>'
            + '<button type="button" class="gallery-new-remove text-xs text-red-600 hover:underline mb-2" aria-label="Remove row">Remove</button>';
        newRows.appendChild(row);
        bindNewRow(row);
        renumberNewRows();
    });
})();
</script>
