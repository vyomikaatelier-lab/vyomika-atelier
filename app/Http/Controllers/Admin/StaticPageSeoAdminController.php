<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\SavesStaticPageSeoFields;
use App\Http\Controllers\Controller;
use App\Support\StaticPageContent;
use Illuminate\Http\Request;

class StaticPageSeoAdminController extends Controller
{
    use SavesStaticPageSeoFields;
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

        $validated = $request->validate(array_merge($this->staticPageSeoValidationRules(), [
            'h1' => 'nullable|string|max:255',
            'intro' => 'nullable|string|max:8000',
            'faq_q' => 'nullable|array',
            'faq_a' => 'nullable|array',
        ]));

        $faqs = [];
        foreach ($validated['faq_q'] ?? [] as $i => $q) {
            $q = trim((string) $q);
            $a = trim((string) ($validated['faq_a'][$i] ?? ''));
            if ($q === '' && $a === '') {
                continue;
            }
            $faqs[] = ['q' => $q, 'a' => $a];
        }

        $this->persistStaticPageSeoFields($slug, $validated, [
            'h1' => $validated['h1'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'faqs' => $faqs,
        ]);

        return redirect()
            ->route('admin.static-pages.index')
            ->with('success', StaticPageContent::label($slug).' SEO saved.');
    }
}
