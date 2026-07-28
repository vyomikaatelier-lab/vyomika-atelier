<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Models\User;
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
}
