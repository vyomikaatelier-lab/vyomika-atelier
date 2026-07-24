<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SiteSettingAdminController extends Controller
{
    use HandlesAdminUploads;

    public function edit()
    {
        $heroOverride = SiteSetting::getValue('hero', []);
        $homepage = SiteSetting::getValue('homepage', []);
        $nav = SiteSetting::getValue('nav');
        $defaultSlides = config('site.hero.slides', []);
        $defaultAnnouncement = config('site.announcement', []);

        return view('admin.settings.edit', [
            'brand' => array_merge(config('site.brand', []), SiteSetting::getValue('brand', [])),
            'social' => array_merge(config('site.social', []), SiteSetting::getValue('social', [])),
            'seo' => array_merge(config('site.seo', []), SiteSetting::getValue('seo', [])),
            'store' => array_merge(config('site.store', []), SiteSetting::getValue('store', [])),
            'analytics' => SiteSetting::getValue('analytics', []) ?? [],
            'business' => array_merge(config('legal.business', []), SiteSetting::getValue('business', [])),
            'legalLastUpdated' => SiteSetting::getValue('legal_last_updated', config('legal.last_updated')),
            'finishSwatches' => config('finishes.swatches', []),
            'finishSwatchImages' => \App\Support\FinishSwatches::imageOverrides(),
            'navJson' => json_encode($nav ?? config('site.nav', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'heroSlides' => $this->heroSlidesForForm($heroOverride, $defaultSlides),
            'announcementText' => data_get($homepage, 'announcement.text', $defaultAnnouncement['text'] ?? ''),
            'announcementLinkLabel' => data_get($homepage, 'announcement.link_label', $defaultAnnouncement['link_label'] ?? ''),
            'announcementLinkHref' => data_get($homepage, 'announcement.link_href', $defaultAnnouncement['link_href'] ?? ''),
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
                'default_og_image' => 'nullable|string|max:500',
                'ga4_measurement_id' => 'nullable|string|max:50',
                'gsc_verification' => 'nullable|string|max:120',
                'shipping_note' => 'nullable|string|max:2000',
                'warranty_duration' => 'nullable|string|max:120',
                'grievance_officer_name' => 'nullable|string|max:255',
                'grievance_officer_email' => 'nullable|email|max:255',
                'grievance_officer_phone' => 'nullable|string|max:50',
                'legal_last_updated' => 'nullable|string|max:50',
                'nav_json' => 'nullable|string',
                'announcement_text' => 'nullable|string|max:500',
                'announcement_link_label' => 'nullable|string|max:120',
                'announcement_link_href' => 'nullable|string|max:500',
            ], $finishRules, $this->heroSlideValidationRules()));
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
                'default_og_image' => $validated['default_og_image'] ?? null,
            ]);

            SiteSetting::setValue('analytics', [
                'ga4_measurement_id' => $validated['ga4_measurement_id'] ?? null,
                'gsc_verification' => $validated['gsc_verification'] ?? null,
            ]);

            SiteSetting::setValue('store', [
                'shipping_note' => $validated['shipping_note'] ?? null,
                'warranty_duration' => $validated['warranty_duration'] ?? null,
            ]);

            SiteSetting::setValue('business', [
                'brand_name' => $validated['brand_name'],
                'legal_name' => config('legal.business.legal_name', 'VYOMIKA SALES'),
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

            if ($request->filled('nav_json')) {
                $nav = json_decode($request->input('nav_json'), true);
                if (! is_array($nav)) {
                    return back()->withInput()->withErrors(['nav_json' => 'Navigation must be valid JSON array.']);
                }
                SiteSetting::setValue('nav', $nav);
            }

            SiteSetting::setValue('hero', [
                'slides' => $this->buildHeroSlidesFromRequest($request, SiteSetting::getValue('hero', [])),
            ]);

            SiteSetting::setValue('homepage', [
                'announcement' => array_filter([
                    'text' => $validated['announcement_text'] ?? null,
                    'link_label' => $validated['announcement_link_label'] ?? null,
                    'link_href' => $validated['announcement_link_href'] ?? null,
                ], fn ($value) => filled($value)),
            ]);

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

    /** @param  array<string, mixed>  $heroOverride
     * @param  list<array<string, mixed>>  $defaultSlides
     * @return list<array<string, mixed>>
     */
    private function heroSlidesForForm(array $heroOverride, array $defaultSlides): array
    {
        $storedSlides = is_array($heroOverride['slides'] ?? null) ? $heroOverride['slides'] : [];

        return collect($defaultSlides)->map(function (array $defaults, int $index) use ($storedSlides, $heroOverride) {
            $stored = is_array($storedSlides[$index] ?? null) ? $storedSlides[$index] : [];

            if ($index === 0 && $stored === [] && isset($heroOverride['title'], $heroOverride['subtitle'], $heroOverride['image'])) {
                $stored = [
                    'title' => $heroOverride['title'] ?? null,
                    'description' => $heroOverride['subtitle'] ?? null,
                    'image' => $heroOverride['image'] ?? null,
                ];
            }

            return [
                'kicker' => $stored['kicker'] ?? $defaults['kicker'] ?? '',
                'title' => $stored['title'] ?? $defaults['title'] ?? '',
                'description' => $stored['description'] ?? $defaults['description'] ?? '',
                'image' => $stored['image'] ?? $defaults['image'] ?? '',
                'cta_label' => $stored['cta_label'] ?? $defaults['cta_label'] ?? '',
                'cta_href' => $stored['cta_href'] ?? $defaults['cta_href'] ?? '',
            ];
        })->values()->all();
    }

    /** @return array<string, string> */
    private function heroSlideValidationRules(): array
    {
        $rules = [];

        foreach (array_keys(config('site.hero.slides', [])) as $index) {
            $prefix = "hero_slides.{$index}";
            $rules["{$prefix}.kicker"] = 'nullable|string|max:120';
            $rules["{$prefix}.title"] = 'nullable|string|max:255';
            $rules["{$prefix}.description"] = 'nullable|string|max:1000';
            $rules["{$prefix}.image"] = 'nullable|string|max:500';
            $rules["{$prefix}.image_file"] = 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120';
            $rules["{$prefix}.image_remove"] = 'nullable|boolean';
            $rules["{$prefix}.cta_label"] = 'nullable|string|max:120';
            $rules["{$prefix}.cta_href"] = 'nullable|string|max:500';
        }

        return $rules;
    }

    /** @param  array<string, mixed>  $existingHero
     * @return list<array<string, mixed>>
     */
    private function buildHeroSlidesFromRequest(Request $request, array $existingHero): array
    {
        $defaultSlides = config('site.hero.slides', []);
        $existingSlides = is_array($existingHero['slides'] ?? null) ? $existingHero['slides'] : [];
        $slides = [];

        foreach (array_keys($defaultSlides) as $index) {
            $prefix = "hero_slides.{$index}";
            $defaults = $defaultSlides[$index];
            $stored = is_array($existingSlides[$index] ?? null) ? $existingSlides[$index] : [];

            if ($index === 0 && $stored === [] && filled($existingHero['image'] ?? null)) {
                $stored['image'] = $existingHero['image'];
            }

            $currentImage = $stored['image'] ?? $defaults['image'] ?? null;
            $image = $this->resolveImageField(
                $request,
                "{$prefix}.image_file",
                "{$prefix}.image",
                $currentImage,
                'hero'
            );

            $slides[] = array_filter([
                'kicker' => $request->input("{$prefix}.kicker"),
                'title' => $request->input("{$prefix}.title"),
                'description' => $request->input("{$prefix}.description"),
                'image' => $image,
                'cta_label' => $request->input("{$prefix}.cta_label"),
                'cta_href' => $request->input("{$prefix}.cta_href"),
            ], fn ($value) => filled($value));
        }

        return $slides;
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
