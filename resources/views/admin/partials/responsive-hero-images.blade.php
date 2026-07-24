@php
    use App\Support\MediaUrl;
    use App\Support\ResponsiveHero;

    $prefix = $prefix ?? 'hero';
    $heroData = $hero ?? [];
    $context = $context ?? 'cover';
    $variants = ResponsiveHero::adminVariants($context);
@endphp
<div class="grid lg:grid-cols-3 gap-3">
    @foreach($variants as $variant => $meta)
        @php
            $storageKey = $meta['key'];
            $flatField = ResponsiveHero::flatFieldForStorageKey($prefix, $storageKey);
            $value = old($flatField, $heroData[$storageKey] ?? '');
            $preview = filled($value) ? (MediaUrl::resolve($value) ?? $value) : null;
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
                name="{{ $flatField }}"
                value="{{ $value }}"
                placeholder="Image URL (optional)"
                class="w-full border px-3 py-2 rounded text-sm"
            >
            <div>
                <label class="block text-xs mb-1">Upload {{ $variant }} image</label>
                <input
                    type="file"
                    name="{{ $flatField }}_file"
                    accept="image/jpeg,image/png,image/webp"
                    class="w-full border px-3 py-2 rounded text-sm"
                >
            </div>
            @if($preview)
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="{{ $flatField }}_remove" value="1" @checked(old($flatField.'_remove'))>
                    Remove {{ $variant }} image
                </label>
            @endif
        </div>
    @endforeach
</div>
