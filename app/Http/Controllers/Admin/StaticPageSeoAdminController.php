<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\StaticPageContent;
use Illuminate\Http\Request;

class StaticPageSeoAdminController extends Controller
{
    public function index()
    {
        $pages = [];
        foreach (StaticPageContent::slugs() as $slug) {
            $page = StaticPageContent::page($slug);
            $pages[] = [
                'slug' => $slug,
                'label' => StaticPageContent::label($slug),
                'title' => $page['meta_title'] ?? StaticPageContent::label($slug),
            ];
        }

        return view('admin.static-pages.index', compact('pages'));
    }

    public function edit(string $slug)
    {
        abort_unless(in_array($slug, StaticPageContent::slugs(), true), 404);

        $page = StaticPageContent::page($slug);
        $label = StaticPageContent::label($slug);

        return view('admin.static-pages.form', compact('slug', 'page', 'label'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(in_array($slug, StaticPageContent::slugs(), true), 404);

        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'canonical' => 'nullable|string|max:500',
            'primary_keyword' => 'nullable|string|max:120',
            'h1' => 'nullable|string|max:255',
            'intro' => 'nullable|string|max:8000',
            'robots' => 'nullable|in:index,noindex',
            'faq_q' => 'nullable|array',
            'faq_a' => 'nullable|array',
        ]);

        $faqs = [];
        foreach ($validated['faq_q'] ?? [] as $i => $q) {
            $q = trim((string) $q);
            $a = trim((string) ($validated['faq_a'][$i] ?? ''));
            if ($q === '' && $a === '') {
                continue;
            }
            $faqs[] = ['q' => $q, 'a' => $a];
        }

        $all = SiteSetting::getValue('static_pages', []) ?? [];
        $all[$slug] = [
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'og_title' => $validated['og_title'] ?? null,
            'og_description' => $validated['og_description'] ?? null,
            'og_image' => $validated['og_image'] ?? null,
            'canonical' => $validated['canonical'] ?? null,
            'primary_keyword' => $validated['primary_keyword'] ?? null,
            'h1' => $validated['h1'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'robots' => $validated['robots'] ?? 'index',
            'faqs' => $faqs,
        ];
        SiteSetting::setValue('static_pages', $all);

        return redirect()
            ->route('admin.static-pages.edit', $slug)
            ->with('success', StaticPageContent::label($slug).' SEO saved.');
    }
}
