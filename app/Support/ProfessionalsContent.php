<?php

namespace App\Support;

use App\Models\Project;

class ProfessionalsContent
{
    public static function all(): array
    {
        return config('professionals', []);
    }

    /** @return \Illuminate\Support\Collection<int, Project> */
    public static function featuredProjects()
    {
        $slugs = config('professionals.featured_projects.slugs', []);

        if ($slugs === []) {
            return Project::query()->where('is_active', true)->where('is_featured', true)->limit(3)->get();
        }

        return Project::query()
            ->whereIn('slug', $slugs)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($p) => array_search($p->slug, $slugs, true));
    }
}
