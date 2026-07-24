<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\LandingPageContent;
use Illuminate\Http\Request;

class IndependentLandingAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index()
    {
        $pages = [];

        foreach (LandingPageContent::slugs() as $slug) {
            $page = LandingPageContent::page($slug);
            $pages[] = [
                'slug' => $slug,
                'label' => LandingPageContent::label($slug),
                'title' => data_get($page, 'hero.title', LandingPageContent::label($slug)),
            ];
        }

        return view('admin.independent-pages.index', compact('pages'));
    }

    public function edit(string $slug)
    {
        abort_unless(in_array($slug, LandingPageContent::slugs(), true), 404);

        $page = LandingPageContent::page($slug);
        $label = LandingPageContent::label($slug);

        return view('admin.independent-pages.form', compact('slug', 'page', 'label'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(in_array($slug, LandingPageContent::slugs(), true), 404);

        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:1000',
            'hero_image' => 'nullable|string|max:500',
            'hero_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'intro_title' => 'nullable|string|max:255',
            'intro_body' => 'nullable|string|max:5000',
        ]);

        $pages = SiteSetting::getValue('landing_pages', []) ?? [];
        $currentHeroImage = data_get($pages, "{$slug}.hero.image")
            ?? data_get(LandingPageContent::page($slug), 'hero.image');

        $heroImage = $this->resolveImageField(
            $request,
            'hero_image_file',
            'hero_image',
            $currentHeroImage,
            'landing-pages'
        );

        $override = array_filter([
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'hero' => array_filter([
                'title' => $validated['hero_title'] ?? null,
                'subtitle' => $validated['hero_subtitle'] ?? null,
                'image' => $heroImage,
            ]),
            'intro' => array_filter([
                'title' => $validated['intro_title'] ?? null,
                'body' => $validated['intro_body'] ?? null,
            ]),
        ], fn ($value) => $value !== null && $value !== []);

        $pages[$slug] = array_replace_recursive($pages[$slug] ?? [], $override);
        SiteSetting::setValue('landing_pages', $pages);

        return redirect()
            ->route('admin.independent-pages.index')
            ->with('success', LandingPageContent::label($slug).' updated.');
    }
}
