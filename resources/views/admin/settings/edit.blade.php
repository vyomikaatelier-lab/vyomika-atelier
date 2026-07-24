@extends('layouts.admin')
@section('title', 'Site Settings')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Site Settings</h1>
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-6 max-w-3xl">
    @csrf
    <section class="space-y-3">
        <h2 class="font-medium">Business</h2>
        <input name="brand_name" value="{{ old('brand_name', $brand['name'] ?? '') }}" placeholder="Business name" required class="w-full border px-3 py-2 rounded">
        <input name="phone" value="{{ old('phone', $brand['phone'] ?? $business['phone'] ?? '') }}" placeholder="Phone" class="w-full border px-3 py-2 rounded">
        <input name="whatsapp" value="{{ old('whatsapp', $social['whatsapp'] ?? '') }}" placeholder="WhatsApp" class="w-full border px-3 py-2 rounded">
        <input name="email" value="{{ old('email', $brand['email'] ?? $business['email'] ?? '') }}" placeholder="Email" class="w-full border px-3 py-2 rounded">
        <textarea name="address_shop" rows="2" placeholder="Shipping / shop address" class="w-full border px-3 py-2 rounded">{{ old('address_shop', $brand['address_shop'] ?? '') }}</textarea>
        <textarea name="address_office" rows="2" placeholder="Office address" class="w-full border px-3 py-2 rounded">{{ old('address_office', $brand['address_office'] ?? $business['address'] ?? '') }}</textarea>
        <input name="gstin" value="{{ old('gstin', $business['gstin'] ?? '') }}" placeholder="GSTIN" class="w-full border px-3 py-2 rounded">
    </section>
    <section class="space-y-3">
        <h2 class="font-medium">Social links</h2>
        @foreach(['instagram','facebook','linkedin','pinterest','youtube'] as $network)
            <input name="{{ $network }}" value="{{ old($network, $social[$network] ?? '') }}" placeholder="{{ ucfirst($network) }} URL (https://…)" class="w-full border px-3 py-2 rounded">
        @endforeach
    </section>
    <section class="space-y-3">
        <h2 class="font-medium">SEO & store</h2>
        <input name="default_meta_title" value="{{ old('default_meta_title', $seo['default_title'] ?? '') }}" placeholder="Default SEO title" class="w-full border px-3 py-2 rounded">
        <textarea name="default_meta_description" rows="2" placeholder="Default meta description" class="w-full border px-3 py-2 rounded">{{ old('default_meta_description', $seo['default_description'] ?? '') }}</textarea>
        <input name="default_og_image" value="{{ old('default_og_image', $seo['default_og_image'] ?? '') }}" placeholder="Default Open Graph image URL" class="w-full border px-3 py-2 rounded">
        <textarea name="shipping_note" rows="2" placeholder="Shipping note" class="w-full border px-3 py-2 rounded">{{ old('shipping_note', $store['shipping_note'] ?? '') }}</textarea>
        <input name="warranty_duration" value="{{ old('warranty_duration', $store['warranty_duration'] ?? '') }}" placeholder="Warranty duration" class="w-full border px-3 py-2 rounded">
    </section>
    <section class="space-y-3">
        <h2 class="font-medium">Analytics & Search Console</h2>
        <p class="text-xs text-gray-500">Leave blank until configured. Do not hardcode credentials in theme files.</p>
        <input name="ga4_measurement_id" value="{{ old('ga4_measurement_id', $analytics['ga4_measurement_id'] ?? '') }}" placeholder="GA4 Measurement ID (G-XXXXXXXX)" class="w-full border px-3 py-2 rounded">
        <input name="gsc_verification" value="{{ old('gsc_verification', $analytics['gsc_verification'] ?? '') }}" placeholder="Google Search Console verification token" class="w-full border px-3 py-2 rounded">
    </section>
    <section class="space-y-3">
        <h2 class="font-medium">Homepage announcement</h2>
        <input name="announcement_text" value="{{ old('announcement_text', $announcementText) }}" placeholder="Announcement bar text" class="w-full border px-3 py-2 rounded">
        <div class="grid md:grid-cols-2 gap-3">
            <input name="announcement_link_label" value="{{ old('announcement_link_label', $announcementLinkLabel) }}" placeholder="Link label" class="w-full border px-3 py-2 rounded">
            <input name="announcement_link_href" value="{{ old('announcement_link_href', $announcementLinkHref) }}" placeholder="Link URL (/shop)" class="w-full border px-3 py-2 rounded">
        </div>
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="font-medium">Homepage hero carousel</h2>
            <p class="text-xs text-gray-500 mt-1">{{ \App\Support\ResponsiveHero::adminUploadIntro('homepage') }}</p>
        </div>
        @foreach($heroSlides as $index => $slide)
            <div class="border rounded p-4 space-y-3 bg-gray-50">
                <h3 class="font-medium text-sm">Slide {{ $index + 1 }}</h3>
                <input name="hero_slides[{{ $index }}][kicker]" value="{{ old("hero_slides.{$index}.kicker", $slide['kicker'] ?? '') }}" placeholder="Eyebrow / kicker (e.g. LIMITED TIME OFFER)" class="w-full border px-3 py-2 rounded bg-white">
                <input name="hero_slides[{{ $index }}][title]" value="{{ old("hero_slides.{$index}.title", $slide['title'] ?? '') }}" placeholder="Hero title" class="w-full border px-3 py-2 rounded bg-white">
                <textarea name="hero_slides[{{ $index }}][description]" rows="2" placeholder="Hero description" class="w-full border px-3 py-2 rounded bg-white">{{ old("hero_slides.{$index}.description", $slide['description'] ?? '') }}</textarea>
                <div class="grid md:grid-cols-2 gap-3">
                    <input name="hero_slides[{{ $index }}][cta_label]" value="{{ old("hero_slides.{$index}.cta_label", $slide['cta_label'] ?? '') }}" placeholder="Button label" class="w-full border px-3 py-2 rounded bg-white">
                    <input name="hero_slides[{{ $index }}][cta_href]" value="{{ old("hero_slides.{$index}.cta_href", $slide['cta_href'] ?? '') }}" placeholder="Button link (/shop)" class="w-full border px-3 py-2 rounded bg-white">
                </div>
                <div class="grid lg:grid-cols-3 gap-3">
                    @include('admin.settings.partials.hero-slide-image', ['index' => $index, 'slide' => $slide, 'variant' => 'desktop'])
                    @include('admin.settings.partials.hero-slide-image', ['index' => $index, 'slide' => $slide, 'variant' => 'tablet'])
                    @include('admin.settings.partials.hero-slide-image', ['index' => $index, 'slide' => $slide, 'variant' => 'mobile'])
                </div>
            </div>
        @endforeach
    </section>

    <section class="space-y-3">
        <h2 class="font-medium">Navigation JSON</h2>
        <p class="text-xs text-gray-500">Array of nav items with label, route, optional params/children.</p>
        <textarea name="nav_json" rows="12" class="w-full border px-3 py-2 rounded font-mono text-xs">{{ old('nav_json', $navJson) }}</textarea>
    </section>

    <section class="space-y-3">
        <h2 class="font-medium">Grievance contact</h2>
        <input name="grievance_officer_name" value="{{ old('grievance_officer_name', $business['grievance_officer_name'] ?? '') }}" class="w-full border px-3 py-2 rounded" placeholder="Officer name">
        <input name="grievance_officer_email" value="{{ old('grievance_officer_email', $business['grievance_officer_email'] ?? '') }}" class="w-full border px-3 py-2 rounded" placeholder="Officer email">
        <input name="grievance_officer_phone" value="{{ old('grievance_officer_phone', $business['grievance_officer_phone'] ?? '') }}" class="w-full border px-3 py-2 rounded" placeholder="Officer phone">
        <input name="legal_last_updated" value="{{ old('legal_last_updated', $legalLastUpdated) }}" class="w-full border px-3 py-2 rounded" placeholder="Legal last updated label">
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold">PVD finish swatches</h2>
            <p class="text-sm text-gray-500 mt-1">Upload square photos (104×104 px or larger) for each finish shown on product pages. Leave blank to use the default placeholder.</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @foreach(array_chunk($finishSwatches, 2) as $column)
            <div class="flex flex-col gap-3">
                @foreach($column as $swatch)
            @php
                $current = $finishSwatchImages[$swatch['slug']] ?? null;
                $preview = $current
                    ? (\Illuminate\Support\Str::startsWith($current, 'http') ? $current : asset($current))
                    : asset('images/finishes/'.$swatch['slug'].'.svg');
            @endphp
            <div class="border rounded p-3 space-y-2 text-center">
                <div class="flex flex-col items-center gap-2">
                    <img src="{{ $preview }}" alt="{{ $swatch['name'] }}" class="w-20 h-20 rounded-full object-cover border-2" style="background: {{ $swatch['hex'] }}">
                    <p class="font-semibold text-sm">{{ $swatch['name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $swatch['slug'] }}</p>
                </div>
                <input type="file" name="finish_image_{{ $swatch['slug'] }}" accept="image/jpeg,image/png,image/webp" class="w-full border px-2 py-1 rounded text-sm">
                <input type="text" name="finish_url_{{ $swatch['slug'] }}" value="{{ old('finish_url_'.$swatch['slug'], \Illuminate\Support\Str::startsWith((string) $current, 'http') ? $current : '') }}" placeholder="Or image URL (https://…)" class="w-full border px-2 py-1 rounded text-sm" inputmode="url">
                <label class="flex items-center justify-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="finish_clear_{{ $swatch['slug'] }}" value="1" @checked(old('finish_clear_'.$swatch['slug']))>
                    Reset to default
                </label>
            </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </section>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save settings</button>
</form>
@endsection
