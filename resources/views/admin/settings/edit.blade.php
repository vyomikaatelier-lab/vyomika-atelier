@extends('layouts.admin')
@section('title', 'Site Settings')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Site Settings</h1>
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-6 max-w-3xl">
    @csrf @method('PUT')
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
            <input name="{{ $network }}" value="{{ old($network, $social[$network] ?? '') }}" placeholder="{{ ucfirst($network) }} URL" class="w-full border px-3 py-2 rounded">
        @endforeach
    </section>
    <section class="space-y-3">
        <h2 class="font-medium">SEO & store</h2>
        <input name="default_meta_title" value="{{ old('default_meta_title', $seo['default_title'] ?? '') }}" placeholder="Default SEO title" class="w-full border px-3 py-2 rounded">
        <textarea name="default_meta_description" rows="2" placeholder="Default meta description" class="w-full border px-3 py-2 rounded">{{ old('default_meta_description', $seo['default_description'] ?? '') }}</textarea>
        <textarea name="shipping_note" rows="2" placeholder="Shipping note" class="w-full border px-3 py-2 rounded">{{ old('shipping_note', $store['shipping_note'] ?? '') }}</textarea>
        <input name="warranty_duration" value="{{ old('warranty_duration', $store['warranty_duration'] ?? '') }}" placeholder="Warranty duration" class="w-full border px-3 py-2 rounded">
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
            <h2 class="font-medium">PVD finish swatches</h2>
            <p class="text-sm text-gray-500 mt-1">Upload square photos (104×104 px or larger) for each finish shown on product pages. Leave blank to use the default placeholder.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($finishSwatches as $swatch)
            @php
                $current = $finishSwatchImages[$swatch['slug']] ?? null;
                $preview = $current
                    ? (\Illuminate\Support\Str::startsWith($current, 'http') ? $current : asset($current))
                    : asset('images/finishes/'.$swatch['slug'].'.svg');
            @endphp
            <div class="border rounded p-3 space-y-2">
                <div class="flex items-center gap-3">
                    <img src="{{ $preview }}" alt="{{ $swatch['name'] }}" class="w-14 h-14 rounded object-cover border" style="background: {{ $swatch['hex'] }}">
                    <div>
                        <p class="font-medium text-sm">{{ $swatch['name'] }}</p>
                        <p class="text-xs text-gray-500">{{ $swatch['slug'] }}</p>
                    </div>
                </div>
                <input type="file" name="finish_image_{{ $swatch['slug'] }}" accept="image/jpeg,image/png,image/webp" class="w-full border px-2 py-1 rounded text-sm">
                <input type="url" name="finish_url_{{ $swatch['slug'] }}" value="{{ old('finish_url_'.$swatch['slug'], \Illuminate\Support\Str::startsWith((string) $current, 'http') ? $current : '') }}" placeholder="Or image URL" class="w-full border px-2 py-1 rounded text-sm">
            </div>
            @endforeach
        </div>
    </section>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save settings</button>
</form>
@endsection
