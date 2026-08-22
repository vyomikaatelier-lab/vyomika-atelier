<?php

namespace App\Support;

use App\Models\Project;

class ProfessionalsContent
{
    public static function all(): array
    {
        $page = config('professionals', []);
        $page['hero'] = PageHeroContent::heroWithResolvedImages('professionals');

        return $page;
    }

    /** @return \Illuminate\Support\Collection<int, Project> */
    public static function featuredProjects()
    {
        return Project::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }
}
