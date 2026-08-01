@php
    $prefix = $prefix ?? 'hero';
    $heroData = $hero ?? [];
    $heroLayout = old($prefix.'_layout', data_get($heroData, 'hero_layout', 'default'));
    $context = $heroLayout === 'compact' ? 'compact' : ($context ?? 'cover');
    $showLayoutOptions = $showLayoutOptions ?? true;
    $lines = fn ($value) => is_array($value) ? implode("\n", $value) : (string) ($value ?? '');
    $eyebrow = data_get($heroData, 'eyebrow') ?: data_get($heroData, 'label');
@endphp
<div class="space-y-3">
    <input
        name="{{ $prefix }}_eyebrow"
        value="{{ old($prefix.'_eyebrow', $eyebrow) }}"
        placeholder="Eyebrow / label"
        class="w-full border px-3 py-2 rounded"
    >
    <div class="grid md:grid-cols-3 gap-3">
        <input
            name="{{ $prefix }}_title_line1"
            value="{{ old($prefix.'_title_line1', data_get($heroData, 'title_line1')) }}"
            placeholder="Title line 1 (structured)"
            class="w-full border px-3 py-2 rounded"
        >
        <input
            name="{{ $prefix }}_title_accent"
            value="{{ old($prefix.'_title_accent', data_get($heroData, 'title_accent')) }}"
            placeholder="Title accent (highlighted)"
            class="w-full border px-3 py-2 rounded"
        >
        <input
            name="{{ $prefix }}_title_line2"
            value="{{ old($prefix.'_title_line2', data_get($heroData, 'title_line2')) }}"
            placeholder="Title line 2"
            class="w-full border px-3 py-2 rounded"
        >
    </div>
    <input
        name="{{ $prefix }}_title"
        value="{{ old($prefix.'_title', data_get($heroData, 'title')) }}"
        placeholder="Simple title (used when structured lines are empty)"
        class="w-full border px-3 py-2 rounded"
    >
    <div class="grid md:grid-cols-2 gap-3">
        <input
            name="{{ $prefix }}_tagline"
            value="{{ old($prefix.'_tagline', data_get($heroData, 'tagline')) }}"
            placeholder="Tagline"
            class="w-full border px-3 py-2 rounded"
        >
        <input
            name="{{ $prefix }}_tagline_accent"
            value="{{ old($prefix.'_tagline_accent', data_get($heroData, 'tagline_accent')) }}"
            placeholder="Tagline accent"
            class="w-full border px-3 py-2 rounded"
        >
    </div>
    <textarea
        name="{{ $prefix }}_subtitle"
        rows="2"
        placeholder="Subtitle / description"
        class="w-full border px-3 py-2 rounded"
    >{{ old($prefix.'_subtitle', data_get($heroData, 'subtitle')) }}</textarea>
    <textarea
        name="{{ $prefix }}_highlights"
        rows="3"
        placeholder="Highlights (one per line)"
        class="w-full border px-3 py-2 rounded"
    >{{ old($prefix.'_highlights', $lines(data_get($heroData, 'highlights'))) }}</textarea>
    <div class="grid md:grid-cols-2 gap-3">
        <input
            name="{{ $prefix }}_footer_tagline"
            value="{{ old($prefix.'_footer_tagline', data_get($heroData, 'footer_tagline')) }}"
            placeholder="Footer tagline"
            class="w-full border px-3 py-2 rounded"
        >
        <input
            name="{{ $prefix }}_footer_tagline_accent"
            value="{{ old($prefix.'_footer_tagline_accent', data_get($heroData, 'footer_tagline_accent')) }}"
            placeholder="Footer tagline accent"
            class="w-full border px-3 py-2 rounded"
        >
    </div>
    <div class="grid md:grid-cols-2 gap-3">
        <input
            name="{{ $prefix }}_cta_primary_label"
            value="{{ old($prefix.'_cta_primary_label', data_get($heroData, 'cta_primary.label')) }}"
            placeholder="Primary CTA label"
            class="w-full border px-3 py-2 rounded"
        >
        <input
            name="{{ $prefix }}_cta_primary_href"
            value="{{ old($prefix.'_cta_primary_href', data_get($heroData, 'cta_primary.href')) }}"
            placeholder="Primary CTA URL / #anchor"
            class="w-full border px-3 py-2 rounded"
        >
        <input
            name="{{ $prefix }}_cta_secondary_label"
            value="{{ old($prefix.'_cta_secondary_label', data_get($heroData, 'cta_secondary.label')) }}"
            placeholder="Secondary CTA label"
            class="w-full border px-3 py-2 rounded"
        >
        <input
            name="{{ $prefix }}_cta_secondary_href"
            value="{{ old($prefix.'_cta_secondary_href', data_get($heroData, 'cta_secondary.href')) }}"
            placeholder="Secondary CTA URL / #anchor"
            class="w-full border px-3 py-2 rounded"
        >
    </div>
    <input
        name="{{ $prefix }}_image_alt"
        value="{{ old($prefix.'_image_alt', data_get($heroData, 'image_alt')) }}"
        placeholder="Hero image alt text"
        class="w-full border px-3 py-2 rounded"
    >
    @if($showLayoutOptions)
    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm mb-1">Hero layout</label>
            <select name="{{ $prefix }}_layout" class="w-full border px-3 py-2 rounded">
                <option value="default" @selected(old($prefix.'_layout', data_get($heroData, 'hero_layout', 'default')) === 'default')>Default</option>
                <option value="compact" @selected(old($prefix.'_layout', data_get($heroData, 'hero_layout')) === 'compact')>Compact split</option>
            </select>
        </div>
        <div>
            <label class="block text-sm mb-1">Image position</label>
            <select name="{{ $prefix }}_image_position" class="w-full border px-3 py-2 rounded">
                <option value="right" @selected(old($prefix.'_image_position', data_get($heroData, 'image_position', 'right')) === 'right')>Right</option>
                <option value="left" @selected(old($prefix.'_image_position', data_get($heroData, 'image_position')) === 'left')>Left</option>
            </select>
        </div>
    </div>
    @endif
    <p class="text-xs text-gray-500">{{ \App\Support\ResponsiveHero::adminUploadIntro($context) }}</p>
    @include('admin.partials.responsive-hero-images', ['prefix' => $prefix, 'hero' => $heroData, 'context' => $context])
</div>
