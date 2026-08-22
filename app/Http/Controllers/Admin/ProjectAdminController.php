<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index(Request $request)
    {
        $query = Project::query()->orderBy('display_order')->orderByDesc('id');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('project_name', 'like', "%{$q}%");
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
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', 'Upload too large for the server limit. Save text changes first, then upload the image (max 5 MB).');
        }

        $validated = $this->validateProject($request);
        $validated['is_active'] = $this->checkboxBoolean($request, 'is_active');
        $validated['display_order'] = $request->integer('display_order', (int) Project::max('display_order') + 1);
        $validated['image_path'] = $this->resolveImageField($request, 'image_file', 'image_path', null, 'projects');

        Project::create($validated);

        return redirect()->route('admin.projects.index')->with('success', 'Project work item created.');
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', 'Upload too large for the server limit. Save text changes first, then upload the image (max 5 MB).');
        }

        $validated = $this->validateProject($request);
        $validated['is_active'] = $this->checkboxBoolean($request, 'is_active');
        $validated['display_order'] = $request->integer('display_order', $project->display_order);
        $validated['image_path'] = $this->resolveImageField($request, 'image_file', 'image_path', $project->image_path, 'projects');

        $project->update($validated);

        return redirect()->route('admin.projects.index')->with('success', 'Project work item updated.');
    }

    public function destroy(Project $project)
    {
        $this->deleteStoredPath($project->image_path);
        $project->delete();

        return redirect()->route('admin.projects.index')->with('success', 'Project work item deleted.');
    }

    private function validateProject(Request $request): array
    {
        return $request->validate([
            'project_name' => 'required|string|max:255',
            'work_type' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:255',
            'client' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:120',
            'price' => 'nullable|string|max:120',
            'description' => 'nullable|string',
            'image_path' => 'nullable|string|max:500',
            'image_alt' => 'nullable|string|max:255',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'display_order' => 'nullable|integer|min:0',
        ]);
    }
}
