<?php

namespace App\Http\Controllers\Admin;

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
            'sections_json' => 'required|string',
        ]);

        $sections = json_decode($validated['sections_json'], true);
        if (! is_array($sections)) {
            return back()->withErrors(['sections_json' => 'Sections must be valid JSON.'])->withInput();
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
}
