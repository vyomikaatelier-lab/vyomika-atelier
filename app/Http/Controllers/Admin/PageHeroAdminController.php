<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\PageHeroContent;
use App\Support\ResponsiveHero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PageHeroAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index()
    {
        $pages = [];

        foreach (PageHeroContent::definitions() as $slug => $definition) {
            $hero = PageHeroContent::hero($slug);
            $pages[] = [
                'slug' => $slug,
                'label' => $definition['label'],
                'group' => $definition['group'],
                'title' => $hero['title'] ?? $definition['label'],
                'preview_url' => PageHeroContent::previewUrl($slug),
            ];
        }

        return view('admin.page-heroes.index', compact('pages'));
    }

    public function edit(string $slug)
    {
        abort_unless(in_array($slug, PageHeroContent::slugs(), true), 404);

        $page = PageHeroContent::hero($slug);
        $stored = $this->storedHero($slug);
        $label = PageHeroContent::label($slug);
        $previewUrl = PageHeroContent::previewUrl($slug);

        return view('admin.page-heroes.form', compact('slug', 'page', 'stored', 'label', 'previewUrl'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(in_array($slug, PageHeroContent::slugs(), true), 404);

        if (! Schema::hasTable('site_settings')) {
            return back()->with('error', 'Database table site_settings is missing. Run: php artisan migrate --force');
        }

        if ($this->multipartPayloadFailed($request)) {
            return back()->with('error', 'Upload too large for the server limit. Save text changes first, then upload one image at a time (max 5 MB each).');
        }

        $validated = $request->validate(array_merge([
            'hero_label' => 'nullable|string|max:120',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:2000',
        ], ResponsiveHero::flatValidationRules('hero')));

        $stored = $this->storedHero($slug);
        $currentHero = array_merge(PageHeroContent::defaultHero($slug), $stored);
        $heroImages = $this->persistResponsiveHeroFlatFields($request, 'hero', $currentHero, 'page-heroes');

        $hero = array_merge($stored, [
            'label' => $validated['hero_label'] ?? null,
            'title' => $validated['hero_title'] ?? null,
            'subtitle' => $validated['hero_subtitle'] ?? null,
        ], $heroImages);

        foreach (ResponsiveHero::storageKeys() as $storageKey) {
            $flatField = ResponsiveHero::flatFieldForStorageKey('hero', $storageKey);
            if ($request->boolean($flatField.'_remove')) {
                unset($hero[$storageKey]);
            }
        }

        $pages = SiteSetting::getValue('page_heroes', []) ?? [];
        $pages[$slug] = $hero;

        try {
            SiteSetting::setValue('page_heroes', $pages);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Could not save hero settings. ('.$e->getMessage().')');
        }

        return redirect()
            ->route('admin.page-heroes.edit', ['slug' => $slug, 'saved' => 1])
            ->with('success', PageHeroContent::label($slug).' hero saved. Title: "'.($hero['title'] ?: '—').'"');
    }

    /** @return array<string, mixed> */
    private function storedHero(string $slug): array
    {
        $pages = SiteSetting::getValue('page_heroes', []) ?? [];
        $stored = $pages[$slug] ?? null;

        return is_array($stored) ? $stored : [];
    }
}
