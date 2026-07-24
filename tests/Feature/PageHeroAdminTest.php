<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AboutContent;
use App\Support\MirrorFramesContent;
use App\Support\PageHeroContent;
use App\Support\ProfessionalsContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageHeroAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_responsive_about_hero_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $desktop = UploadedFile::fake()->image('about-desktop.jpg', 1600, 900);
        $mobile = UploadedFile::fake()->image('about-mobile.jpg', 800, 1200);

        $this->actingAsAdmin($admin)->put(route('admin.page-heroes.update', 'about'), [
            'hero_title' => 'About Hero',
            'hero_image_file' => $desktop,
            'hero_image_mobile_file' => $mobile,
        ])->assertRedirect(route('admin.page-heroes.edit', 'about'));

        $stored = data_get(SiteSetting::getValue('page_heroes', []), 'about');
        $this->assertSame('About Hero', $stored['title'] ?? null);
        $this->assertNotEmpty($stored['image'] ?? null);
        $this->assertNotEmpty($stored['image_mobile'] ?? null);
        Storage::disk('public')->assertExists($stored['image']);
        Storage::disk('public')->assertExists($stored['image_mobile']);

        $this->assertSame('About Hero', AboutContent::all()['hero']['title']);

        $this->get(url('/about'))
            ->assertOk()
            ->assertSee('About Hero', false)
            ->assertSee('--hero-bg-desktop:', false)
            ->assertSee('--hero-bg-mobile:', false);
    }

    public function test_admin_can_upload_mirror_frames_hero_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $desktop = UploadedFile::fake()->image('mirror-desktop.jpg', 1600, 900);

        $this->actingAsAdmin($admin)->put(route('admin.page-heroes.update', 'mirror-frames'), [
            'hero_image_file' => $desktop,
        ])->assertRedirect();

        $path = data_get(SiteSetting::getValue('page_heroes', []), 'mirror-frames.image');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        $this->get(route('shop.mirror-frames.index'))
            ->assertOk()
            ->assertSee('storage/'.$path, false)
            ->assertSee('--hero-bg-desktop:', false);
    }

    public function test_service_page_hero_uses_uploaded_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('page-heroes/partitions.jpg', 'ok');

        SiteSetting::setValue('page_heroes', [
            'service:slim-profile-door-system' => [
                'image' => 'page-heroes/slim.jpg',
            ],
        ]);

        $hero = PageHeroContent::serviceHero('slim-profile-door-system');
        $this->assertNotNull($hero);
        $this->assertStringContainsString('storage/page-heroes/slim.jpg', (string) ($hero['image'] ?? ''));
    }

    public function test_page_heroes_index_lists_core_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->get(route('admin.page-heroes.index'))
            ->assertOk()
            ->assertSee('About', false)
            ->assertSee('Professionals', false)
            ->assertSee('Mirror Frames', false)
            ->assertSee('Studio service: Partitions', false);
    }

    public function test_defaults_render_without_override(): void
    {
        $this->assertSame(
            config('professionals.hero.title'),
            ProfessionalsContent::all()['hero']['title']
        );

        $this->get(route('professionals.index'))
            ->assertOk()
            ->assertSee(config('professionals.hero.title'), false);
    }
}
