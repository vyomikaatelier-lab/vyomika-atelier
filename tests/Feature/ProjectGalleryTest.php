<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_index_shows_active_gallery_items(): void
    {
        Project::query()->create([
            'project_name' => 'Andheri Loft',
            'work_type' => 'Residential',
            'city' => 'Mumbai',
            'description' => 'A brass and glass mezzanine study.',
            'image_path' => 'projects/andheri.jpg',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Andheri Loft', false)
            ->assertSee('A brass and glass mezzanine study.', false)
            ->assertSee('am-work-gallery', false);
    }

    public function test_legacy_project_slug_redirects_to_projects_index(): void
    {
        $this->get('/projects/andheri-loft')
            ->assertStatus(301)
            ->assertRedirect(route('projects.index'));
    }

    public function test_deactivated_project_is_hidden_from_gallery(): void
    {
        Project::query()->create([
            'project_name' => 'Hidden Loft',
            'display_order' => 1,
            'is_active' => false,
        ]);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Hidden Loft');
    }
}
