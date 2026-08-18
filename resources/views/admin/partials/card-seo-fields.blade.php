@props([
    'prefix' => 'cards[0]',
    'card' => [],
    'previewTitleFallback' => 'Card title',
])

@php
    $field = static fn (string $name): string => $prefix.'['.$name.']';
    $value = static fn (string $name) => old(str_replace(['[', ']'], ['.', ''], $prefix).'.'.$name, $card[$name] ?? '');
    $seoTitle = $value('meta_title');
    $seoDesc = $value('meta_description');
    $titleLen = strlen((string) $seoTitle);
    $descLen = strlen((string) $seoDesc);
    $robotsIndex = old(str_replace(['[', ']'], ['.', ''], $prefix).'.robots_index', $card['robots_index'] ?? true);
@endphp

<div class="space-y-2 text-sm">
    <div>
        <label class="block text-xs mb-1 text-gray-600">SEO title <span class="text-gray-400">({{ $titleLen }} chars)</span></label>
        <input name="{{ $field('meta_title') }}" value="{{ $seoTitle }}" placeholder="SEO title (blank = card title)" class="w-full border px-3 py-2 rounded">
    </div>
    <div>
        <label class="block text-xs mb-1 text-gray-600">Meta description <span class="text-gray-400">({{ $descLen }} chars)</span></label>
        <textarea name="{{ $field('meta_description') }}" rows="2" placeholder="Meta description (blank = card text)" class="w-full border px-3 py-2 rounded">{{ $seoDesc }}</textarea>
    </div>
    <div class="grid md:grid-cols-2 gap-2">
        <input name="{{ $field('og_title') }}" value="{{ $value('og_title') }}" placeholder="OG title (blank = SEO title)" class="w-full border px-3 py-2 rounded">
        <input name="{{ $field('og_image') }}" value="{{ $value('og_image') }}" placeholder="OG image URL (blank = card image)" class="w-full border px-3 py-2 rounded">
    </div>
    <textarea name="{{ $field('og_description') }}" rows="2" placeholder="OG description (blank = meta description)" class="w-full border px-3 py-2 rounded">{{ $value('og_description') }}</textarea>
    <input name="{{ $field('canonical_url') }}" value="{{ $value('canonical_url') }}" placeholder="Canonical URL (optional — future detail page)" class="w-full border px-3 py-2 rounded">
    <input name="{{ $field('seo_keyword') }}" value="{{ $value('seo_keyword') }}" placeholder="Internal target keyword (not shown on site)" class="w-full border px-3 py-2 rounded">
    <label class="inline-flex items-center gap-2 text-xs">
        <input type="checkbox" name="{{ $field('robots_index') }}" value="1" @checked($robotsIndex !== false && $robotsIndex !== '0' && $robotsIndex !== 0)>
        Include in structured data / allow indexing signals
    </label>
</div>
