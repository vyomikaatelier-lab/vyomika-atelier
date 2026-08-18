<?php

namespace App\Support\Seo;

use App\Models\Project;

class ProjectSeo
{
    /** @return array<string, mixed> */
    public static function pageData(Project $project): array
    {
        return PageSeo::make([
            'title' => $project->seoTitle(),
            'description' => $project->seoDescription(),
            'canonical' => route('projects.show', $project->slug),
            'og_image' => $project->imageUrl(),
            'og_type' => 'article',
        ]);
    }
}
