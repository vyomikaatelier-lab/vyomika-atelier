<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index(Request $request)
    {
        $query = Project::query()->orderBy('display_order')->orderByDesc('completed_at');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('title', 'like', "%{$q}%");
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'published');
        }

        $projects = $query->paginate(15)->withQueryString();

        return view('admin.projects.index', compact('projects'));
    }

    public function create()
    {
        return view('admin.projects.form');
    }

    public function store(Request $request)
    {
        $validated = $this->validateProject($request);
        $validated['slug'] = Str::slug($request->input('slug') ?: $validated['title']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['display_order'] = $request->integer('display_order', Project::max('display_order') + 1);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', null, 'projects');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', null, 'projects');
        $validated['materials'] = $this->parseMultilineUrls($request->input('materials_list'));
        $validated['finishes'] = $this->parseMultilineUrls($request->input('finishes_list'));

        Project::create($validated);

        return redirect()->route('admin.projects.index')->with('success', 'Project created.');
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $this->validateProject($request, $project);
        $validated['slug'] = Str::slug($request->input('slug') ?: $validated['title']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', $project->image, 'projects');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', $project->gallery, 'projects');
        $validated['materials'] = $this->parseMultilineUrls($request->input('materials_list'));
        $validated['finishes'] = $this->parseMultilineUrls($request->input('finishes_list'));
        $validated['display_order'] = $request->integer('display_order', $project->display_order);

        $project->update($validated);

        return redirect()->route('admin.projects.index')->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $this->deleteStoredPath($project->image);

        foreach ($project->gallery ?? [] as $path) {
            $this->deleteStoredPath($path);
        }

        $project->delete();

        return redirect()->route('admin.projects.index')->with('success', 'Project deleted.');
    }

    private function validateProject(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($project?->id)],
            'summary' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1990|max:2100',
            'category' => 'nullable|string|max:50',
            'client' => 'nullable|string|max:255',
            'design_details' => 'nullable|string',
            'scope' => 'nullable|string',
            'challenges' => 'nullable|string',
            'testimonial_quote' => 'nullable|string|max:2000',
            'testimonial_author' => 'nullable|string|max:255',
            'testimonial_role' => 'nullable|string|max:255',
            'completed_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'gallery_urls' => 'nullable|string',
            'gallery_files' => 'nullable|array',
            'gallery_files.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'materials_list' => 'nullable|string',
            'finishes_list' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
        ]);
    }
}
