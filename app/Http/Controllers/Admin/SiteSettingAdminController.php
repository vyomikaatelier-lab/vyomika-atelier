<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class SiteSettingAdminController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'brand' => array_merge(config('site.brand', []), SiteSetting::getValue('brand', [])),
            'social' => array_merge(config('site.social', []), SiteSetting::getValue('social', [])),
            'seo' => array_merge(config('site.seo', []), SiteSetting::getValue('seo', [])),
            'store' => array_merge(config('site.store', []), SiteSetting::getValue('store', [])),
            'business' => array_merge(config('legal.business', []), SiteSetting::getValue('business', [])),
            'legalLastUpdated' => SiteSetting::getValue('legal_last_updated', config('legal.last_updated')),
            'finishSwatches' => config('finishes.swatches', []),
            'finishSwatchImages' => \App\Support\FinishSwatches::imageOverrides(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'brand_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address_shop' => 'nullable|string|max:500',
            'address_office' => 'nullable|string|max:500',
            'gstin' => 'nullable|string|max:50',
            'instagram' => 'nullable|url|max:500',
            'facebook' => 'nullable|url|max:500',
            'linkedin' => 'nullable|url|max:500',
            'pinterest' => 'nullable|url|max:500',
            'youtube' => 'nullable|url|max:500',
            'default_meta_title' => 'nullable|string|max:255',
            'default_meta_description' => 'nullable|string|max:500',
            'shipping_note' => 'nullable|string|max:2000',
            'warranty_duration' => 'nullable|string|max:120',
            'grievance_officer_name' => 'nullable|string|max:255',
            'grievance_officer_email' => 'nullable|email|max:255',
            'grievance_officer_phone' => 'nullable|string|max:50',
            'legal_last_updated' => 'nullable|string|max:50',
        ]);

        SiteSetting::setValue('brand', [
            'name' => $validated['brand_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address_shop' => $validated['address_shop'],
            'address_office' => $validated['address_office'],
        ]);

        SiteSetting::setValue('social', [
            'instagram' => $validated['instagram'],
            'facebook' => $validated['facebook'],
            'linkedin' => $validated['linkedin'],
            'pinterest' => $validated['pinterest'],
            'youtube' => $validated['youtube'],
            'whatsapp' => $validated['whatsapp'],
        ]);

        SiteSetting::setValue('seo', [
            'default_title' => $validated['default_meta_title'],
            'default_description' => $validated['default_meta_description'],
        ]);

        SiteSetting::setValue('store', [
            'shipping_note' => $validated['shipping_note'],
            'warranty_duration' => $validated['warranty_duration'],
        ]);

        SiteSetting::setValue('business', [
            'brand_name' => $validated['brand_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address_office'],
            'gstin' => $validated['gstin'],
            'grievance_officer_name' => $validated['grievance_officer_name'],
            'grievance_officer_email' => $validated['grievance_officer_email'],
            'grievance_officer_phone' => $validated['grievance_officer_phone'],
        ]);

        if (filled($validated['legal_last_updated'])) {
            SiteSetting::setValue('legal_last_updated', $validated['legal_last_updated']);
        }

        $finishImages = \App\Support\FinishSwatches::imageOverrides();

        foreach (config('finishes.swatches', []) as $swatch) {
            $slug = $swatch['slug'];
            $fileKey = 'finish_image_'.$slug;
            $urlKey = 'finish_url_'.$slug;

            if ($request->hasFile($fileKey)) {
                $request->validate([
                    $fileKey => 'image|mimes:jpeg,jpg,png,webp|max:4096',
                ]);
                $finishImages[$slug] = 'storage/'.$request->file($fileKey)->store('finishes', 'public');
            } elseif (filled($request->input($urlKey))) {
                $request->validate([
                    $urlKey => 'url|max:500',
                ]);
                $finishImages[$slug] = $request->input($urlKey);
            }
        }

        SiteSetting::setValue('finish_swatches', $finishImages);

        return back()->with('success', 'Site settings saved.');
    }
}
