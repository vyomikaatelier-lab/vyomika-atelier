<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExhibitionUploadReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_one_gallery_image_without_gallery_existing_uses_current_gallery(): void
    {
        Storage::fake('public');
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
            'name' => 'INDEX 2023',
            'gallery_managed' => '1',
            'gallery_replace' => [
                0 => UploadedFile::fake()->image('photo.jpg'),
            ],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertCount(2, $exhibition->gallery ?? []);
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[0]);
        $this->assertSame('/images/exhibitions/index-2023/02.svg', $exhibition->gallery[1]);
        Storage::disk('public')->assertExists($exhibition->gallery[0]);
    }

    public function test_add_gallery_image_without_gallery_existing_preserves_current_gallery(): void
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
            'gallery_files' => [
                UploadedFile::fake()->image('booth.jpg'),
            ],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertCount(2, $exhibition->gallery ?? []);
        $this->assertSame('/images/exhibitions/index-2023/01.svg', $exhibition->gallery[0]);
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[1]);
        Storage::disk('public')->assertExists($exhibition->gallery[1]);
    }

    public function test_empty_gallery_files_slot_as_uploaded_file_does_not_block_replace(): void
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

        $emptySlot = new UploadedFile('', '', null, UPLOAD_ERR_NO_FILE, true);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'gallery_managed' => '1',
            'gallery_existing' => ['/images/exhibitions/index-2023/01.svg'],
            'gallery_replace' => [
                0 => UploadedFile::fake()->image('photo.jpg'),
            ],
            'gallery_files' => [$emptySlot],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[0]);
        Storage::disk('public')->assertExists($exhibition->gallery[0]);
    }
}
