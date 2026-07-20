<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
        if (! Schema::hasTable('site_settings')) {
            return back()->with('error', 'Database table site_settings is missing. Run: php artisan migrate --force');
        }

        if ($request->isMethod('post') && $request->header('Content-Length') > 0 && ! $request->has('brand_name') && empty($request->all())) {
            return back()->with('error', 'Upload too large for the server limit. Try one image at a time, or ask Hostinger to raise post_max_size.');
        }

        $finishRules = [];
        foreach (config('finishes.swatches', []) as $swatch) {
            $slug = $swatch['slug'];
            $finishRules['finish_image_'.$slug] = 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096';
            $finishRules['finish_url_'.$slug] = 'nullable|string|max:500';
            $finishRules['finish_clear_'.$slug] = 'nullable|boolean';
        }

        try {
            $validated = $request->validate(array_merge([
                'brand_name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:50',
                'whatsapp' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'address_shop' => 'nullable|string|max:500',
                'address_office' => 'nullable|string|max:500',
                'gstin' => 'nullable|string|max:50',
                'instagram' => 'nullable|string|max:500',
                'facebook' => 'nullable|string|max:500',
                'linkedin' => 'nullable|string|max:500',
                'pinterest' => 'nullable|string|max:500',
                'youtube' => 'nullable|string|max:500',
                'default_meta_title' => 'nullable|string|max:255',
                'default_meta_description' => 'nullable|string|max:500',
                'shipping_note' => 'nullable|string|max:2000',
                'warranty_duration' => 'nullable|string|max:120',
                'grievance_officer_name' => 'nullable|string|max:255',
                'grievance_officer_email' => 'nullable|email|max:255',
                'grievance_officer_phone' => 'nullable|string|max:50',
                'legal_last_updated' => 'nullable|string|max:50',
            ], $finishRules));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        try {
            SiteSetting::setValue('brand', [
                'name' => $validated['brand_name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address_shop' => $validated['address_shop'] ?? null,
                'address_office' => $validated['address_office'] ?? null,
            ]);

            SiteSetting::setValue('social', [
                'instagram' => $this->normalizeUrl($validated['instagram'] ?? null),
                'facebook' => $this->normalizeUrl($validated['facebook'] ?? null),
                'linkedin' => $this->normalizeUrl($validated['linkedin'] ?? null),
                'pinterest' => $this->normalizeUrl($validated['pinterest'] ?? null),
                'youtube' => $this->normalizeUrl($validated['youtube'] ?? null),
                'whatsapp' => $validated['whatsapp'] ?? null,
            ]);

            SiteSetting::setValue('seo', [
                'default_title' => $validated['default_meta_title'] ?? null,
                'default_description' => $validated['default_meta_description'] ?? null,
            ]);

            SiteSetting::setValue('store', [
                'shipping_note' => $validated['shipping_note'] ?? null,
                'warranty_duration' => $validated['warranty_duration'] ?? null,
            ]);

            SiteSetting::setValue('business', [
                'brand_name' => $validated['brand_name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address_office'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
                'grievance_officer_name' => $validated['grievance_officer_name'] ?? null,
                'grievance_officer_email' => $validated['grievance_officer_email'] ?? null,
                'grievance_officer_phone' => $validated['grievance_officer_phone'] ?? null,
            ]);

            if (filled($validated['legal_last_updated'] ?? null)) {
                SiteSetting::setValue('legal_last_updated', $validated['legal_last_updated']);
            }

            $finishImages = \App\Support\FinishSwatches::imageOverrides();

            foreach (config('finishes.swatches', []) as $swatch) {
                $slug = $swatch['slug'];
                $fileKey = 'finish_image_'.$slug;
                $urlKey = 'finish_url_'.$slug;

                if ($request->boolean('finish_clear_'.$slug)) {
                    unset($finishImages[$slug]);
                } elseif ($request->hasFile($fileKey)) {
                    $path = $request->file($fileKey)->store('finishes', 'public');
                    $finishImages[$slug] = 'storage/'.$path;
                } elseif (filled($request->input($urlKey))) {
                    $url = $this->normalizeUrl($request->input($urlKey));
                    if ($url) {
                        $finishImages[$slug] = $url;
                    } else {
                        return back()->withInput()->with('error', "Invalid image URL for {$swatch['name']}. Use https://example.com/image.jpg");
                    }
                }
            }

            SiteSetting::setValue('finish_swatches', $finishImages);
        } catch (\Throwable $e) {
            Log::error('Site settings save failed.', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Could not save settings: '.$e->getMessage());
        }

        return back()->with('success', 'Site settings saved.');
    }

    private function normalizeUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim($value);

        if (! preg_match('~^https?://~i', $value)) {
            $value = 'https://'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }
}
