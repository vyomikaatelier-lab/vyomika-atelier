<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Support\CmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExhibitionAboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_does_not_render_capabilities_section(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertDontSee('id="capabilities"', false)
            ->assertDontSee('Capabilities', false);
    }

    public function test_exhibition_cover_and_gallery_both_appear_on_about_page(): void
    {
        Storage::fake('public');

        $coverPath = 'exhibitions/index-2023-cover.jpg';
        $galleryPath = 'exhibitions/index-2023-gallery.jpg';
        Storage::disk('public')->put($coverPath, 'cover');
        Storage::disk('public')->put($galleryPath, 'gallery');

        Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'city' => 'Mumbai',
            'year' => 2023,
            'cover_image' => $coverPath,
            'gallery' => [$galleryPath],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('INDEX 2023')
            ->assertSee(asset('storage/'.$coverPath), false)
            ->assertSee(asset('storage/'.$galleryPath), false)
            ->assertSee('am-about-gallery__featured', false);
    }

    public function test_exhibition_cover_is_deduped_from_gallery_on_about_page(): void
    {
        Storage::fake('public');

        $path = 'exhibitions/shared-photo.jpg';
        Storage::disk('public')->put($path, 'shared');

        Exhibition::query()->create([
            'slug' => 'index-2024',
            'name' => 'INDEX 2024',
            'city' => 'Mumbai',
            'year' => 2024,
            'cover_image' => $path,
            'gallery' => [$path, $path],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $events = CmsSettings::exhibitions();
        $event = collect($events)->firstWhere('slug', 'index-2024');

        $this->assertNotNull($event);
        $this->assertSame(asset('storage/'.$path), $event['cover_image']);
        $this->assertSame([], $event['gallery']);
        $this->assertSame([asset('storage/'.$path)], $event['images']);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('am-about-gallery__featured', false)
            ->assertDontSee('am-about-gallery--with-featured', false);
    }

    public function test_config_seed_exhibitions_resolve_gallery_images(): void
    {
        $events = CmsSettings::exhibitions();

        $this->assertNotEmpty($events);
        $this->assertNotEmpty($events[0]['images'] ?? []);
        $this->assertStringStartsWith(url('/'), $events[0]['images'][0]);
    }
}
