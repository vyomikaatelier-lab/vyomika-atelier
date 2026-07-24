@php
    $titleKey = $titleKey ?? 'title';
    $textKey = $textKey ?? null;
    $showCta = $showCta ?? false;
    $title = $item[$titleKey] ?? $item['title'] ?? $item['name'] ?? $item['label'] ?? '';
    $text = $textKey ? ($item[$textKey] ?? $item['text'] ?? $item['description'] ?? '') : null;
    $image = $item['image'] ?? null;
@endphp
<div class="border rounded p-3 bg-gray-50 space-y-2" data-row>
    @if($image)
        <img src="{{ $preview($image) }}" alt="" class="w-full max-w-xs h-28 object-cover rounded border">
        <p class="text-xs text-gray-500">Source: {{ str_starts_with((string) $image, 'http') ? 'External / legacy URL' : 'Uploaded storage path' }}</p>
    @endif
    <input data-field="title" name="{{ $prefix }}[{{ $index }}][{{ $titleKey }}]" value="{{ $title }}" placeholder="Title" class="w-full border px-3 py-2 rounded">
    @if($textKey)
    <textarea data-field="text" name="{{ $prefix }}[{{ $index }}][{{ $textKey }}]" rows="2" placeholder="Description" class="w-full border px-3 py-2 rounded">{{ $text }}</textarea>
    @endif
    <input data-field="image_alt" name="{{ $prefix }}[{{ $index }}][image_alt]" value="{{ $item['image_alt'] ?? '' }}" placeholder="Image alt text" class="w-full border px-3 py-2 rounded">
    <input data-field="image" name="{{ $prefix }}[{{ $index }}][image]" value="{{ $image }}" placeholder="Image URL" class="w-full border px-3 py-2 rounded">
    <input type="file" data-field="image_file" name="{{ $prefix }}[{{ $index }}][image_file]" accept="image/jpeg,image/png,image/webp">
    @if($showCta)
    <div class="grid md:grid-cols-2 gap-2">
        <input data-field="cta_label" name="{{ $prefix }}[{{ $index }}][cta_label]" value="{{ $item['cta_label'] ?? '' }}" placeholder="CTA label (optional)" class="w-full border px-3 py-2 rounded">
        <input data-field="cta_href" name="{{ $prefix }}[{{ $index }}][cta_href]" value="{{ $item['cta_href'] ?? '' }}" placeholder="CTA URL / #anchor (optional)" class="w-full border px-3 py-2 rounded">
    </div>
    @endif
    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" data-field="active" name="{{ $prefix }}[{{ $index }}][active]" value="1" @checked(($item['active'] ?? true) !== false)> Active</label>
    <button type="button" class="text-red-600 text-sm" data-remove-row>Remove</button>
</div>
