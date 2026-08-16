<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\CollectionContent;
use App\Support\CollectionNav;
use App\Support\HeroAdminFields;
use App\Support\StorefrontRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CollectionPageAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index()
    {
        $slugs = CollectionContent::slugs();
        $pages = [];
        $categories = Category::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        foreach ($slugs as $slug) {
            $category = $categories->get($slug);
            $pages[] = [
                'slug' => $slug,
                'title' => CollectionContent::labelForSlug($slug),
                'is_active' => $category?->is_active ?? true,
                'hide_from_nav' => $category?->hide_from_nav ?? false,
                'is_custom' => ! CollectionContent::isConfigSlug($slug),
                'storefront_url' => StorefrontRoutes::shopCategoryUrl($slug),
            ];
        }

        return view('admin.collection-pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.collection-pages.create');
    }

    public function store(Request $request)
    {
        abort_unless(Schema::hasTable('site_settings'), 503, 'Database table site_settings is missing. Run: php artisan migrate --force');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(['mirror-frames']),
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $slug = Str::slug(is_string($value) && $value !== '' ? $value : (string) $request->input('name'));

                    if (in_array($slug, CollectionContent::slugs(), true)) {
                        $fail('A collection page with this slug already exists.');
                    }
                },
            ],
        ]);

        $slug = Str::slug($validated['slug'] ?: $validated['name']);

        $category = Category::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $validated['name'],
                'section' => Product::SECTION_SHOP,
                'is_active' => true,
                'hide_from_nav' => false,
                'sort_order' => (int) Category::max('sort_order') + 1,
            ]
        );

        $category->update([
            'name' => $validated['name'],
            'section' => Product::SECTION_SHOP,
            'is_active' => true,
            'hide_from_nav' => false,
        ]);

        $pages = CollectionContent::normalizeStoredPages(SiteSetting::getValue('collection_pages', []) ?? []);
        $pages[$slug] = [
            'gallery_title' => $validated['name'],
            'hero' => ['title' => $validated['name']],
        ];
        SiteSetting::setValue('collection_pages', $pages);

        CollectionNav::addShopCollectionLink($slug, $validated['name']);

        return redirect()
            ->route('admin.collection-pages.edit', $slug)
            ->with('success', 'Collection page created. You can now edit hero and SEO content.');
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
            return back()->withInput()->with('error', 'Upload too large for the server limit. Save text changes first, then upload one image at a time (max 5 MB each).');
        }

        $validated = $request->validate(array_merge([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'intro_title' => 'nullable|string|max:255',
            'intro_body' => 'nullable|string|max:5000',
            'gallery_title' => 'nullable|string|max:255',
        ], HeroAdminFields::validationRules('hero')));

        $storedHero = data_get($this->storedPage($slug), 'hero', data_get(CollectionContent::page($slug), 'hero', []));
        $storedHero = is_array($storedHero) ? $storedHero : [];

        $heroImages = $this->persistResponsiveHeroFlatFields($request, 'hero', $storedHero, 'collections');
        $hero = HeroAdminFields::buildFromRequest($request, 'hero', $storedHero, $heroImages);
        $hero = CollectionContent::normalizeStoredHero($slug, $hero);

        $pages = CollectionContent::normalizeStoredPages(SiteSetting::getValue('collection_pages', []) ?? []);
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

        foreach (CollectionContent::overrideKeysFor($slug) as $legacyKey) {
            if ($legacyKey !== $slug) {
                unset($pages[$legacyKey]);
            }
        }

        try {
            SiteSetting::setValue('collection_pages', $pages);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Could not save collection page. ('.$e->getMessage().')');
        }

        $label = HeroAdminFields::displayTitle($hero);
        if ($label !== '—') {
            CollectionNav::addShopCollectionLink($slug, $label);
        }

        return redirect()
            ->route('admin.collection-pages.index')
            ->with('success', ucfirst(str_replace('-', ' ', $slug)).' updated. Saved hero title: "'.$label.'"');
    }

    public function destroy(string $slug)
    {
        abort_unless(in_array($slug, CollectionContent::slugs(), true), 404);

        $this->removeCollectionPage($slug);

        return redirect()
            ->route('admin.collection-pages.index')
            ->with('success', ucfirst(str_replace('-', ' ', $slug)).' removed from the site.');
    }

    public function bulk(Request $request)
    {
        abort_unless(Schema::hasTable('site_settings'), 503);

        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,hide_from_nav,show_in_nav,delete',
            'slugs' => 'required|array|min:1',
            'slugs.*' => 'string|max:255',
        ]);

        $slugs = array_values(array_intersect($validated['slugs'], CollectionContent::slugs()));
        abort_if($slugs === [], 422, 'No valid collection pages selected.');

        $count = count($slugs);

        match ($validated['action']) {
            'activate' => $this->bulkUpdateCategories($slugs, ['is_active' => true]),
            'deactivate' => $this->bulkUpdateCategories($slugs, ['is_active' => false]),
            'hide_from_nav' => $this->bulkUpdateCategories($slugs, ['hide_from_nav' => true]),
            'show_in_nav' => $this->bulkUpdateCategories($slugs, ['hide_from_nav' => false]),
            'delete' => collect($slugs)->each(fn (string $slug) => $this->removeCollectionPage($slug)),
        };

        $message = match ($validated['action']) {
            'activate' => "{$count} collection page(s) activated.",
            'deactivate' => "{$count} collection page(s) deactivated.",
            'hide_from_nav' => "{$count} collection page(s) hidden from main menu.",
            'show_in_nav' => "{$count} collection page(s) shown in main menu.",
            'delete' => "{$count} collection page(s) removed.",
        };

        return redirect()
            ->route('admin.collection-pages.index')
            ->with('success', $message);
    }

    /** @param  list<string>  $slugs */
    private function bulkUpdateCategories(array $slugs, array $attributes): void
    {
        foreach ($slugs as $slug) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                array_merge([
                    'name' => StorefrontRoutes::shopCategoryLabel($slug),
                    'section' => Product::SECTION_SHOP,
                ], $attributes)
            );
        }
    }

    private function removeCollectionPage(string $slug): void
    {
        CollectionNav::removeShopCollectionLink($slug);

        if (! CollectionContent::isConfigSlug($slug)) {
            $pages = CollectionContent::normalizeStoredPages(SiteSetting::getValue('collection_pages', []) ?? []);
            unset($pages[$slug]);
            SiteSetting::setValue('collection_pages', $pages);
        }

        $category = Category::query()->where('slug', $slug)->first();

        if ($category && ! $category->products()->exists() && ! CollectionContent::isConfigSlug($slug)) {
            $category->delete();

            return;
        }

        if ($category) {
            $category->update([
                'is_active' => false,
                'hide_from_nav' => true,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function storedPage(string $slug): array
    {
        return CollectionContent::storedOverrides($slug);
    }
}
