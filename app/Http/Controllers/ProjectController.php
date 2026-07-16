<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

        return view('projects.index', compact('projects', 'activeCategory', 'categories', 'page'));
    }

    public function show(string $slug)
    {
        $project = Project::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('projects.show', compact('project'));
    }
}
