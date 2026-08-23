<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\ProductImageSizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProjectGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_project_with_uploaded_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();

        $response = $this->actingAsAdmin($admin)->post(route('admin.projects.store'), [
            'project_name' => 'Gallery Test Project',
            'work_type' => 'Commercial',
            'city' => 'Mumbai',
            'image_file' => UploadedFile::fake()->image('detail-a.jpg'),
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.projects.index'));
        $response->assertSessionHas('success');

        $project = Project::query()->where('project_name', 'Gallery Test Project')->first();
        $this->assertNotNull($project);
        $this->assertNotNull($project->image_path);
        Storage::disk('public')->assertExists($project->image_path);
    }

    public function test_admin_destroy_deletes_stored_image_file(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $path = UploadedFile::fake()->image('cover.jpg')->store('projects', 'public');

        $project = Project::query()->create([
            'project_name' => 'Delete Me',
            'image_path' => $path,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'));

        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_can_update_project_without_losing_image_path(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $project = Project::query()->create([
            'project_name' => 'Gallery Project',
            'image_path' => 'projects/photo-a.jpg',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.projects.update', $project), [
            '_page_save' => '1',
            'project_name' => 'Gallery Project Updated',
            'description' => 'Updated summary',
            'image_path' => 'projects/photo-a.jpg',
            'is_active' => '1',
        ])->assertRedirect(route('admin.projects.index'));

        $project->refresh();
        $this->assertSame('Gallery Project Updated', $project->project_name);
        $this->assertSame('projects/photo-a.jpg', $project->image_path);
    }

    public function test_admin_project_form_shows_portrait_upload_hint(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->get(route('admin.projects.create'))
            ->assertOk()
            ->assertSee(ProductImageSizes::projectGalleryAdminHint(), false)
            ->assertSee(ProductImageSizes::designGalleryDimensionsLabel(), false);

        $project = Project::query()->create([
            'project_name' => 'Hint Test',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->get(route('admin.projects.edit', $project))
            ->assertOk()
            ->assertSee(ProductImageSizes::projectGalleryAdminHint(), false);
    }
}

