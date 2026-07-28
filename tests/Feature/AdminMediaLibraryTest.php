<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_upload_a_public_image_to_the_library(): void
    {
        Storage::fake('public');

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.media.store'), [
                'file' => UploadedFile::fake()->image('brass-swatch.jpg'),
                'alt' => 'Brass swatch',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $media = MediaFile::query()->firstOrFail();

        $this->assertSame('brass-swatch.jpg', $media->filename);
        $this->assertSame('Brass swatch', $media->alt);
        $this->assertFalse($media->is_private);
        $this->assertSame('public', $media->disk);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_private_upload_is_stored_on_the_local_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.media.store'), [
                'file' => UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf'),
                'is_private' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $media = MediaFile::query()->firstOrFail();

        $this->assertTrue($media->is_private);
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists($media->path);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_upload_of_an_unsupported_type_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.media.index'))
            ->post(route('admin.media.store'), [
                'file' => UploadedFile::fake()->create('macro.exe', 10, 'application/x-msdownload'),
            ])
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MediaFile::query()->count());
    }

    public function test_admin_can_update_alt_text(): void
    {
        $media = MediaFile::query()->create([
            'disk' => 'public',
            'path' => 'media/swatch.jpg',
            'filename' => 'swatch.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'is_private' => false,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.media.update', $media), ['alt' => 'Champagne gold PVD swatch'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Champagne gold PVD swatch', $media->fresh()->alt);
    }

    public function test_unreferenced_file_is_deleted_from_disk_and_database(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/swatch.jpg', 'x');

        $media = MediaFile::query()->create([
            'disk' => 'public',
            'path' => 'media/swatch.jpg',
            'filename' => 'swatch.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'is_private' => false,
        ]);

        $this->actingAsAdmin($this->admin())
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, MediaFile::query()->count());
        Storage::disk('public')->assertMissing('media/swatch.jpg');
    }

    public function test_referenced_file_cannot_be_deleted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/in-use.jpg', 'x');

        $media = MediaFile::query()->create([
            'disk' => 'public',
            'path' => 'media/in-use.jpg',
            'filename' => 'in-use.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'is_private' => false,
        ]);

        Product::query()->create([
            'name' => 'Fluted Mirror',
            'slug' => 'fluted-mirror-media-ref',
            'price' => 1000,
            'image' => 'media/in-use.jpg',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, MediaFile::query()->count());
        Storage::disk('public')->assertExists('media/in-use.jpg');
    }

    public function test_media_library_requires_admin_access(): void
    {
        $this->get(route('admin.media.index'))->assertRedirect(route('admin.login'));

        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)
            ->get(route('admin.media.index'))
            ->assertRedirect(route('admin.login'));
    }
}
