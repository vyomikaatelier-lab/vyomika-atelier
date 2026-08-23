<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProjectUpdateImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_file_reference_count_uses_project_image_path_column(): void
    {
        Storage::fake('public');

        $path = 'projects/legacy-cover.jpg';
        Project::query()->create([
            'project_name' => 'Legacy Cover',
            'image_path' => $path,
            'is_active' => true,
        ]);

        $media = MediaFile::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => 'legacy-cover.jpg',
            'mime' => 'image/jpeg',
            'size' => 100,
            'is_private' => false,
        ]);

        $this->assertSame(1, $media->referenceCount());
    }

    public function test_admin_can_update_fourth_project_with_new_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();

        $projects = [];
        for ($i = 1; $i <= 4; $i++) {
            $path = UploadedFile::fake()->image("project-{$i}.jpg")->store('projects', 'public');
            MediaFile::create([
                'disk' => 'public',
                'path' => $path,
                'filename' => "project-{$i}.jpg",
                'mime' => 'image/jpeg',
                'size' => 100,
                'is_private' => false,
            ]);

            $projects[] = Project::query()->create([
                'project_name' => "Project {$i}",
                'image_path' => $path,
                'display_order' => $i,
                'is_active' => true,
            ]);
        }

        $fourth = $projects[3];

        $this->actingAsAdmin($admin)->put(route('admin.projects.update', $fourth), [
            '_page_save' => '1',
            'project_name' => 'Project 4 Updated',
            'display_order' => '4',
            'image_path' => $fourth->image_path,
            'image_file' => UploadedFile::fake()->image('project-4-new.jpg'),
            'is_active' => '1',
        ])->assertRedirect(route('admin.projects.index'));

        $fourth->refresh();
        $this->assertSame('Project 4 Updated', $fourth->project_name);
        $this->assertNotSame('projects/project-4.jpg', $fourth->image_path);
        Storage::disk('public')->assertExists($fourth->image_path);
    }
}
