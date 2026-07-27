<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProjectGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_project_with_uploaded_gallery_images(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();

        $response = $this->actingAsAdmin($admin)->post(route('admin.projects.store'), [
            'title' => 'Gallery Test Project',
            'slug' => 'gallery-test-project',
            'gallery_files' => [
                UploadedFile::fake()->image('detail-a.jpg'),
                UploadedFile::fake()->image('detail-b.jpg'),
            ],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.projects.index'));
        $response->assertSessionHas('success');

        $project = Project::query()->where('slug', 'gallery-test-project')->first();
        $this->assertNotNull($project);
        $this->assertIsArray($project->gallery);
        $this->assertCount(2, $project->gallery);

        foreach ($project->gallery as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_admin_destroy_deletes_stored_gallery_files(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $path = UploadedFile::fake()->image('cover.jpg')->store('projects', 'public');

        $project = Project::query()->create([
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'gallery' => [$path],
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'));

        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_can_update_project_without_losing_existing_gallery(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $project = Project::query()->create([
            'title' => 'Gallery Project',
            'slug' => 'gallery-project',
            'gallery' => ['projects/photo-a.jpg', 'projects/photo-b.jpg'],
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.projects.update', $project), [
            '_page_save' => '1',
            'title' => 'Gallery Project Updated',
            'summary' => 'Updated summary',
            'gallery_managed' => '1',
            'gallery_existing' => ['projects/photo-a.jpg', 'projects/photo-b.jpg'],
            'is_active' => '1',
        ])->assertRedirect(route('admin.projects.index'));

        $project->refresh();
        $this->assertSame('Gallery Project Updated', $project->title);
        $this->assertSame(['projects/photo-a.jpg', 'projects/photo-b.jpg'], $project->gallery);
    }
}
