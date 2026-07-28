<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExhibitionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_exhibition_without_losing_existing_gallery(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'design-week',
            'name' => 'Design Week',
            'city' => 'Delhi',
            'country' => 'India',
            'year' => 2024,
            'gallery' => ['exhibitions/photo-a.jpg', 'exhibitions/photo-b.jpg'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'Design Week Updated',
            'city' => 'Delhi',
            'country' => 'India',
            'year' => 2024,
            'description' => 'Updated copy',
            'gallery_managed' => '1',
            'gallery_existing' => ['exhibitions/photo-a.jpg', 'exhibitions/photo-b.jpg'],
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertSame('Design Week Updated', $exhibition->name);
        $this->assertSame(['exhibitions/photo-a.jpg', 'exhibitions/photo-b.jpg'], $exhibition->gallery);
    }

    public function test_admin_can_add_gallery_images_with_separate_upload_fields(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'material-fair',
            'name' => 'Material Fair',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'Material Fair',
            'country' => 'India',
            'gallery_managed' => '1',
            'gallery_files' => [
                UploadedFile::fake()->image('booth-one.jpg'),
                UploadedFile::fake()->image('booth-two.jpg'),
            ],
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertCount(2, $exhibition->gallery ?? []);
        Storage::disk('public')->assertExists($exhibition->gallery[0]);
        Storage::disk('public')->assertExists($exhibition->gallery[1]);
    }

    public function test_admin_form_shows_per_image_gallery_upload_rows(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->get(route('admin.exhibitions.create'))
            ->assertOk()
            ->assertSee('data-gallery-upload', false)
            ->assertSee('name="gallery_files[]"', false)
            ->assertSee('+ Add another image');
    }

    public function test_admin_can_create_exhibition_with_text_fields_only(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->post(route('admin.exhibitions.store'), [
            '_page_save' => '1',
            'name' => 'New Expo',
            'city' => 'Mumbai',
            'country' => 'India',
            'year' => 2025,
            'description' => 'Opening night',
            'gallery_managed' => '1',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition = Exhibition::query()->where('slug', 'new-expo')->first();
        $this->assertNotNull($exhibition);
        $this->assertSame('New Expo', $exhibition->name);
        $this->assertNull($exhibition->gallery);
    }

    public function test_browser_empty_year_and_sort_order_do_not_block_save(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->post(route('admin.exhibitions.store'), [
            '_page_save' => '1',
            'name' => 'Browser Expo',
            'city' => 'Mumbai',
            'country' => 'India',
            'year' => '',
            'description' => 'Test',
            'gallery_managed' => '1',
            'sort_order' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $this->assertNotNull(Exhibition::query()->where('slug', 'browser-expo')->first());
    }

    public function test_text_update_preserves_gallery_when_existing_fields_not_submitted(): void
    {
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'gallery' => [
                '/images/exhibitions/index-2023/01.svg',
                '/images/exhibitions/index-2023/02.svg',
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023 Updated',
            'gallery_managed' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertSame('INDEX 2023 Updated', $exhibition->name);
        $this->assertSame([
            '/images/exhibitions/index-2023/01.svg',
            '/images/exhibitions/index-2023/02.svg',
        ], $exhibition->gallery);
    }

    public function test_duplicate_name_shows_validation_error(): void
    {
        $admin = User::factory()->admin()->create();

        Exhibition::query()->create([
            'slug' => 'index-2024',
            'name' => 'INDEX 2024',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->from(route('admin.exhibitions.edit', $exhibition))
            ->put(route('admin.exhibitions.update', $exhibition), [
                '_page_save' => '1',
                'name' => 'INDEX 2024',
                'gallery_managed' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.exhibitions.edit', $exhibition))
            ->assertSessionHasErrors('name');
    }

    public function test_edit_form_save_button_is_explicit_submit(): void
    {
        $admin = User::factory()->admin()->create();
        $exhibition = Exhibition::query()->create([
            'slug' => 'design-week',
            'name' => 'Design Week',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.exhibitions.edit', $exhibition))
            ->assertOk()
            ->assertSee('type="submit"', false)
            ->assertSee('name="_page_save"', false);
    }

    public function test_desktop_text_only_multipart_post_updates_exhibition(): void
    {
        $admin = User::factory()->admin()->create();
        $exhibition = Exhibition::query()->create([
            'slug' => 'design-week',
            'name' => 'Design Week',
            'city' => 'Delhi',
            'country' => 'India',
            'year' => 2024,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->post(
            route('admin.exhibitions.update', $exhibition),
            [
                '_token' => csrf_token(),
                '_method' => 'PUT',
                '_page_save' => '1',
                'name' => 'Design Week Renamed',
                'city' => 'Mumbai',
                'country' => 'India',
                'year' => '2024',
                'description' => 'Updated on desktop',
                'gallery_managed' => '1',
                'gallery_urls' => '',
                'sort_order' => '1',
                'is_active' => '1',
            ],
            ['CONTENT_TYPE' => 'multipart/form-data']
        )
            ->assertRedirect(route('admin.exhibitions.index'))
            ->assertSessionHas('success');

        $exhibition->refresh();
        $this->assertSame('Design Week Renamed', $exhibition->name);
        $this->assertSame('Mumbai', $exhibition->city);
        $this->assertSame('Updated on desktop', $exhibition->description);
    }
}
