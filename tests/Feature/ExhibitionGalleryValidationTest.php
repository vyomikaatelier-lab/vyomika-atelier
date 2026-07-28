<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Models\User;
use App\Support\AdminImageUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExhibitionGalleryValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_gallery_file_slot_does_not_block_save(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'gallery' => ['/images/exhibitions/index-2023/01.svg'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'gallery_managed' => '1',
            'gallery_existing' => ['/images/exhibitions/index-2023/01.svg'],
            'gallery_files' => [''],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertSame('INDEX 2023', $exhibition->name);
        $this->assertSame(['/images/exhibitions/index-2023/01.svg'], $exhibition->gallery);
    }

    public function test_mixed_valid_and_empty_gallery_uploads_save_new_files_only(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2024',
            'name' => 'INDEX 2024',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2024',
            'gallery_managed' => '1',
            'gallery_files' => [
                UploadedFile::fake()->image('booth.jpg'),
                '',
            ],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertCount(1, $exhibition->gallery ?? []);
    }

    public function test_cover_upload_with_uppercase_jpg_extension(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'cover_image' => '/images/exhibitions/index-2023/01.svg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'cover_image' => '/images/exhibitions/index-2023/01.svg',
            'cover_file' => UploadedFile::fake()->image('IMG_4811.JPG'),
            'gallery_managed' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertStringStartsWith('exhibitions/', $exhibition->cover_image);
        Storage::disk('public')->assertExists($exhibition->cover_image);
    }

    public function test_gallery_replace_with_uppercase_jpg_at_matching_index(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'gallery' => [
                '/images/exhibitions/index-2023/01.svg',
                '/images/exhibitions/index-2023/02.svg',
                '/images/exhibitions/index-2023/03.svg',
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'gallery_managed' => '1',
            'gallery_existing' => [
                '/images/exhibitions/index-2023/01.svg',
                '/images/exhibitions/index-2023/02.svg',
                '/images/exhibitions/index-2023/03.svg',
            ],
            'gallery_replace' => [
                0 => UploadedFile::fake()->image('WhatsApp-1.JPEG'),
                1 => UploadedFile::fake()->image('WhatsApp-2.JPEG'),
                2 => UploadedFile::fake()->image('WhatsApp-3.JPEG'),
            ],
            'gallery_files' => [''],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertCount(3, $exhibition->gallery ?? []);
        foreach ($exhibition->gallery as $path) {
            $this->assertStringStartsWith('exhibitions/', $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_heic_cover_upload_shows_clear_validation_error(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->from(route('admin.exhibitions.edit', $exhibition))
            ->put(route('admin.exhibitions.update', $exhibition), [
                '_page_save' => '1',
                'name' => 'INDEX 2023',
                'gallery_managed' => '1',
                'cover_file' => UploadedFile::fake()->create('IMG_4811.HEIC', 100, 'image/heic'),
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.exhibitions.edit', $exhibition))
            ->assertSessionHasErrors('cover_file');

        $this->assertStringContainsString(
            'HEIC',
            session('errors')->get('cover_file')[0]
        );
    }

    public function test_failed_cover_upload_shows_validation_error_instead_of_silent_save(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'cover_image' => '/images/exhibitions/index-2023/01.svg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $failedUpload = new UploadedFile(
            '',
            'IMG_4811.JPG',
            'image/jpeg',
            UPLOAD_ERR_INI_SIZE,
            true
        );

        $this->actingAsAdmin($admin)->from(route('admin.exhibitions.edit', $exhibition))
            ->put(route('admin.exhibitions.update', $exhibition), [
                '_page_save' => '1',
                'name' => 'INDEX 2023',
                'cover_image' => '/images/exhibitions/index-2023/01.svg',
                'cover_file' => $failedUpload,
                'gallery_managed' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.exhibitions.edit', $exhibition))
            ->assertSessionHasErrors('cover_file');

        $this->assertSame('/images/exhibitions/index-2023/01.svg', $exhibition->fresh()->cover_image);
    }

    public function test_exhibition_form_shows_mobile_upload_hints(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->get(route('admin.exhibitions.create'))
            ->assertOk()
            ->assertSee(AdminImageUpload::hintText(), false)
            ->assertSee(AdminImageUpload::acceptAttribute(), false);
    }
}
