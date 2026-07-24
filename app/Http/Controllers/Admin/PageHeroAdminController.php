<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\PageHeroContent;
use App\Support\ResponsiveHero;
use Illuminate\Http\Request;

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
        $label = PageHeroContent::label($slug);
        $previewUrl = PageHeroContent::previewUrl($slug);

        return view('admin.page-heroes.form', compact('slug', 'page', 'label', 'previewUrl'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(in_array($slug, PageHeroContent::slugs(), true), 404);

        if (! \Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            return back()->with('error', 'Database table site_settings is missing. Run: php artisan migrate --force');
        }

        if ($this->multipartPayloadFailed($request, 'hero_title')) {
            return back()->with('error', 'Upload too large for the server limit. Save text changes first, then upload one image at a time (max 5 MB each).');
        }

        $validated = $request->validate(array_merge([
            'hero_label' => 'nullable|string|max:120',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:2000',
        ], ResponsiveHero::flatValidationRules('hero')));

        $pages = SiteSetting::getValue('page_heroes', []) ?? [];
        $stored = is_array($pages[$slug] ?? null) ? $pages[$slug] : [];
        $currentHero = array_merge(PageHeroContent::defaultHero($slug), $stored);

        $heroImages = $this->persistResponsiveHeroFlatFields($request, 'hero', $currentHero, 'page-heroes');

        $pages[$slug] = array_filter(array_merge($stored, [
            'label' => $validated['hero_label'] ?? null,
            'title' => $validated['hero_title'] ?? null,
            'subtitle' => $validated['hero_subtitle'] ?? null,
        ], $heroImages));

        SiteSetting::setValue('page_heroes', $pages);

        return redirect()
            ->route('admin.page-heroes.edit', $slug)
            ->with('success', PageHeroContent::label($slug).' hero saved.');
    }
}
