<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\CollectionContent;
use App\Support\ResponsiveHero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        $stored = $this->storedPage($slug);

        return view('admin.collection-pages.form', compact('slug', 'page', 'stored'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(in_array($slug, CollectionContent::slugs(), true), 404);

        if (! Schema::hasTable('site_settings')) {
            return back()->with('error', 'Database table site_settings is missing. Run: php artisan migrate --force');
        }

        if ($this->multipartPayloadFailed($request)) {
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

        $storedHero = data_get($this->storedPage($slug), 'hero', data_get(CollectionContent::page($slug), 'hero', []));
        $storedHero = is_array($storedHero) ? $storedHero : [];

        $heroImages = $this->persistResponsiveHeroFlatFields($request, 'hero', $storedHero, 'collections');

        $hero = array_merge($storedHero, [
            'title' => $validated['hero_title'] ?? null,
            'subtitle' => $validated['hero_subtitle'] ?? null,
        ], $heroImages);

        foreach (ResponsiveHero::storageKeys() as $storageKey) {
            $flatField = ResponsiveHero::flatFieldForStorageKey('hero', $storageKey);
            if ($request->boolean($flatField.'_remove')) {
                unset($hero[$storageKey]);
            }
        }

        $pages = SiteSetting::getValue('collection_pages', []) ?? [];
        $pages[$slug] = [
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'gallery_title' => $validated['gallery_title'] ?? null,
            'hero' => $hero,
            'intro' => [
                'title' => $validated['intro_title'] ?? null,
                'body' => $validated['intro_body'] ?? null,
            ],
        ];

        try {
            SiteSetting::setValue('collection_pages', $pages);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Could not save collection page. ('.$e->getMessage().')');
        }

        return redirect()
            ->route('admin.collection-pages.edit', ['slug' => $slug, 'saved' => 1])
            ->with('success', ucfirst(str_replace('-', ' ', $slug)).' updated. Saved hero title: "'.($hero['title'] ?: '—').'"');
    }

    /** @return array<string, mixed> */
    private function storedPage(string $slug): array
    {
        $pages = SiteSetting::getValue('collection_pages', []) ?? [];
        $stored = $pages[$slug] ?? null;

        return is_array($stored) ? $stored : [];
    }
}
