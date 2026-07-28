<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExhibitionMultipartBrowserTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_byte_gallery_files_slot_with_ok_error_does_not_block_replace(): void
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

        $tmp = tempnam(sys_get_temp_dir(), 'empty');
        $zeroByteSlot = new UploadedFile($tmp, '', 'application/octet-stream', UPLOAD_ERR_OK, true);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'gallery_managed' => '1',
            'gallery_existing' => ['/images/exhibitions/index-2023/01.svg'],
            'gallery_replace' => [
                0 => UploadedFile::fake()->image('photo.jpg'),
            ],
            'gallery_files' => [$zeroByteSlot],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[0]);
        Storage::disk('public')->assertExists($exhibition->gallery[0]);
    }

    public function test_empty_gallery_replace_slots_from_mobile_do_not_block_save(): void
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

        $tmp = tempnam(sys_get_temp_dir(), 'empty');
        $emptyReplaceSlot = new UploadedFile($tmp, '', 'application/octet-stream', UPLOAD_ERR_OK, true);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'gallery_managed' => '1',
            'gallery_existing' => [
                '/images/exhibitions/index-2023/01.svg',
                '/images/exhibitions/index-2023/02.svg',
            ],
            'gallery_replace' => [
                0 => UploadedFile::fake()->image('photo.jpg'),
                1 => $emptyReplaceSlot,
            ],
            'gallery_files' => [new UploadedFile('', '', null, UPLOAD_ERR_NO_FILE, true)],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[0]);
        $this->assertSame('/images/exhibitions/index-2023/02.svg', $exhibition->gallery[1]);
    }

    public function test_edit_form_multipart_post_with_empty_gallery_files_and_replace_matches_browser(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'city' => 'Mumbai',
            'country' => 'India',
            'year' => 2023,
            'cover_image' => '/images/exhibitions/index-2023/cover.svg',
            'gallery' => [
                '/images/exhibitions/index-2023/01.svg',
                '/images/exhibitions/index-2023/02.svg',
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $emptyGallerySlot = new UploadedFile('', '', null, UPLOAD_ERR_NO_FILE, true);

        $this->actingAsAdmin($admin)->post(
            route('admin.exhibitions.update', $exhibition),
            [
                '_token' => csrf_token(),
                '_method' => 'PUT',
                '_page_save' => '1',
                'name' => 'INDEX 2023',
                'city' => 'Mumbai',
                'country' => 'India',
                'year' => '2023',
                'description' => '',
                'cover_image' => '/images/exhibitions/index-2023/cover.svg',
                'gallery_managed' => '1',
                'gallery_existing' => [
                    '/images/exhibitions/index-2023/01.svg',
                    '/images/exhibitions/index-2023/02.svg',
                ],
                'gallery_replace' => [
                    0 => UploadedFile::fake()->image('iphone-photo.jpg'),
                ],
                'gallery_files' => [$emptyGallerySlot],
                'sort_order' => '1',
                'is_active' => '1',
            ],
            ['CONTENT_TYPE' => 'multipart/form-data']
        )->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertCount(2, $exhibition->gallery ?? []);
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[0]);
        $this->assertSame('/images/exhibitions/index-2023/02.svg', $exhibition->gallery[1]);
        Storage::disk('public')->assertExists($exhibition->gallery[0]);
    }
}
