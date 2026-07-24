<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;

class LegalPageAdminController extends Controller
{
    public function index()
    {
        $pages = LegalPage::query()->orderBy('slug')->get();

        return view('admin.legal.index', compact('pages'));
    }

    public function edit(LegalPage $legal)
    {
        return view('admin.legal.form', ['page' => $legal]);
    }

    public function update(Request $request, LegalPage $legal)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'content_updated_at' => 'nullable|date',
            'section_headings' => 'nullable|array',
            'section_headings.*' => 'nullable|string|max:255',
            'section_paragraphs' => 'nullable|array',
            'section_paragraphs.*' => 'nullable|string',
            'sections_json' => 'nullable|string',
        ]);

        $sections = $this->parseSections($request);
        if ($sections === null) {
            return back()->withErrors(['sections_json' => 'Sections must be valid JSON or include at least one heading with content.'])->withInput();
        }

        $legal->update([
            'title' => $validated['title'],
            'meta_title' => $validated['meta_title'],
            'meta_description' => $validated['meta_description'],
            'sections' => $sections,
            'content_updated_at' => $validated['content_updated_at'] ?? now()->toDateString(),
        ]);

        return redirect()->route('admin.legal.index')->with('success', 'Legal page updated.');
    }

    /** @return array<int, array{heading: string, paragraphs: array<int, string>}>|null */
    private function parseSections(Request $request): ?array
    {
        if ($request->filled('sections_json')) {
            $sections = json_decode($request->input('sections_json'), true);

            return is_array($sections) ? $sections : null;
        }

        $headings = (array) $request->input('section_headings', []);
        $paragraphBlocks = (array) $request->input('section_paragraphs', []);
        $sections = [];

        foreach ($headings as $index => $heading) {
            $heading = trim((string) $heading);
            $rawParagraphs = trim((string) ($paragraphBlocks[$index] ?? ''));

            if ($heading === '' && $rawParagraphs === '') {
                continue;
            }

            if ($heading === '') {
                return null;
            }

            $paragraphs = array_values(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $rawParagraphs) ?: []
            )));

            $sections[] = [
                'heading' => $heading,
                'paragraphs' => $paragraphs !== [] ? $paragraphs : [''],
            ];
        }

        return $sections !== [] ? $sections : null;
    }
}
