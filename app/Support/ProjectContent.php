<?php

namespace App\Support;

use App\Models\Project;

class ProjectContent
{
    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return Project::categoryLabels();
    }

    public static function indexConfig(): array
    {
        return config('projects', []);
    }
}
