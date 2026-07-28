<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Simulates desktop Chrome/Firefox/Edge multipart behaviour:
 * empty file inputs are usually omitted; when present they use UPLOAD_ERR_NO_FILE.
 */
class ExhibitionDesktopMultipartTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_text_only_edit_omits_all_file_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'design-week',
            'name' => 'Design Week',
            'city' => 'Delhi',
            'country' => 'India',
            'year' => 2024,
            'cover_image' => '/images/exhibitions/cover.svg',
            'gallery' => ['exhibitions/photo-a.jpg', 'exhibitions/photo-b.jpg'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->post(
            route('admin.exhibitions.update', $exhibition),
            [
                '_token' => csrf_token(),
                '_method' => 'PUT',
                '_page_save' => '1',
                'name' => 'Design Week Updated',
                'city' => 'Delhi',
                'country' => 'India',
                'year' => '2024',
                'description' => '',
                'cover_image' => '/images/exhibitions/cover.svg',
                'gallery_managed' => '1',
                'gallery_existing' => ['exhibitions/photo-a.jpg', 'exhibitions/photo-b.jpg'],
                'gallery_urls' => '',
                'sort_order' => '1',
                'is_active' => '1',
            ],
            ['CONTENT_TYPE' => 'multipart/form-data']
        )->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertSame('Design Week Updated', $exhibition->name);
        $this->assertSame('/images/exhibitions/cover.svg', $exhibition->cover_image);
        $this->assertSame(['exhibitions/photo-a.jpg', 'exhibitions/photo-b.jpg'], $exhibition->gallery);
    }

    public function test_desktop_all_empty_gallery_replace_slots_do_not_block_save(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'gallery' => [
                '/images/exhibitions/01.svg',
                '/images/exhibitions/02.svg',
                '/images/exhibitions/03.svg',
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $noFile = fn () => new UploadedFile('', '', null, UPLOAD_ERR_NO_FILE, true);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'gallery_managed' => '1',
            'gallery_existing' => [
                '/images/exhibitions/01.svg',
                '/images/exhibitions/02.svg',
                '/images/exhibitions/03.svg',
            ],
            'gallery_replace' => [
                0 => $noFile(),
                1 => $noFile(),
                2 => $noFile(),
            ],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertSame([
            '/images/exhibitions/01.svg',
            '/images/exhibitions/02.svg',
            '/images/exhibitions/03.svg',
        ], $exhibition->gallery);
    }

    public function test_desktop_cover_no_file_with_gallery_replace_succeeds(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'cover_image' => '/images/exhibitions/cover.svg',
            'gallery' => ['/images/exhibitions/01.svg'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'INDEX 2023',
            'cover_image' => '/images/exhibitions/cover.svg',
            'cover_file' => new UploadedFile('', '', null, UPLOAD_ERR_NO_FILE, true),
            'gallery_managed' => '1',
            'gallery_existing' => ['/images/exhibitions/01.svg'],
            'gallery_replace' => [
                0 => UploadedFile::fake()->image('desktop-photo.JPG'),
            ],
            'gallery_files' => [new UploadedFile('', '', null, UPLOAD_ERR_NO_FILE, true)],
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertSame('/images/exhibitions/cover.svg', $exhibition->cover_image);
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[0]);
        Storage::disk('public')->assertExists($exhibition->gallery[0]);
    }

    public function test_desktop_omitted_file_fields_with_new_gallery_upload(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'gallery' => ['/images/exhibitions/01.svg'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->post(
            route('admin.exhibitions.update', $exhibition),
            [
                '_token' => csrf_token(),
                '_method' => 'PUT',
                '_page_save' => '1',
                'name' => 'INDEX 2023',
                'gallery_managed' => '1',
                'gallery_existing' => ['/images/exhibitions/01.svg'],
                'gallery_files' => [UploadedFile::fake()->image('new-shot.webp')],
                'is_active' => '1',
            ],
            ['CONTENT_TYPE' => 'multipart/form-data']
        )->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertCount(2, $exhibition->gallery ?? []);
        $this->assertSame('/images/exhibitions/01.svg', $exhibition->gallery[0]);
        $this->assertStringStartsWith('exhibitions/', $exhibition->gallery[1]);
    }
}
