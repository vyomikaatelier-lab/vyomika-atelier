@props([
    'page' => [],
    'previewUrl' => '',
    'previewTitleFallback' => 'Title preview',
])

@php
    $seoTitle = old('meta_title', $page['meta_title'] ?? '');
    $seoDesc = old('meta_description', $page['meta_description'] ?? '');
    $previewTitle = $seoTitle !== '' ? $seoTitle : $previewTitleFallback;
    $titleLen = strlen((string) $seoTitle);
    $descLen = strlen((string) $seoDesc);
@endphp

@include('admin.partials.seo-preview', [
    'title' => $previewTitle,
    'description' => $seoDesc,
    'url' => $previewUrl,
])
<div>
    <label class="block text-sm mb-1">SEO title <span class="text-gray-400">({{ $titleLen }} chars — aim ~50–60)</span></label>
    <input name="meta_title" value="{{ $seoTitle }}" placeholder="SEO title" class="w-full border px-3 py-2 rounded">
</div>
<div>
    <label class="block text-sm mb-1">Meta description <span class="text-gray-400">({{ $descLen }} chars — aim ~140–160)</span></label>
    <textarea name="meta_description" rows="3" placeholder="Meta description" class="w-full border px-3 py-2 rounded">{{ $seoDesc }}</textarea>
</div>
<input name="primary_keyword" value="{{ old('primary_keyword', $page['primary_keyword'] ?? '') }}" placeholder="Primary keyword (editorial only — not a meta keywords tag)" class="w-full border px-3 py-2 rounded">
@if($showSeoKeyword ?? true)
<input name="seo_keyword" value="{{ old('seo_keyword', $page['seo_keyword'] ?? '') }}" placeholder="Internal target keyword (not shown on site)" class="w-full border px-3 py-2 rounded">
@endif
<div class="grid md:grid-cols-2 gap-3">
    <input name="og_title" value="{{ old('og_title', $page['og_title'] ?? '') }}" placeholder="OG title (blank = SEO title)" class="w-full border px-3 py-2 rounded">
    <input name="og_image" value="{{ old('og_image', $page['og_image'] ?? '') }}" placeholder="OG image URL (blank = hero image)" class="w-full border px-3 py-2 rounded">
</div>
<textarea name="og_description" rows="2" placeholder="OG description (blank = meta description)" class="w-full border px-3 py-2 rounded">{{ old('og_description', $page['og_description'] ?? '') }}</textarea>
<input name="canonical" value="{{ old('canonical', $page['canonical'] ?? '') }}" placeholder="Canonical URL (blank = public page URL)" class="w-full border px-3 py-2 rounded">
<div>
    <label class="block text-sm mb-1">Indexing</label>
    <select name="robots" class="border px-3 py-2 rounded">
        <option value="index" @selected(old('robots', $page['robots'] ?? 'index') === 'index')>index, follow</option>
        <option value="noindex" @selected(old('robots', $page['robots'] ?? '') === 'noindex')>noindex</option>
    </select>
</div>
