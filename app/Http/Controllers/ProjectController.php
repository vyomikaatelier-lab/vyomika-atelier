<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Seo\StaticPageSeo;
use App\Support\StaticPageContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->get();

        $pageContent = StaticPageContent::page('projects');

        return view('projects.index', [
            'projects' => $projects,
            'pageContent' => $pageContent,
            'pageSeo' => StaticPageSeo::forSlug('projects'),
        ]);
    }

    public function redirectLegacy(string $slug): RedirectResponse
    {
        return redirect()->route('projects.index', status: 301);
    }
}
