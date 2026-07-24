<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\CollectionContent;
use App\Support\ResponsiveHero;
use Illuminate\Http\Request;

class CollectionPageAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index()
    {
        $slugs = CollectionContent::slugs();
        $pages = [];

        foreach ($slugs as $slug) {
            $page = CollectionContent::page($slug) ?? [];
            $pages[] = [
                'slug' => $slug,
                'title' => data_get($page, 'hero.title', ucfirst(str_replace('-', ' ', $slug))),
            ];
        }

        return view('admin.collection-pages.index', compact('pages'));
    }

    public function edit(string $slug)
    {
        abort_unless(in_array($slug, CollectionContent::slugs(), true), 404);

        $page = CollectionContent::page($slug) ?? [];

        return view('admin.collection-pages.form', compact('slug', 'page'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(in_array($slug, CollectionContent::slugs(), true), 404);

        if (! \Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            return back()->with('error', 'Database table site_settings is missing. Run: php artisan migrate --force');
        }

        if ($this->multipartPayloadFailed($request, 'hero_title')) {
            return back()->with('error', 'Upload too large for the server limit. Save text changes first, then upload one image at a time (max 5 MB each).');
        }

        $validated = $request->validate(array_merge([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:1000',
            'intro_title' => 'nullable|string|max:255',
            'intro_body' => 'nullable|string|max:5000',
            'gallery_title' => 'nullable|string|max:255',
        ], ResponsiveHero::flatValidationRules('hero')));

        $pages = SiteSetting::getValue('collection_pages', []) ?? [];
        $storedHero = data_get($pages, "{$slug}.hero", data_get(CollectionContent::page($slug), 'hero', []));
        $storedHero = is_array($storedHero) ? $storedHero : [];

        $heroImages = $this->persistResponsiveHeroFlatFields($request, 'hero', $storedHero, 'collections');

        $override = array_filter([
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'gallery_title' => $validated['gallery_title'] ?? null,
            'hero' => array_filter(array_merge([
                'title' => $validated['hero_title'] ?? null,
                'subtitle' => $validated['hero_subtitle'] ?? null,
            ], $heroImages)),
            'intro' => array_filter([
                'title' => $validated['intro_title'] ?? null,
                'body' => $validated['intro_body'] ?? null,
            ]),
        ], fn ($value) => $value !== null && $value !== []);

        $pages[$slug] = array_replace_recursive($pages[$slug] ?? [], $override);
        SiteSetting::setValue('collection_pages', $pages);

        return redirect()->route('admin.collection-pages.index')->with('success', 'Collection page updated.');
    }
}
