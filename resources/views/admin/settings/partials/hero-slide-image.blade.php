@php
    use App\Support\ResponsiveHero;

    $meta = ResponsiveHero::adminVariants('homepage')[$variant] ?? ResponsiveHero::adminVariants('homepage')['desktop'];
    $field = $variant === 'desktop' ? 'image' : 'image_'.$variant;
    $value = old("hero_slides.{$index}.{$field}", $slide[$field] ?? '');
    $preview = filled($value) ? (\App\Support\MediaUrl::resolve($value) ?? $value) : null;
@endphp
<div class="rounded border bg-white p-3 space-y-2">
    <div>
        <p class="text-sm font-medium">{{ $meta['label'] }}</p>
        <p class="text-xs font-medium text-gray-700 mt-1">Recommended: {{ $meta['size'] }}</p>
        <p class="text-xs text-gray-500">{{ $meta['hint'] }}</p>
    </div>
    @if($preview)
        <img src="{{ $preview }}" alt="" class="w-full max-w-xs h-28 object-cover rounded border">
    @endif
    <input
        name="hero_slides[{{ $index }}][{{ $field }}]"
        value="{{ $value }}"
        placeholder="Image URL (optional)"
        class="w-full border px-3 py-2 rounded text-sm"
    >
    <div>
        <label class="block text-xs mb-1">Upload {{ $variant }} image</label>
        <input
            type="file"
            name="hero_slides[{{ $index }}][{{ $field }}_file]"
            accept="image/jpeg,image/png,image/webp"
            class="w-full border px-3 py-2 rounded text-sm"
        >
    </div>
    @if($preview)
        <label class="flex items-center gap-2 text-xs text-gray-600">
            <input type="checkbox" name="hero_slides[{{ $index }}][{{ $field }}_remove]" value="1" @checked(old("hero_slides.{$index}.{$field}_remove"))>
            Remove {{ $variant }} image
        </label>
    @endif
</div>
