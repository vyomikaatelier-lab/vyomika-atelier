<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Seo\ProjectSeo;
use App\Support\Seo\StaticPageSeo;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $activeCategory = $request->query('category', '');
        $categories = config('projects.categories', []);

        $query = Project::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->latest('completed_at');

        if ($activeCategory !== '' && array_key_exists($activeCategory, Project::categoryLabels())) {
            $query->where('category', $activeCategory);
        }

        $projects = $query->paginate(12)->withQueryString();
        $page = config('projects', []);

        return view('projects.index', [
            'projects' => $projects,
            'activeCategory' => $activeCategory,
            'categories' => $categories,
            'page' => $page,
            'pageSeo' => StaticPageSeo::forSlug('projects'),
        ]);
    }

    public function show(string $slug)
    {
        $project = Project::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('projects.show', [
            'project' => $project,
            'pageSeo' => ProjectSeo::pageData($project),
        ]);
    }
}
