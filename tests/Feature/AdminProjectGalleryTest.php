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

        $response = $this->actingAs($admin)->post(route('admin.projects.store'), [
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

        $this->actingAs($admin)->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'));

        Storage::disk('public')->assertMissing($path);
    }
}
